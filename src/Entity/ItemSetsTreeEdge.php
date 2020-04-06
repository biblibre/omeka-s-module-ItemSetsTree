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

namespace ItemSetsTree\Entity;

use Omeka\Entity\AbstractEntity;
use Omeka\Entity\ItemSet;

/**
 * @Entity
 */
class ItemSetsTreeEdge extends AbstractEntity
{
    /**
     * @Id
     * @Column(type="integer")
     * @GeneratedValue
     */
    protected $id;

    /**
     * @OneToOne(targetEntity="Omeka\Entity\ItemSet")
     * @JoinColumn(onDelete="cascade", nullable=false)
     */
    protected $itemSet;

    /**
     * @ManyToOne(targetEntity="Omeka\Entity\ItemSet")
     * @JoinColumn(onDelete="cascade", nullable=false)
     */
    protected $parentItemSet;

    public function getId()
    {
        return $this->id;
    }

    public function setItemSet(ItemSet $itemSet)
    {
        $this->itemSet = $itemSet;
    }

    public function getItemSet()
    {
        return $this->itemSet;
    }

    public function setParentItemSet(ItemSet $itemSet)
    {
        $this->parentItemSet = $itemSet;
    }

    public function getParentItemSet()
    {
        return $this->parentItemSet;
    }
}
