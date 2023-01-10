<?php

namespace ItemSetsTree\Test\Service;

use Omeka\Test\AbstractHttpControllerTestCase;
use Omeka\Api\Representation\ItemSetRepresentation;

class ItemSetsTreeTest extends AbstractHttpControllerTestCase
{
    protected $itemSet1;
    protected $itemSet2;
    protected $itemSet3;
    protected $itemSet4;
    protected $site;

    public function setUp(): void
    {
        parent::setUp();

        $this->loginAsAdmin();
        $this->itemSet1 = $this->createItemSet('Item set 1');
        $this->itemSet2 = $this->createItemSet('Item set 2');
        $this->itemSet3 = $this->createItemSet('Item set 3');
        $this->itemSet4 = $this->createItemSet('Item set 4');
        $this->site = $this->createSite('Default', 'default', [$this->itemSet2, $this->itemSet3]);
    }

    public function tearDown(): void
    {
        $services = $this->getServiceLocator();
        $api = $services->get('Omeka\ApiManager');
        $itemSetsTree = $services->get('ItemSetsTree');
        $itemSetsTree->replaceTree([]);
        $api->delete('sites', $this->site->id());
        $api->delete('item_sets', $this->itemSet1->id());
        $api->delete('item_sets', $this->itemSet2->id());
        $api->delete('item_sets', $this->itemSet3->id());
        $api->delete('item_sets', $this->itemSet4->id());
    }

    public function testGetRootItemSets()
    {
        $services = $this->getServiceLocator();
        $itemSetsTree = $services->get('ItemSetsTree');
        $this->assertCount(4, $itemSetsTree->getRootItemSets());
    }

    public function testGetItemSetsTree()
    {
        $services = $this->getServiceLocator();
        $api = $services->get('Omeka\ApiManager');
        $itemSetsTree = $services->get('ItemSetsTree');
        $settings = $services->get('Omeka\Settings');

        $itemSetsTree->replaceTree([
            [
                'item-set-id' => $this->itemSet3->id(),
                'children' => [
                    [
                        'item-set-id' => $this->itemSet2->id(),
                    ],
                    [
                        'item-set-id' => $this->itemSet1->id(),
                    ],
                    [
                        'item-set-id' => $this->itemSet4->id(),
                    ],
                ],
            ],
        ]);

        $settings->set('itemsetstree_sorting_method', 'none');

        $tree = $itemSetsTree->getItemSetsTree();

        $this->assertIsArray($tree);
        $this->assertCount(1, $tree);
        $this->assertIsArray($tree[0]);

        $this->assertArrayHasKey('itemSet', $tree[0]);
        $this->assertInstanceOf(ItemSetRepresentation::class, $tree[0]['itemSet']);
        $this->assertEquals($this->itemSet3->id(), $tree[0]['itemSet']->id());

        $this->assertArrayHasKey('children', $tree[0]);
        $this->assertIsArray($tree[0]['children']);
        $this->assertCount(3, $tree[0]['children']);

        $this->assertArrayHasKey('itemSet', $tree[0]['children'][0]);
        $this->assertInstanceOf(ItemSetRepresentation::class, $tree[0]['children'][0]['itemSet']);
        $this->assertEquals($this->itemSet2->id(), $tree[0]['children'][0]['itemSet']->id());

        $this->assertArrayHasKey('itemSet', $tree[0]['children'][1]);
        $this->assertInstanceOf(ItemSetRepresentation::class, $tree[0]['children'][1]['itemSet']);
        $this->assertEquals($this->itemSet1->id(), $tree[0]['children'][1]['itemSet']->id());

        $this->assertArrayHasKey('itemSet', $tree[0]['children'][2]);
        $this->assertInstanceOf(ItemSetRepresentation::class, $tree[0]['children'][2]['itemSet']);
        $this->assertEquals($this->itemSet4->id(), $tree[0]['children'][2]['itemSet']->id());
    }

