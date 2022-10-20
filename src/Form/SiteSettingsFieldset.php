<?php

namespace ItemSetsTree\Form;

use Laminas\Form\Element\Select;
use Laminas\Form\Fieldset;

class SiteSettingsFieldset extends Fieldset
{
    public function init()
    {
        $this->setLabel('Item Sets Tree'); // @translate

        $this->add([
            'type' => Select::class,
            'name' => 'itemsetstree_display',
            'options' => [
                'label' => 'Display', // @translate
                'info' => 'Choose which item sets to display in the tree', // @translate
                'value_options' => [
                    'all' => 'Display all item sets without considering what is selected in the Resources tab', // @translate
                    'selected' => 'Display only item sets selected in the Resources tab', // @translate
                    'selected-and-descendants' => 'Display only item sets selected in the Resources tab and their descendants', // @translate
                ],
            ],
        ]);
    }
}
