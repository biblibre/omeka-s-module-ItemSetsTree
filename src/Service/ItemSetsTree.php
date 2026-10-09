<?php

/*
 * Copyright 2020 BibLibre
 *
 * This file is part of ItemSetsTree.
 *
 * ItemSetsTree is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * ItemSetsTree is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with ItemSetsTree.  If not, see <https://www.gnu.org/licenses/>.
 */

namespace ItemSetsTree\Service;

use Doctrine\ORM\EntityManager;
use ItemSetsTree\Entity\ItemSetsTreeEdge;
use Omeka\Api\Adapter\Manager as ApiAdapterManager;
use Omeka\Api\Manager as ApiManager;
use Omeka\Api\Representation\ItemSetRepresentation;
use Omeka\Entity\ItemSet;
use Omeka\Settings\Settings;
use Omeka\Settings\SiteSettings;

class ItemSetsTree
{
    protected $api;
    protected $em;
    protected $apiAdapters;
    protected $settings;
    protected $siteSettings;

    public function setApiManager(ApiManager $api)
    {
        $this->api = $api;
    }

    public function setEntityManager(EntityManager $em)
    {
        $this->em = $em;
    }

    public function setApiAdapterManager(ApiAdapterManager $apiAdapters)
    {
        $this->apiAdapters = $apiAdapters;
    }

    public function setSettings(Settings $settings)
    {
        $this->settings = $settings;
    }

    public function setSiteSettings(SiteSettings $siteSettings)
    {
        $this->siteSettings = $siteSettings;
    }

    public function getRootItemSets(array $options = [])
    {
        $qb = $this->em->createQueryBuilder();
        $qb->select('itemset')
            ->from('Omeka\Entity\ItemSet', 'itemset')
            ->leftJoin('ItemSetsTree\Entity\ItemSetsTreeEdge', 'edge', 'WITH', 'itemset = edge.itemSet')
            ->where($qb->expr()->isNull('edge.parentItemSet'));

        $sorting_method = $options['sorting_method'] ?? $this->settings->get('itemsetstree_sorting_method', 'title');
        if ($sorting_method === 'none') {
            $qb->addOrderBy('edge.rank');
        } else {
            $qb->addOrderBy('itemset.title');
        }

        $qb->groupBy('itemset.id');

        $query = $qb->getQuery();
        $itemSets = $query->getResult();

        $rootItemSets = [];
        foreach ($itemSets as $itemSet) {
            $rootItemSets[$itemSet->getId()] = $this->getItemSetRepresentation($itemSet);
        }

        return $rootItemSets;
    }

    public function getSiteItemSets($site_id, array $options = [])
    {
        $display = $options['display'] ?? $this->siteSettings->get('itemsetstree_display', 'all', $site_id);
        if ($display === 'all') {
            return $this->api->search('item_sets')->getContent();
        }

        $qb = $this->em->createQueryBuilder();
        $qb->select('itemset')
            ->from('Omeka\Entity\ItemSet', 'itemset')
            ->leftJoin('Omeka\Entity\SiteItemSet', 'siteItemSet', 'WITH', 'itemset = siteItemSet.itemSet')
            ->where('siteItemSet.site = :site')
            ->setParameter(':site', $site_id);

        $qb->groupBy('itemset.id');

        $query = $qb->getQuery();
        $itemSets = $query->getResult();

        $siteItemSets = [];
        foreach ($itemSets as $itemSet) {
            $siteItemSets[$itemSet->getId()] = $this->getItemSetRepresentation($itemSet);
        }

        if ($display === 'selected-and-descendants') {
            $itemSetsToCheck = $siteItemSets;
            while ($itemSet = array_shift($itemSetsToCheck)) {
                $children = $this->getChildren($itemSet, $options);
                foreach ($children as $child) {
                    if (!array_key_exists($child->id(), $siteItemSets)) {
                        $siteItemSets[$child->id()] = $child;
                        $itemSetsToCheck[] = $child;
                    }
                }
            }
        }

        return $siteItemSets;
    }

    /**
     * @deprecated Use getTree instead
     */
    public function getItemSetsTree(?int $maxDepth = null, array $options = [])
    {
        return $this->getTree(
            maxDepth: $maxDepth,
            siteId: $options['site_id'] ?? null,
            sortBy: $options['sorting_method'] ?? null
        );
    }

