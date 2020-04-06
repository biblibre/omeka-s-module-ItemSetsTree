<?php

/*
 * Copyright 2020 BibLibre
 *
 * This file is part of ItemSetsTree.
 *
 * ItemSetsTree is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * ItemSetsTree is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with ItemSetsTree.  If not, see <https://www.gnu.org/licenses/>.
 */

namespace ItemSetsTree;

use Omeka\Module\AbstractModule;
use Omeka\Permissions\Acl;
use Zend\EventManager\Event;
use Zend\EventManager\SharedEventManagerInterface;
use Zend\Mvc\MvcEvent;
use Zend\ServiceManager\ServiceLocatorInterface;

class Module extends AbstractModule
{
    public function getConfig()
    {
        return include __DIR__ . '/config/module.config.php';
    }

    public function install(ServiceLocatorInterface $services)
    {
        $connection = $services->get('Omeka\Connection');
        $connection->exec('CREATE TABLE item_sets_tree_edge (id INT AUTO_INCREMENT NOT NULL, item_set_id INT NOT NULL, parent_item_set_id INT NOT NULL, UNIQUE INDEX UNIQ_619BDFA3960278D7 (item_set_id), INDEX IDX_619BDFA3FD2E6AA7 (parent_item_set_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $connection->exec('ALTER TABLE item_sets_tree_edge ADD CONSTRAINT FK_619BDFA3960278D7 FOREIGN KEY (item_set_id) REFERENCES item_set (id) ON DELETE CASCADE');
        $connection->exec('ALTER TABLE item_sets_tree_edge ADD CONSTRAINT FK_619BDFA3FD2E6AA7 FOREIGN KEY (parent_item_set_id) REFERENCES item_set (id) ON DELETE CASCADE');
    }

    public function uninstall(ServiceLocatorInterface $services)
    {
        $connection = $services->get('Omeka\Connection');
        $connection->exec('DROP TABLE item_sets_tree_edge');
    }

    public function onBootstrap(MvcEvent $event)
    {
        parent::onBootstrap($event);

        $acl = $this->getServiceLocator()->get('Omeka\Acl');
        $acl->allow(
            null,
            'ItemSetsTree\Controller\Site\Index'
        );
        $acl->allow(
            null,
            'ItemSetsTree\Api\Adapter\ItemSetsTreeEdgeAdapter',
            'search'
        );
        $acl->allow(
            [Acl::ROLE_EDITOR, Acl::ROLE_REVIEWER, Acl::ROLE_AUTHOR, Acl::ROLE_RESEARCHER],
            'ItemSetsTree\Controller\Admin\Index'
        );
        $acl->allow(
            [Acl::ROLE_EDITOR, Acl::ROLE_REVIEWER, Acl::ROLE_AUTHOR],
            [
                'ItemSetsTree\Api\Adapter\ItemSetsTreeEdgeAdapter',
                'ItemSetsTree\Entity\ItemSetsTreeEdge',
            ],
            ['create', 'update', 'delete']
        );
    }

    public function attachListeners(SharedEventManagerInterface $sharedEventManager)
    {
        $sharedEventManager->attach('*', 'view.layout', function (Event $event) {
            $view = $event->getTarget();
            $view->headLink()->appendStylesheet($view->assetUrl('css/item-sets-tree.css', 'ItemSetsTree'));
        });

        foreach (['create', 'update'] as $operation) {
            $sharedEventManager->attach(
                'Omeka\Api\Adapter\ItemSetAdapter',
                "api.$operation.post",
                [$this, 'onItemSetSave']
            );
        }

        foreach (['add', 'edit'] as $action) {
            $sharedEventManager->attach(
                'Omeka\Controller\Admin\ItemSet',
                "view.$action.section_nav",
                [$this, 'onItemSetSectionNav']
            );
            $sharedEventManager->attach(
                'Omeka\Controller\Admin\ItemSet',
                "view.$action.form.after",
                [$this, 'onItemSetForm']
            );
        }
    }

    public function onItemSetSave(Event $event)
    {
        $services = $this->getServiceLocator();
        $api = $services->get('Omeka\ApiManager');
        $entityManager = $services->get('Omeka\EntityManager');

        $data = $event->getParam('request')->getContent();
        if ($data['item-sets-tree-parent-id']) {
            $parentItemSet = $entityManager->find('Omeka\Entity\ItemSet', $data['item-sets-tree-parent-id']);
        }

        $itemSet = $event->getParam('response')->getContent();
        $itemSetsTreeEdges = $api->search('item_sets_tree_edges', ['item_set_id' => $itemSet->getId()])->getContent();
        if (!empty($itemSetsTreeEdges)) {
            $itemSetsTreeEdge = reset($itemSetsTreeEdges);
            if ($parentItemSet) {
                $api->update('item_sets_tree_edges', $itemSetsTreeEdge->id(), ['o:item_set' => $itemSet, 'o:parent_item_set' => $parentItemSet]);
            } else {
                $api->delete('item_sets_tree_edges', $itemSetsTreeEdge->id());
            }
        } else {
            if ($parentItemSet) {
                $api->create('item_sets_tree_edges', ['o:item_set' => $itemSet, 'o:parent_item_set' => $parentItemSet]);
            }
        }
    }

    public function onItemSetSectionNav(Event $event)
    {
        $view = $event->getTarget();
        $sectionNav = $event->getParam('section_nav');

        $sectionNav['item-sets-tree'] = $view->translate('Item Sets Tree');

        $event->setParam('section_nav', $sectionNav);
    }

    public function onItemSetForm(Event $event)
    {
        $view = $event->getTarget();
        $form = $event->getParam('form');
        $itemSet = $view->viewModel()->getCurrent()->getVariable('itemSet');
        echo $view->partial('item-sets-tree/item-set-fieldset', ['form' => $form, 'itemSet' => $itemSet]);
    }

    public function onItemSetFormAddElements(Event $event)
    {
        $form = $event->getTarget();
        $form->add([
            'name' => 'item-sets-tree-parent-id',
            'type' => 'Text',
            'options' => [
                'label' => 'Parent item set', // @translate
            ],
        ]);
    }
}
