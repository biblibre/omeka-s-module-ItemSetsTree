<?php

namespace ItemSetsTree\Test\Service;

use Omeka\Test\AbstractHttpControllerTestCase;
use Omeka\Api\Representation\ItemSetRepresentation;

class ItemSetsTreeTest extends AbstractHttpControllerTestCase
{
    public function testGetRootItemSetsReturnsEmpty()
    {
        $services = $this->getServiceLocator();
        $itemSetsTree = $services->get('ItemSetsTree');
        $this->assertEmpty($itemSetsTree->getRootItemSets());
    }

    public function testGetItemSetsTree()
    {
        $services = $this->getServiceLocator();
        $api = $services->get('Omeka\ApiManager');
        $itemSetsTree = $services->get('ItemSetsTree');
        $settings = $services->get('Omeka\Settings');

        $this->loginAsAdmin();
        $itemSet1 = $this->createItemSet('Item set 1');
        $itemSet2 = $this->createItemSet('Item set 2');
        $itemSet3 = $this->createItemSet('Item set 3');
        $itemSetsTree->replaceTree([
            [
                'item-set-id' => $itemSet3->id(),
                'children' => [
                    [
                        'item-set-id' => $itemSet2->id(),
                    ],
                    [
                        'item-set-id' => $itemSet1->id(),
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
        $this->assertEquals($itemSet3->id(), $tree[0]['itemSet']->id());

        $this->assertArrayHasKey('children', $tree[0]);
        $this->assertIsArray($tree[0]['children']);
        $this->assertCount(2, $tree[0]['children']);

        $this->assertArrayHasKey('itemSet', $tree[0]['children'][0]);
        $this->assertInstanceOf(ItemSetRepresentation::class, $tree[0]['children'][0]['itemSet']);
        $this->assertEquals($itemSet2->id(), $tree[0]['children'][0]['itemSet']->id());

        $this->assertArrayHasKey('itemSet', $tree[0]['children'][1]);
        $this->assertInstanceOf(ItemSetRepresentation::class, $tree[0]['children'][1]['itemSet']);
        $this->assertEquals($itemSet1->id(), $tree[0]['children'][1]['itemSet']->id());
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