    /**
     * Fetch item sets in a tree-like nested arrays structure
     *
     * @param ?int $rootItemSetId   If given, fetch a sub-tree starting at this
     *                              item set. If $siteId is given too the
     *                              site's itemsetstree_display setting only
     *                              allows site's item sets, this item set
     *                              needs to belong to the site too.
     * @param ?int $siteId  If given, the item sets returned depend on the
     *                      site's itemsetstree_display setting
     * @param ?int $maxDepth    Maximum depth of the tree
     * @param ?string $sortBy   Sort item sets by title ("title") or do not
     *                          sort/use the order set manually in the admin
     *                          ("none")
     *
     * @return array[]  A tree-like nested arrays structure. The top-level
     *                  elements are associative arrays with the following keys:
     *                  * "itemSet": \Omeka\Api\Representation\ItemSetRepresentation
     *                  * "children": array of associative arrays similar to the top-elements
     *                  * "parent": reference to the parent element in the tree
     *                  * "rank": the order of this element among its siblings (used when $sortBy === "none")
     */
    public function getTree(?int $rootItemSetId = null, ?int $siteId = null, ?int $maxDepth = null, ?string $sortBy = null): array
    {
        $tree = [];

        $display = 'all';
        if ($siteId !== null) {
            $display = $this->siteSettings->get('itemsetstree_display', 'all', $siteId);
        }

        $sortBy ??= $this->settings->get('itemsetstree_sorting_method', 'title');

        $qb = $this->em->createQueryBuilder();
        $qb->select(['itemSet', 'edge']);
        $qb->from('Omeka\Entity\ItemSet', 'itemSet');
        $qb->leftJoin('ItemSetsTree\Entity\ItemSetsTreeEdge', 'edge', 'WITH', 'itemSet = edge.itemSet');

        if ($sortBy === 'title') {
            $qb->addOrderBy('itemSet.title');
        } else {
            $qb->addOrderBy('edge.rank');
        }

        // First query needs to be slightly different than the next (in-loop) queries
        $firstQb = clone $qb;
        if ($rootItemSetId !== null) {
            $firstQb->andWhere('edge.parentItemSet = :parentItemSetId');
            $firstQb->setParameter('parentItemSetId', $rootItemSetId);

            if ($siteId !== null && $display !== 'all') {
                // Make sure the root item set belongs to the site
                $firstQb->innerJoin('edge.parentItemSet', 'parentItemSet');
                $firstQb->innerJoin('parentItemSet.siteItemSets', 'parentItemSetSiteItemSets');
                $firstQb->andWhere('parentItemSetSiteItemSets.site = :siteId');

                if ($display === 'selected') {
                    // Select only children that belongs to the site
                    $firstQb->innerJoin('itemSet.siteItemSets', 'siteItemSets');
                    $firstQb->andWhere('siteItemSets.site = :siteId');

                    // Same while in loop
                    $qb->innerJoin('itemSet.siteItemSets', 'siteItemSets');
                    $qb->andWhere('siteItemSets.site = :siteId');
                    $qb->setParameter('siteId', $siteId);
                }

                $firstQb->setParameter('siteId', $siteId);
            }
        } else {
            if ($siteId !== null && $display !== 'all') {
                // Select all item sets that belongs to the site, they can be at any depth
                $firstQb->innerJoin('itemSet.siteItemSets', 'siteItemSets');
                $firstQb->andWhere('siteItemSets.site = :siteId');
                $firstQb->setParameter('siteId', $siteId);
            } else {
                // Select only root item sets
                $firstQb->andWhere('edge.parentItemSet IS NULL');
            }
        }

        $qb->andWhere('edge.parentItemSet IN (:parentItemSetsIds)');

        $results = $firstQb->getQuery()->getResult();

        $itemSetMap = [];

        $depth = 1;

        if ($rootItemSetId) {
            $rootItemSet = $this->em->find('Omeka\Entity\ItemSet', $rootItemSetId);
            $itemSetMap[$rootItemSetId] = [
                'itemSet' => $this->getItemSetRepresentation($rootItemSet),
                'children' => [],
                'parent' => null,
                'rank' => 0,
            ];

            $depth++;
        }

        while ($results && ($maxDepth === null || $depth <= $maxDepth)) {
            $newItemSetsIds = [];

            // First loop: put new elements into $itemSetMap
            for ($i = 0; $i + 1 < count($results); $i += 2) {
                /**
                 * @var \Omeka\Entity\ItemSet $itemSet
                 * @var ?\ItemSetsTree\Entity\ItemSetsTreeEdge $edge
                 */
                $itemSet = $results[$i];
                $edge = $results[$i + 1];

                if (!array_key_exists($itemSet->getId(), $itemSetMap)) {
                    $itemSetMap[$itemSet->getId()] = [
                        'itemSet' => $this->getItemSetRepresentation($itemSet),
                        'children' => [],
                        'parent' => null,
                        'rank' => $edge ? $edge->getRank() : 0,
                    ];

                    $newItemSetsIds[$itemSet->getId()] = $itemSet->getId();
                }
            }

            // Second loop: create parent/child relations
            for ($i = 0; $i + 1 < count($results); $i += 2) {
                /**
                 * @var \Omeka\Entity\ItemSet $itemSet
                 * @var ?\ItemSetsTree\Entity\ItemSetsTreeEdge $edge
                 * @var ?\Omeka\Entity\ItemSet $parentItemSet
                 */
                $itemSet = $results[$i];
                $edge = $results[$i + 1];
                $parentItemSet = $edge?->getParentItemSet();

                $isNew = array_key_exists($itemSet->getId(), $newItemSetsIds);

                if ($isNew && $parentItemSet && array_key_exists($parentItemSet->getId(), $itemSetMap)) {
                    $itemSetMap[$parentItemSet->getId()]['children'][] = &$itemSetMap[$itemSet->getId()];
                    $itemSetMap[$itemSet->getId()]['parent'] = &$itemSetMap[$parentItemSet->getId()];
                }
            }

            if ($rootItemSetId === null && $siteId !== null && $display === 'selected') {
                // In this particular case, all needed item sets have been
                // fetched by the first query, so we don't need to fetch more
                break;
            }

            // Prepare next iteration
            $qb->setParameter('parentItemSetsIds', $newItemSetsIds);
            $results = $qb->getQuery()->getResult();
            $depth++;
        }

        foreach ($itemSetMap as $itemSetNode) {
            if ($itemSetNode['parent'] === null) {
                $tree[] = $itemSetNode;
            }
        }

        // When no root item set is selected and site's display setting is not
        // "all", the above code can build a tree more deep than $maxDepth and
        // can mess up the order so we need to truncate it and reorder it
        if ($rootItemSetId === null && $siteId !== null && $display !== 'all' && $maxDepth !== null && $maxDepth > 0) {
            $this->truncateTree($tree, $maxDepth);

            if ($sortBy === 'title') {
                $sortFn = fn ($a, $b) => strcmp($a['itemSet']->title() ?? '', $b['itemSet']->title() ?? '');
            } else {
                $sortFn = fn ($a, $b) => $a['rank'] - $b['rank'];
            }
            $this->sortTree($tree, $sortFn);
        }

        return $tree;
    }

