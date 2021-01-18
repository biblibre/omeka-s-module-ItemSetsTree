<?php declare(strict_types=1);

namespace ItemSetsTree\Form;

use Laminas\Form\Element;
use Laminas\Form\Fieldset;

class ItemSetsTreeFieldset extends Fieldset
{
    public function init(): void
    {
        $this
            ->add([
                'name' => 'o:block[__blockIndex__][o:data][heading]',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'Block title', // @translate
                    'info' => 'Heading for the block, if any.', // @translate
                ],
                'attributes' => [
                    'id' => 'item-sets-tree-heading',
                ],
            ]);

        if (class_exists('BlockPlus\Form\Element\TemplateSelect')) {
            $this
            ->add([
                'name' => 'o:block[__blockIndex__][o:data][template]',
                'type' => \BlockPlus\Form\Element\TemplateSelect::class,
                'options' => [
                    'label' => 'Template to display', // @translate
                    'info' => 'Templates are in folder "common/block-layout" of the theme and should start with "item-sets-tree".', // @translate
                    'template' => 'common/block-layout/item-sets-tree',
                ],
                'attributes' => [
                    'class' => 'chosen-select',
                ],
            ]);
        }
    }
}
