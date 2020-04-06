<?php

namespace ItemSetsTree;

return [
    'api_adapters' => [
        'invokables' => [
            'item_sets_tree_edges' => Api\Adapter\ItemSetsTreeEdgeAdapter::class,
        ]
    ],
    'controller_plugins' => [
        'factories' => [
            'itemSetsTree' => Service\ControllerPlugin\ItemSetsTreeFactory::class,
        ],
    ],
    'controllers' => [
        'invokables' => [
            'ItemSetsTree\Controller\Admin\Index' => Controller\Admin\IndexController::class,
            'ItemSetsTree\Controller\Site\Index' => Controller\Site\IndexController::class,
        ],
    ],
    'entity_manager' => [
        'mapping_classes_paths' => [
            dirname(__DIR__) . '/src/Entity',
        ],
        'proxy_paths' => [
            dirname(__DIR__) . '/data/doctrine-proxies',
        ],
    ],
    'navigation' => [
        'AdminModule' => [
            [
                'label' => 'Item Sets Tree', // @translate
                'class' => 'item-sets-tree',
                'route' => 'admin/item-sets-tree',
            ],
        ],
    ],
    'navigation_links' => [
        'invokables' => [
            'itemSetsTree' => Site\Navigation\Link\ItemSetsTree::class,
        ],
    ],
    'router' => [
        'routes' => [
            'admin' => [
                'child_routes' => [
                    'item-sets-tree' => [
                        'type' => 'Segment',
                        'options' => [
                            'route' => '/item-sets-tree',
                            'defaults' => [
                                '__NAMESPACE__' => 'ItemSetsTree\Controller',
                                'controller' => 'Admin\Index',
                                'action' => 'index',
                            ],
                        ],
                    ],
                ],
            ],
            'site' => [
                'child_routes' => [
                    'item-sets-tree' => [
                        'type' => 'Segment',
                        'options' => [
                            'route' => '/item-sets-tree',
                            'defaults' => [
                                '__NAMESPACE__' => 'ItemSetsTree\Controller',
                                'controller' => 'Site\Index',
                                'action' => 'index',
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
    'service_manager' => [
        'factories' => [
            'ItemSetsTree' => Service\ItemSetsTreeFactory::class,
        ],
    ],
    'translator' => [
        'translation_file_patterns' => [
            [
                'type' => 'gettext',
                'base_dir' => dirname(__DIR__) . '/language',
                'pattern' => '%s.mo',
                'text_domain' => null,
            ],
        ],
    ],
    'view_helpers' => [
        'factories' => [
            'itemSetsTreeSelect' => Service\ViewHelper\ItemSetsTreeSelectFactory::class,
        ],
    ],
    'view_manager' => [
        'template_path_stack' => [
            dirname(__DIR__) . DIRECTORY_SEPARATOR . 'view',
        ],
    ],
];