    public function getParent(ItemSetRepresentation $itemSet): ?ItemSetRepresentation
    {
        $qb = $this->em->createQueryBuilder();
        $qb->select('itemset')
            ->from('Omeka\Entity\ItemSet', 'itemset')
            ->innerJoin('ItemSetsTree\Entity\ItemSetsTreeEdge', 'edge', 'WITH', 'itemset = edge.parentItemSet')
            ->where('edge.itemSet = :itemSetId')
            ->setParameter('itemSetId', $itemSet->id());

        $query = $qb->getQuery();
        $parentItemSets = $query->getResult();
        $parentItemSet = array_shift($parentItemSets);

        if ($parentItemSet) {
            $itemSetAdapter = $this->apiAdapters->get('item_sets');
            $itemSetRepresentation = new ItemSetRepresentation($parentItemSet, $itemSetAdapter);

            return $itemSetRepresentation;
        }

        return null;
    }

    public function getAncestors(ItemSetRepresentation $itemSet)
    {
        $ancestors = [];
        while ($parentItemSet = $this->getParent($itemSet)) {
            $ancestors[] = $parentItemSet;
            $itemSet = $parentItemSet;
        }

        return $ancestors;
    }

    public function getChildren(ItemSetRepresentation $itemSet, array $options = [])
    {
        $itemSetAdapter = $this->apiAdapters->get('item_sets');

        $qb = $this->em->createQueryBuilder();
        $qb->select('itemset')
            ->from('Omeka\Entity\ItemSet', 'itemset')
            ->leftJoin('ItemSetsTree\Entity\ItemSetsTreeEdge', 'edge', 'WITH', 'itemset = edge.itemSet')
            ->where('edge.parentItemSet = :itemSetId')
            ->setParameter('itemSetId', $itemSet->id());

        $sorting_method = $options['sorting_method'] ?? $this->settings->get('itemsetstree_sorting_method', 'title');
        if ($sorting_method === 'none') {
            $qb->addOrderBy('edge.rank');
        } else {
            $qb->addOrderBy('itemset.title');
        }
        $qb->groupBy('itemset.id');

        $query = $qb->getQuery();
        $itemSets = $query->getResult();

        $itemSetsRepresentations = array_map(function ($itemSet) use ($itemSetAdapter) {
            return new ItemSetRepresentation($itemSet, $itemSetAdapter);
        }, $itemSets);

        return $itemSetsRepresentations;
    }

