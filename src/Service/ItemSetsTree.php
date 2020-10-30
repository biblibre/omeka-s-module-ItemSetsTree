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
use Omeka\Api\Adapter\Manager as ApiAdapterManager;
use Omeka\Api\Manager as ApiManager;
use Omeka\Api\Representation\ItemSetRepresentation;

class ItemSetsTree
{
    protected $api;
    protected $em;
    protected $apiAdapters;

    public function __construct(ApiManager $api, EntityManager $em, ApiAdapterManager $apiAdapters)
    {
        $this->api = $api;
        $this->em = $em;
        $this->apiAdapters = $apiAdapters;
    }

    public function getRootItemSets()
    {
        $itemSetAdapter = $this->apiAdapters->get('item_sets');
        $subqb = $this->em->createQueryBuilder();
        $subqb->select('IDENTITY(edge.itemSet)')
              ->from('ItemSetsTree\Entity\ItemSetsTreeEdge', 'edge');

        $qb = $this->em->createQueryBuilder();
        $qb->select('itemset')
            ->from('Omeka\Entity\ItemSet', 'itemset')
            ->where($qb->expr()->notIn('itemset.id', $subqb->getDQL()));

        $qb->addOrderBy('itemset.title');
        $qb->groupBy('itemset.id');

        $query = $qb->getQuery();
        $itemSets = $query->getResult();

        $itemSetsRepresentations = array_map(function ($itemSet) use ($itemSetAdapter) {
            return new ItemSetRepresentation($itemSet, $itemSetAdapter);
        }, $itemSets);

        return $itemSetsRepresentations;
    }

    public function getItemSetsTree(int $maxDepth = null)
    {
        $rootItemSets = $this->getRootItemSets();

        $itemSetsTree = [];
        foreach ($rootItemSets as $itemSet) {
            $itemSetsTree[] = [
                'itemSet' => $itemSet,
                'children' => [],
            ];
        }

        $currentDepth = 2;
        $this->fetchItemSetsTreeChildren($itemSetsTree, $currentDepth, $maxDepth);

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

    public function getChildren(ItemSetRepresentation $itemSet)
    {
        $itemSetAdapter = $this->apiAdapters->get('item_sets');
        $subqb = $this->em->createQueryBuilder();
        $subqb->select('IDENTITY(edge.itemSet)')
            ->from('ItemSetsTree\Entity\ItemSetsTreeEdge', 'edge')
            ->where('edge.parentItemSet = :itemSetId');

        $qb = $this->em->createQueryBuilder();
        $qb->select('itemset')
            ->from('Omeka\Entity\ItemSet', 'itemset')
            ->where($qb->expr()->in('itemset.id', $subqb->getDQL()))
            ->setParameter('itemSetId', $itemSet->id());

        $qb->addOrderBy('itemset.title');
        $qb->groupBy('itemset.id');

        $query = $qb->getQuery();
        $itemSets = $query->getResult();

        $itemSetsRepresentations = array_map(function ($itemSet) use ($itemSetAdapter) {
            return new ItemSetRepresentation($itemSet, $itemSetAdapter);
        }, $itemSets);

        return $itemSetsRepresentations;
    }

    public function getDescendants(ItemSetRepresentation $itemSet)
    {
        $descendants = [];
        $children = $this->getChildren($itemSet);
        while ($child = array_shift($children)) {
            $descendants[] = $child;
            $children = array_merge($children, $this->getChildren($child));
        }

        return $descendants;
    }

    protected function fetchItemSetsTreeChildren(&$itemSetsTree, $currentDepth, $maxDepth = null)
    {
        if (isset($maxDepth) && $currentDepth > $maxDepth) {
            return;
        }

        foreach ($itemSetsTree as &$itemSetsTreeNode) {
            $children = $this->getChildren($itemSetsTreeNode['itemSet']);
            $childrenNodes = array_map(function ($itemSet) {
                return ['itemSet' => $itemSet, 'children' => []];
            }, $children);
            $itemSetsTreeNode['children'] = $childrenNodes;
            $this->fetchItemSetsTreeChildren($itemSetsTreeNode['children'], $currentDepth + 1, $maxDepth);
        }
    }

}
