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

    public function getItemSetsTree(int $maxDepth = null, array $options = [])
    {
        $itemSetsTree = [];
        $itemSetsTreeFlat = [];

        $site_id = $options['site_id'] ?? null;
        if ($site_id) {
            $itemSets = $this->getSiteItemSets($site_id, $options);
        } else {
            $itemSets = $this->api->search('item_sets')->getContent();
        }

        foreach ($itemSets as $itemSet) {
            $itemSetsTreeFlat[$itemSet->id()] = [
                'itemSet' => $itemSet,
                'children' => [],
                'parent' => null,
                'rank' => 0,
            ];
        }

        foreach ($itemSetsTreeFlat as $id => &$itemSetsTreeNodeRef) {
            $edge = $this->getItemSetsTreeEdge($itemSetsTreeNodeRef['itemSet']);
            if ($edge) {
                $itemSetsTreeNodeRef['rank'] = $edge->rank();

                $parentItemSet = $edge->parentItemSet();
                if ($parentItemSet && array_key_exists($parentItemSet->id(), $itemSetsTreeFlat)) {
                    $itemSetsTreeNodeParent = & $itemSetsTreeFlat[$parentItemSet->id()];
                    $itemSetsTreeNodeParent['children'][] = & $itemSetsTreeNodeRef;
                    $itemSetsTreeNodeRef['parent'] = & $itemSetsTreeNodeParent;
                }
            }
        }

        $sorting_method = $options['sorting_method'] ?? $this->settings->get('itemsetstree_sorting_method', 'title');
        if ($sorting_method === 'title') {
            $sortingFunction = function ($a, $b) {
                return strcmp($a['itemSet']->title(), $b['itemSet']->title());
            };
        } else {
            $sortingFunction = function ($a, $b) {
                return $a['rank'] - $b['rank'];
            };
        }

        foreach ($itemSetsTreeFlat as &$itemSetsTreeNodeRef) {
            usort($itemSetsTreeNodeRef['children'], $sortingFunction);
        }

        foreach ($itemSetsTreeFlat as $itemSetsTreeNode) {
            if (is_null($itemSetsTreeNode['parent'])) {
                $itemSetsTree[] = $itemSetsTreeNode;
            }
        }
        usort($itemSetsTree, $sortingFunction);

        if ($maxDepth) {
            $this->truncateTree($itemSetsTree, $maxDepth);
        }

        return $itemSetsTree;
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
}
