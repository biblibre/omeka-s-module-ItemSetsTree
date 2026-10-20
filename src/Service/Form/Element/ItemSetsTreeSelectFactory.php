<?php

namespace ItemSetsTree\Service\Form\Element;

use ItemSetsTree\Form\Element\ItemSetsTreeSelect;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

class ItemSetsTreeSelectFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null)
    {
        $element = new ItemSetsTreeSelect(null, $options ?? []);
        $element->setItemSetsTree($container->get('ItemSetsTree'));

        return $element;
    }
}
