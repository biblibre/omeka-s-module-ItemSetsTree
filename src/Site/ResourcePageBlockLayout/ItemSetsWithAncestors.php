<?php
namespace ItemSetsTree\Site\ResourcePageBlockLayout;

use Omeka\Api\Representation\AbstractResourceEntityRepresentation;
use Omeka\Site\ResourcePageBlockLayout\ResourcePageBlockLayoutInterface;
use Laminas\View\Renderer\PhpRenderer;

class ItemSetsWithAncestors implements ResourcePageBlockLayoutInterface
{
    public function getLabel() : string
    {
        return 'Item sets (with ancestors)'; // @translate
    }

    public function getCompatibleResourceNames() : array
    {
        return ['items'];
    }

    public function render(PhpRenderer $view, AbstractResourceEntityRepresentation $resource) : string
    {
        if ($view->siteSetting('exclude_resources_not_in_site')) {
            // Get only those item sets that are assigned to the current site.
            $siteItemSetIds = [];
            foreach ($view->site->siteItemSets() as $siteItemSet) {
                $siteItemSetIds[] = $siteItemSet->itemSet()->id();
            }
            $itemSets = [];
            foreach ($resource->itemSets() as $itemSet) {
                if (in_array($itemSet->id(), $siteItemSetIds)) {
                    $itemSets[] = $itemSet;
                }
            }
        } else {
            $itemSets = $resource->itemSets();
        }

        return $view->partial('item-sets-tree/common/resource-page-block-layout/item-sets-with-ancestors', ['itemSets' => $itemSets]);
    }
}
