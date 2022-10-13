<?php
namespace ItemSetsTree\Form;

use Laminas\Form\Form;
use Laminas\Form\Element\Select;

class ConfigForm extends Form
{
    public function init()
    {
        $this->add([
            'type' => 'checkbox',
            'name' => 'item_sets_include_descendants',
            'options' => [
                'label' => 'Item sets include descendants', // @translate
                'info' => 'If enabled, displaying items of an item set will also display items of descendant item sets', // @translate
                'use_hidden_element' => true,
                'checked_value' => '1',
                'unchecked_value' => '0',
            ],
            'attributes' => [
                'id' => 'item-sets-include-descendants',
            ],
        ]);

        $this->add([
            'type' => Select::class,
            'name' => 'sorting_method',
            'options' => [
                'label' => 'Sorting method', // @translate
                'info' => 'Method used to sort item sets of the same level', // @translate
                'value_options' => [
                    'none' => 'Do not sort', // @translate
                    'title' => 'Sort by title', //@translate
                ],
            ],
        ]);
    }
}
