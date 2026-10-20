<?php

namespace ItemSetsTree\Service\Site\BlockLayout;

use ItemSetsTree\Site\BlockLayout\ItemSetsTree;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

class ItemSetsTreeFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null)
    {
        $formElementManager = $container->get('FormElementManager');

        return new ItemSetsTree($formElementManager);
    }
}