    public function testGetItemSetsTreeBySite()
    {
        $services = $this->getServiceLocator();
        $api = $services->get('Omeka\ApiManager');
        $itemSetsTree = $services->get('ItemSetsTree');
        $settings = $services->get('Omeka\Settings');
        $siteSettings = $services->get('Omeka\Settings\Site');

        $itemSetsTree->replaceTree([
            [
                'item-set-id' => $this->itemSet1->id(),
                'children' => [
                    [
                        'item-set-id' => $this->itemSet2->id(),
                        'children' => [
                            [
                                'item-set-id' => $this->itemSet3->id(),
                                'children' => [
                                    [
                                        'item-set-id' => $this->itemSet4->id(),
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $siteSettings->setTargetId($this->site->id());
        $siteSettings->set('itemsetstree_display', 'selected');

        $tree = $itemSetsTree->getItemSetsTree(null, ['site_id' => $this->site->id()]);

        $this->assertIsArray($tree);
        $this->assertCount(1, $tree);
        $this->assertIsArray($tree[0]);

        $this->assertArrayHasKey('itemSet', $tree[0]);
        $this->assertInstanceOf(ItemSetRepresentation::class, $tree[0]['itemSet']);
        $this->assertEquals($this->itemSet2->id(), $tree[0]['itemSet']->id());

        $this->assertArrayHasKey('children', $tree[0]);
        $this->assertIsArray($tree[0]['children']);
        $this->assertCount(1, $tree[0]['children']);

        $this->assertArrayHasKey('itemSet', $tree[0]['children'][0]);
        $this->assertInstanceOf(ItemSetRepresentation::class, $tree[0]['children'][0]['itemSet']);
        $this->assertEquals($this->itemSet3->id(), $tree[0]['children'][0]['itemSet']->id());
    }

    public function testGetItemSetsTreeMaxDepth()
    {
        $services = $this->getServiceLocator();
        $api = $services->get('Omeka\ApiManager');
        $itemSetsTree = $services->get('ItemSetsTree');
        $settings = $services->get('Omeka\Settings');
        $siteSettings = $services->get('Omeka\Settings\Site');

        $this->loginAsAdmin();
        $itemSetsTree->replaceTree([
            [
                'item-set-id' => $this->itemSet1->id(),
                'children' => [
                    [
                        'item-set-id' => $this->itemSet2->id(),
                        'children' => [
                            [
                                'item-set-id' => $this->itemSet3->id(),
                                'children' => [
                                    [
                                        'item-set-id' => $this->itemSet4->id(),
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $tree = $itemSetsTree->getItemSetsTree(2);

        $this->assertIsArray($tree);
        $this->assertCount(1, $tree);
        $this->assertIsArray($tree[0]);

        $this->assertArrayHasKey('itemSet', $tree[0]);
        $this->assertInstanceOf(ItemSetRepresentation::class, $tree[0]['itemSet']);
        $this->assertEquals($this->itemSet1->id(), $tree[0]['itemSet']->id());

        $this->assertArrayHasKey('children', $tree[0]);
        $this->assertIsArray($tree[0]['children']);
        $this->assertCount(1, $tree[0]['children']);

        $this->assertArrayHasKey('itemSet', $tree[0]['children'][0]);
        $this->assertInstanceOf(ItemSetRepresentation::class, $tree[0]['children'][0]['itemSet']);
        $this->assertEquals($this->itemSet2->id(), $tree[0]['children'][0]['itemSet']->id());

        $this->assertArrayHasKey('children', $tree[0]['children'][0]);
        $this->assertIsArray($tree[0]['children'][0]['children']);
        $this->assertCount(0, $tree[0]['children'][0]['children']);
    }

    protected function getServiceLocator()
    {
        return $this->getApplication()->getServiceManager();
    }

    protected function createItemSet(string $title)
    {
        $services = $this->getServiceLocator();
        $api = $services->get('Omeka\ApiManager');

        $response = $api->create('item_sets', [
            'dcterms:title' => [
                [
                    'property_id' => $this->getProperty('dcterms:title')->id(),
                    'type' => 'literal',
                    'is_public' => '1',
                    '@value' => $title,
                ],
            ],
        ]);

        return $response->getContent();
    }

    protected function createSite(string $title, string $slug, array $itemSets)
    {
        $services = $this->getServiceLocator();
        $api = $services->get('Omeka\ApiManager');

        $response = $api->create('sites', [
            'o:title' => $title,
            'o:slug' => $slug,
            'o:theme' => 'default',
            'o:site_item_set' => array_map(function ($itemSet) {
                return [
                    'o:item_set' => [
                        'o:id' => $itemSet->id(),
                    ],
                ];
            }, $itemSets),
        ]);

        return $response->getContent();
    }

    protected function getProperty($term)
    {
        $services = $this->getServiceLocator();
        $api = $services->get('Omeka\ApiManager');

        $properties = $api->search('properties', ['term' => $term])->getContent();

        return reset($properties);
    }

    protected function login($email, $password)
    {
        $serviceLocator = $this->getServiceLocator();
        $auth = $serviceLocator->get('Omeka\AuthenticationService');
        $adapter = $auth->getAdapter();
        $adapter->setIdentity($email);
        $adapter->setCredential($password);
        return $auth->authenticate();
    }

    protected function loginAsAdmin()
    {
        $this->login('admin@example.com', 'root');
    }
}