    public function getDescendants(ItemSetRepresentation $itemSet, array $options = [])
    {
        $descendants = [];
        $children = $this->getChildren($itemSet, $options);
        while ($child = array_shift($children)) {
            $descendants[] = $child;
            $children = array_merge($children, $this->getChildren($child, $options));
        }

        return $descendants;
    }

    public function replaceTree(array $tree = [])
    {
        $em = $this->em;
        $edges = $em->getRepository(ItemSetsTreeEdge::class)->findAll();
        foreach ($edges as $edge) {
            $em->remove($edge);
        }
        $em->flush();

        $itemSetRepository = $em->getRepository(ItemSet::class);
        $buildTree = function ($treeNode) use (&$buildTree, $em, $itemSetRepository) {
            $parentItemSet = $itemSetRepository->find($treeNode['item-set-id']);
            if (isset($treeNode['children'])) {
                foreach ($treeNode['children'] as $rank => $child) {
                    $itemSet = $itemSetRepository->find($child['item-set-id']);
                    $edge = new ItemSetsTreeEdge();
                    $edge->setParentItemSet($parentItemSet);
                    $edge->setItemSet($itemSet);
                    $edge->setRank($rank);
                    $em->persist($edge);

                    $buildTree($child);
                }
            }
        };

        foreach ($tree as $rank => $treeNode) {
            $itemSet = $itemSetRepository->find($treeNode['item-set-id']);
            $edge = new ItemSetsTreeEdge();
            $edge->setItemSet($itemSet);
            $edge->setParentItemSet(null);
            $edge->setRank($rank);
            $em->persist($edge);

            $buildTree($treeNode);
        }

        $em->flush();
    }

    protected function getItemSetRepresentation(ItemSet $itemSet)
    {
        $itemSetAdapter = $this->apiAdapters->get('item_sets');

        return new ItemSetRepresentation($itemSet, $itemSetAdapter);
    }

    protected function getItemSetsTreeEdge(ItemSetRepresentation $itemSet)
    {
        $edges = $this->api->search('item_sets_tree_edges', ['item_set_id' => $itemSet->id()])->getContent();
        return reset($edges);
    }

    protected function truncateTree(array &$tree, int $maxDepth, int $depth = 1)
    {
        if ($depth === $maxDepth) {
            foreach ($tree as &$treeNode) {
                $treeNode['children'] = [];
            }
            return;
        }

        foreach ($tree as &$treeNode) {
            $this->truncateTree($treeNode['children'], $maxDepth, $depth + 1);
        }
    }

    protected function sortTree(array &$tree, callable $sortFn): void
    {
        usort($tree, $sortFn);
        foreach ($tree as $node) {
            $this->sortTree($node['children'], $sortFn);
        }
    }
}
