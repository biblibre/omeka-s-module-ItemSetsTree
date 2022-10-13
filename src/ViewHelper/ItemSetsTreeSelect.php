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

use ItemSetsTree\Service\ItemSetsTree;
use Laminas\Form\Element\Select;
use Laminas\Form\Factory;
use Laminas\ServiceManager\ServiceLocatorInterface;
use Laminas\View\Helper\AbstractHelper;

class ItemSetsTreeSelect extends AbstractHelper
{
    protected $formElementManager;
    protected $itemSetsTree;
    protected $itemSetId;

    public function __construct(ServiceLocatorInterface $formElementManager, ItemSetsTree $itemSetsTree)
    {
        $this->formElementManager = $formElementManager;
        $this->itemSetsTree = $itemSetsTree;
    }

    public function __invoke(array $spec = [], $itemSetId = null, int $maxDepth = null)
    {
        $this->itemSetId = $itemSetId;

        $spec['type'] = Select::class;
        if (!isset($spec['options']['empty_option'])) {
            $spec['options']['empty_option'] = 'Select item set'; // @translate
        }

        $itemSetsTree = $this->itemSetsTree->getItemSetsTree($maxDepth);
        $valueOptions = $this->getValueOptions($itemSetsTree);
        $spec['options']['value_options'] = $valueOptions;

        $factory = new Factory($this->formElementManager);
        $element = $factory->createElement($spec);
        if ($itemSetId) {
            $edges = $this->getView()->api()->search('item_sets_tree_edges', ['item_set_id' => $itemSetId])->getContent();
            $edge = reset($edges);
            if ($edge) {
                $parentItemSet = $edge->parentItemSet();
                if ($parentItemSet) {
                    $element->setValue($parentItemSet->id());
                }
            }
        }

        return $this->getView()->formSelect($element);
    }

    protected function getValueOptions($itemSetsTree, $depth = 0, $forceDisable = false)
    {
        $valueOptions = [];
        foreach ($itemSetsTree as $itemSetsTreeNode) {
            $itemSet = $itemSetsTreeNode['itemSet'];
            $disabled = $forceDisable || $itemSet->id() === $this->itemSetId;
            $valueOptions[] = [
                'value' => $itemSet->id(),
                'label' => str_repeat('‒', $depth) . ' ' . $itemSet->displayTitle(),
                'disabled' => $disabled,
            ];
            $valueOptions = array_merge($valueOptions, $this->getValueOptions($itemSetsTreeNode['children'], $depth + 1, $disabled));
        }

        return $valueOptions;
    }
}
