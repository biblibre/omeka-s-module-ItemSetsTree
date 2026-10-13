<?php

namespace ItemSetsTree\Form\Element;

use ItemSetsTree\Service\ItemSetsTree;
use Laminas\Form\Element\Select;

class ItemSetsTreeSelect extends Select
{
    protected ItemSetsTree $itemSetsTree;

    protected ?int $disableSubtreeItemSetId = null;

    public function getValueOptions(): array
    {
        if (!$this->valueOptions) {
            parent::setValueOptions($this->buildValueOptions());
        }

        return $this->valueOptions;
    }

    public function setValueOptions(array $options)
    {
        parent::setValueOptions($this->buildValueOptions());
    }

    protected function buildValueOptions(): array
    {
        $maxDepth = $this->getOption('max_depth');
        $siteId = $this->getOption('site_id');
        $this->disableSubtreeItemSetId = (int) $this->getOption('disable_subtree_item_set_id');

        $tree = $this->itemSetsTree->getTree(maxDepth: $maxDepth, siteId: $siteId);

        return $this->buildValueOptionsRecursive($tree);
    }

    protected function buildValueOptionsRecursive($itemSetsTree, $depth = 0, $forceDisable = false): array
    {
        $valueOptions = [];
        foreach ($itemSetsTree as $itemSetsTreeNode) {
            $itemSet = $itemSetsTreeNode['itemSet'];
            $disabled = $forceDisable || $itemSet->id() === $this->disableSubtreeItemSetId;
            $valueOptions[] = [
                'value' => $itemSet->id(),
                'label' => str_repeat('‒', $depth) . ' ' . $itemSet->displayTitle(),
                'disabled' => $disabled,
            ];
            $valueOptions = array_merge($valueOptions, $this->buildValueOptionsRecursive($itemSetsTreeNode['children'], $depth + 1, $disabled));
        }

        return $valueOptions;
    }

    public function setItemSetsTree(ItemSetsTree $itemSetsTree): void
    {
        $this->itemSetsTree = $itemSetsTree;
    }
}
