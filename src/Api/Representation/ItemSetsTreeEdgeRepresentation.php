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

namespace ItemSetsTree\Api\Representation;

use Omeka\Api\Representation\AbstractEntityRepresentation;

class ItemSetsTreeEdgeRepresentation extends AbstractEntityRepresentation
{
    public function getJsonLdType()
    {
        return 'o:ItemSetsTreeEdge';
    }

    public function getJsonLd()
    {
        $parentItemSet = $this->parentItemSet();

        return [
            'o:item_set' => $this->itemSet()->getReference(),
            'o:parent_item_set' => $parentItemSet ? $parentItemSet->getReference() : null,
        ];
    }

    public function itemSet()
    {
        return $this->getAdapter('item_sets')
            ->getRepresentation($this->resource->getItemSet());
    }

    public function parentItemSet()
    {
        return $this->getAdapter('item_sets')
            ->getRepresentation($this->resource->getParentItemSet());
    }

    public function rank()
    {
        return $this->resource->getRank();
    }
}
