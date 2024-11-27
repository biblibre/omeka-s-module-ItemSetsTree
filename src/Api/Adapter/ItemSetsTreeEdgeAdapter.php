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

namespace ItemSetsTree\Api\Adapter;

use Doctrine\ORM\QueryBuilder;
use ItemSetsTree\Api\Representation\ItemSetsTreeEdgeRepresentation;
use ItemSetsTree\Entity\ItemSetsTreeEdge;
use Omeka\Api\Adapter\AbstractEntityAdapter;
use Omeka\Api\Request;
use Omeka\Entity\EntityInterface;
use Omeka\Stdlib\ErrorStore;

class ItemSetsTreeEdgeAdapter extends AbstractEntityAdapter
{
    public function getResourceName()
    {
        return 'item_sets_tree_edges';
    }

    public function getEntityClass()
    {
        return ItemSetsTreeEdge::class;
    }

    public function getRepresentationClass()
    {
        return ItemSetsTreeEdgeRepresentation::class;
    }

    public function hydrate(Request $request, EntityInterface $entity, ErrorStore $errorStore)
    {
        // Refresh the reference to avoid issues with doctrine.
        if ($this->shouldHydrate($request, 'o:item_set')) {
            $itemSet = $request->getValue('o:item_set');
            $itemSet = $itemSet
                ? $this->getEntityManager()->getReference(\Omeka\Entity\ItemSet::class, $itemSet->getId())
                : null;
            $entity->setItemSet($itemSet);
        }

        if ($this->shouldHydrate($request, 'o:parent_item_set')) {
            $itemSet = $request->getValue('o:parent_item_set');
            $itemSet = $itemSet
                ? $this->getEntityManager()->getReference(\Omeka\Entity\ItemSet::class, $itemSet->getId())
                : null;
            $entity->setParentItemSet($itemSet);
        }
    }

    public function buildQuery(QueryBuilder $qb, array $query)
    {
        if (isset($query['item_set_id'])) {
            $itemSetAlias = $this->createAlias();
            $qb->innerJoin('omeka_root.itemSet', $itemSetAlias);
            $qb->andWhere(
                $qb->expr()->eq(
                    "$itemSetAlias.id",
                    $this->createNamedParameter($qb, $query['item_set_id'])
                )
            );
        }

        if (isset($query['parent_item_set_id'])) {
            $parentItemSetAlias = $this->createAlias();
            $qb->innerJoin('omeka_root.parentItemSet', $parentItemSetAlias);
            $qb->andWhere(
                $qb->expr()->eq(
                    "$parentItemSetAlias.id",
                    $this->createNamedParameter($qb, $query['parent_item_set_id'])
                )
            );
        }
    }
}
