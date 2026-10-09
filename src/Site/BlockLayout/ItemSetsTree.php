<?php

namespace ItemSetsTree\Site\BlockLayout;

use Laminas\View\Renderer\PhpRenderer;
use Laminas\Form\Element\Checkbox;
use Laminas\Form\Element\Text;
use Laminas\Form\FormElementManager;
use Omeka\Api\Representation\SitePageBlockRepresentation;
use Omeka\Api\Representation\SitePageRepresentation;
use Omeka\Api\Representation\SiteRepresentation;
use Omeka\Entity\SitePageBlock;
use Omeka\Site\BlockLayout\AbstractBlockLayout;
use Omeka\Stdlib\ErrorStore;

class ItemSetsTree extends AbstractBlockLayout
{
    public function __construct(protected FormElementManager $formElementManager)
    {
    }

    public function getLabel()
    {
        return 'Item Sets Tree'; // @translate
    }

    public function form(
        PhpRenderer $view,
        SiteRepresentation $site,
        ?SitePageRepresentation $page = null,
        ?SitePageBlockRepresentation $block = null
    ) {
        $defaults = [
            'heading' => '',
            'displayCount' => false,
            'displayDescription' => false,
            'linkEmpty' => true,
            'rootItemSetId' => '',
            'excludeRootItemSet' => false,
        ];

        $data = $block ? $block->data() + $defaults : $defaults;

        $form = $this->formElementManager->get('Laminas\Form\Form');
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
                'label' => 'Link to item sets without items', // @translate
                'info' => 'An item set without items is always displayed in the tree but is not clickable. If this parameter is checked, the link to the item set page will be available.', // @translate
            ],
            'attributes' => [
                'id' => 'item-sets-tree-link-empty',
            ],
        ]);
        $form->add([
            'name' => 'o:block[__blockIndex__][o:data][rootItemSetId]',
            'type' => 'ItemSetsTree\Form\Element\ItemSetsTreeSelect',
            'options' => [
                'label' => 'Subtree root', // @translate
                'info' => 'Show only this item set and its descendants.',
                'empty_option' => '',
            ],
        ]);
        $form->add([
            'name' => 'o:block[__blockIndex__][o:data][excludeRootItemSet]',
            'type' => 'Laminas\Form\Element\Checkbox',
            'options' => [
                'label' => 'Exclude subtree root', // @translate
                'info' => 'Exclude the item set selected above. Has no effect if "Limit to a subtree" is not set',
            ],
        ]);

        $form->setData([
            'o:block[__blockIndex__][o:data][heading]' => $data['heading'],
            'o:block[__blockIndex__][o:data][displayCount]' => $data['displayCount'],
            'o:block[__blockIndex__][o:data][displayDescription]' => $data['displayDescription'],
            'o:block[__blockIndex__][o:data][linkEmpty]' => $data['linkEmpty'],
            'o:block[__blockIndex__][o:data][rootItemSetId]' => $data['rootItemSetId'],
            'o:block[__blockIndex__][o:data][excludeRootItemSet]' => $data['excludeRootItemSet'],
        ]);

        return $view->partial('item-sets-tree/common/block-layout/item-sets-tree-form', ['form' => $form]);
    }

    public function onHydrate(SitePageBlock $block, ErrorStore $errorStore)
    {
        $data = $block->getData();

        if (is_numeric($data['rootItemSetId']) && $data['rootItemSetId'] > 0) {
            $data['rootItemSetId'] = (int) $data['rootItemSetId'];
        } else {
            $data['rootItemSetId'] = null;
        }

        $block->setData($data);
    }

    public function render(PhpRenderer $view, SitePageBlockRepresentation $block)
    {
        return $view->partial('common/block-layout/item-sets-tree', $block->data());
    }
}
