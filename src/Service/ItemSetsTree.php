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

class ItemSetsTree
{
    protected $api;
    protected $em;
    protected $apiAdapters;
    protected $settings;

    public function __construct(ApiManager $api, EntityManager $em, ApiAdapterManager $apiAdapters, Settings $settings)
    {
        $this->api = $api;
        $this->em = $em;
        $this->apiAdapters = $apiAdapters;
        $this->settings = $settings;
    }

    public function getRootItemSets(array $options = [])
    {
        $itemSetAdapter = $this->apiAdapters->get('item_sets');
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

        $itemSetsRepresentations = array_map(function ($itemSet) use ($itemSetAdapter) {
            return new ItemSetRepresentation($itemSet, $itemSetAdapter);
        }, $itemSets);

        return $itemSetsRepresentations;
    }

    public function getItemSetsTree(int $maxDepth = null, array $options = [])
    {
        $rootItemSets = $this->getRootItemSets($options);

        $itemSetsTree = [];
        foreach ($rootItemSets as $itemSet) {
            $itemSetsTree[] = [
                'itemSet' => $itemSet,
                'children' => [],
            ];
        }

        $currentDepth = 2;
        $this->fetchItemSetsTreeChildren($itemSetsTree, $currentDepth, $maxDepth, $options);

        return $itemSetsTree;
    }

    public function getParent(ItemSetRepresentation $itemSet)
    {
        $itemSetsTreeEdges = $this->api->search('item_sets_tree_edges', ['item_set_id' => $itemSet->id()])->getContent();
        if (!empty($itemSetsTreeEdges)) {
            $parentItemSet = $itemSetsTreeEdges[0]->parentItemSet();

            return $parentItemSet;
        }
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

    protected function fetchItemSetsTreeChildren(&$itemSetsTree, $currentDepth, $maxDepth = null, array $options = [])
    {
        if (isset($maxDepth) && $currentDepth > $maxDepth) {
            return;
        }

        foreach ($itemSetsTree as &$itemSetsTreeNode) {
            $children = $this->getChildren($itemSetsTreeNode['itemSet'], $options);
            $childrenNodes = array_map(function ($itemSet) {
                return ['itemSet' => $itemSet, 'children' => []];
            }, $children);
            $itemSetsTreeNode['children'] = $childrenNodes;
            $this->fetchItemSetsTreeChildren($itemSetsTreeNode['children'], $currentDepth + 1, $maxDepth, $options);
        }
    }
}
