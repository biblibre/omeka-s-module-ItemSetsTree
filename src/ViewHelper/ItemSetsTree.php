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

namespace ItemSetsTree\ViewHelper;

use ItemSetsTree\Service\ItemSetsTree as ItemSetsTreeService;
use Omeka\Api\Representation\ItemSetRepresentation;
use Zend\View\Helper\AbstractHelper;

class ItemSetsTree extends AbstractHelper
{
    protected $itemSetsTree;

    public function __construct(ItemSetsTreeService $itemSetsTree)
    {
        $this->itemSetsTree = $itemSetsTree;
    }

    public function getParent(ItemSetRepresentation $itemSet)
    {
        return $this->itemSetsTree->getParent($itemSet);
    }

    public function getAncestors(ItemSetRepresentation $itemSet)
    {
        return $this->itemSetsTree->getAncestors($itemSet);
    }
}
