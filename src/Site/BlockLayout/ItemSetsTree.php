<?php

namespace ItemSetsTree\Site\BlockLayout;

use Laminas\View\Renderer\PhpRenderer;
use Laminas\Form\Element\Checkbox;
use Laminas\Form\Element\Text;
use Laminas\Form\Form;
use Omeka\Api\Representation\SitePageBlockRepresentation;
use Omeka\Api\Representation\SitePageRepresentation;
use Omeka\Api\Representation\SiteRepresentation;
use Omeka\Site\BlockLayout\AbstractBlockLayout;

class ItemSetsTree extends AbstractBlockLayout
{
    public function getLabel()
    {
        return 'Item Sets Tree'; // @translate
    }

    public function form(
        PhpRenderer $view,
        SiteRepresentation $site,
        SitePageRepresentation $page = null,
        SitePageBlockRepresentation $block = null
    ) {
        $defaults = [
            'heading' => '',
            'displayCount' => false,
            'displayDescription' => false,
            'linkEmpty' => true,
        ];

        $data = $block ? ($block->data() ?? []) + $defaults : $defaults;

        $form = new Form();
        $form->add([
            'name' => 'o:block[__blockIndex__][o:data][heading]',
            'type' => Text::class,
            'options' => [
                'label' => 'Block title', // @translate
            ],
            'attributes' => [
                'id' => 'item-sets-tree-heading',
            ],
        ]);
        $form->add([
            'name' => 'o:block[__blockIndex__][o:data][displayCount]',
            'type' => Checkbox::class,
            'options' => [
                'label' => 'Display items count', // @translate
            ],
            'attributes' => [
                'id' => 'item-sets-tree-display-count',
            ],
        ]);
        $form->add([
            'name' => 'o:block[__blockIndex__][o:data][displayDescription]',
            'type' => Checkbox::class,
            'options' => [
                'label' => 'Display description', // @translate
            ],
            'attributes' => [
                'id' => 'item-sets-tree-display-description',
            ],
        ]);
        $form->add([
            'name' => 'o:block[__blockIndex__][o:data][linkEmpty]',
            'type' => Checkbox::class,
            'options' => [
                'label' => 'Link for empty item sets', // @translate
            ],
            'attributes' => [
                'id' => 'item-sets-tree-link-empty',
            ],
        ]);

        $form->setData([
            'o:block[__blockIndex__][o:data][heading]' => $data['heading'],
            'o:block[__blockIndex__][o:data][displayCount]' => $data['displayCount'],
            'o:block[__blockIndex__][o:data][displayDescription]' => $data['displayDescription'],
            'o:block[__blockIndex__][o:data][linkEmpty]' => $data['linkEmpty'],
        ]);

        return $view->formCollection($form);
    }

    public function render(PhpRenderer $view, SitePageBlockRepresentation $block)
    {
        return $view->partial('common/block-layout/item-sets-tree', $block->data());
    }
}
