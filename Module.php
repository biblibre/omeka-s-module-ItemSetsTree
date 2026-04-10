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

use Composer\Semver\Comparator;
use ItemSetsTree\Form\ConfigForm;
use ItemSetsTree\Form\SiteSettingsFieldset;
use Omeka\Module\AbstractModule;
use Omeka\Permissions\Acl;
use Laminas\EventManager\Event;
use Laminas\EventManager\SharedEventManagerInterface;
use Laminas\Mvc\Controller\AbstractController;
use Laminas\Mvc\MvcEvent;
use Laminas\ServiceManager\ServiceLocatorInterface;
use Laminas\View\Renderer\PhpRenderer;
use Laminas\Validator\InArray;

class Module extends AbstractModule
{
    public function getConfig()
    {
        return include __DIR__ . '/config/module.config.php';
    }

    public function install(ServiceLocatorInterface $services)
    {
        $connection = $services->get('Omeka\Connection');
        $connection->exec('CREATE TABLE item_sets_tree_edge (id INT AUTO_INCREMENT NOT NULL, item_set_id INT NOT NULL, parent_item_set_id INT DEFAULT NULL, `rank` INT NOT NULL, UNIQUE INDEX UNIQ_619BDFA3960278D7 (item_set_id), INDEX IDX_619BDFA3FD2E6AA7 (parent_item_set_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $connection->exec('ALTER TABLE item_sets_tree_edge ADD CONSTRAINT FK_619BDFA3960278D7 FOREIGN KEY (item_set_id) REFERENCES item_set (id) ON DELETE CASCADE');
        $connection->exec('ALTER TABLE item_sets_tree_edge ADD CONSTRAINT FK_619BDFA3FD2E6AA7 FOREIGN KEY (parent_item_set_id) REFERENCES item_set (id) ON DELETE CASCADE');
    }

    public function upgrade($oldVersion, $newVersion, ServiceLocatorInterface $services)
    {
        $connection = $services->get('Omeka\Connection');
        if (Comparator::lessThan($oldVersion, '0.5.0')) {
            $connection->exec('ALTER TABLE item_sets_tree_edge MODIFY COLUMN parent_item_set_id INT DEFAULT NULL');
            $connection->exec('ALTER TABLE item_sets_tree_edge ADD COLUMN `rank` INT NOT NULL AFTER parent_item_set_id');
            $connection->exec('INSERT IGNORE INTO item_sets_tree_edge (item_set_id, parent_item_set_id, `rank`) SELECT item_set.id, NULL, 0 FROM item_set');
        }
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
        $settings = $this->getServiceLocator()->get('Omeka\Settings');
        $item_sets_include_descendants = $settings->get('itemsetstree_item_sets_include_descendants', 0);

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
        $sharedEventManager->attach(
            'Omeka\Controller\Admin\ItemSet',
            'view.details',
            [$this, 'onItemSetDetails']
        );
        $sharedEventManager->attach(
            'Omeka\Controller\Admin\ItemSet',
            'view.show.sidebar',
            [$this, 'onItemSetShowSidebar']
        );

        if ($item_sets_include_descendants) {
            $sharedEventManager->attach(
                'Omeka\Api\Adapter\ItemAdapter',
                'api.search.pre',
                [$this, 'onItemApiSearchPre']
            );
        }

        $sharedEventManager->attach(
            \Omeka\Form\SiteSettingsForm::class,
            'form.add_elements',
            [$this, 'onSiteSettingsFormAddElements']
        );

        $sharedEventManager->attach(
            \Omeka\Form\ResourceBatchUpdateForm::class,
            'form.add_elements',
            [$this, 'onResourceBatchEditAddElement']
        );

        $sharedEventManager->attach(
            'Solr\ValueExtractor\ItemValueExtractor',
            'solr.value_extractor.fields',
            [$this, 'onSolrValueExtractorFields']
        );
        $sharedEventManager->attach(
            'Solr\ValueExtractor\ItemValueExtractor',
            'solr.value_extractor.extract_value',
            [$this, 'onSolrValueExtractorExtractValue']
        );

        $sharedEventManager->attach(
            '*',
            'api.preprocess_batch_update',
            [$this, 'onApiPreprocessBatchUpdate']
        );

        $sharedEventManager->attach(
            '*',
            'api.hydrate.post',
            [$this, 'onApiHydratePost']
        );

        $sharedEventManager->attach(
            'Omeka\Form\ResourceBatchUpdateForm',
            'form.add_input_filters',
            [$this, 'onResourceBatchUpdateFormAddInputFilters']
        );
    }

    public function getConfigForm(PhpRenderer $renderer)
    {
        $settings = $this->getServiceLocator()->get('Omeka\Settings');
        $item_sets_include_descendants = $settings->get('itemsetstree_item_sets_include_descendants', 0);
        $sorting_method = $settings->get('itemsetstree_sorting_method', 'title');

        $form = new ConfigForm;
        $form->init();
        $form->setData([
            'item_sets_include_descendants' => $item_sets_include_descendants,
            'sorting_method' => $sorting_method,
        ]);

        return $renderer->formCollection($form, false);
    }

    public function handleConfigForm(AbstractController $controller)
    {
        $settings = $this->getServiceLocator()->get('Omeka\Settings');
        $form = new ConfigForm;
        $form->init();
        $form->setData($controller->params()->fromPost());
        if (!$form->isValid()) {
            $controller->messenger()->addErrors($form->getMessages());
            return false;
        }
        $formData = $form->getData();
        $settings->set('itemsetstree_item_sets_include_descendants', $formData['item_sets_include_descendants']);
        $settings->set('itemsetstree_sorting_method', $formData['sorting_method']);

        return true;
    }

    public function onItemSetSave(Event $event)
    {
        $services = $this->getServiceLocator();
        $api = $services->get('Omeka\ApiManager');
        $entityManager = $services->get('Omeka\EntityManager');

        $data = $event->getParam('request')->getContent();
        if (!empty($data['item-sets-tree-parent-id'])) {
            $parentItemSet = $entityManager->find('Omeka\Entity\ItemSet', $data['item-sets-tree-parent-id']);
        }

        $itemSet = $event->getParam('response')->getContent();
        $itemSetsTreeEdges = $api->search('item_sets_tree_edges', ['item_set_id' => $itemSet->getId()])->getContent();
        if (!empty($itemSetsTreeEdges)) {
            $itemSetsTreeEdge = reset($itemSetsTreeEdges);
            $api->update('item_sets_tree_edges', $itemSetsTreeEdge->id(), ['o:item_set' => $itemSet, 'o:parent_item_set' => $parentItemSet ?? null]);
        } else {
            if (isset($parentItemSet)) {
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

    public function onItemSetDetails(Event $event)
    {
        $view = $event->getTarget();
        $entity = $event->getParam('entity');

        echo $view->partial('item-sets-tree/item-set-details', ['itemSet' => $entity]);
    }

    public function onItemSetShowSidebar(Event $event)
    {
        $view = $event->getTarget();
        $itemSet = $view->viewModel()->getCurrent()->getVariable('itemSet');

        echo $view->partial('item-sets-tree/item-set-details', ['itemSet' => $itemSet]);
    }

    public function onItemApiSearchPre(Event $event)
    {
        $request = $event->getParam('request');
        $data = $request->getContent();
        if (isset($data['item_set_id'])) {
            $api = $this->getServiceLocator()->get('Omeka\ApiManager');
            $itemSetsTree = $this->getServiceLocator()->get('ItemSetsTree');

            $itemSetIds = $data['item_set_id'];
            if (!is_array($itemSetIds)) {
                $itemSetIds = [$itemSetIds];
            }
            $itemSetIds = array_filter($itemSetIds);

            $allItemSets = [];

            foreach ($itemSetIds as $itemSetId) {
                $itemSet = $api->read('item_sets', $itemSetId)->getContent();
                $allItemSets[$itemSet->id()] = $itemSet;

                $descendants = $itemSetsTree->getDescendants($itemSet);
                foreach ($descendants as $descendant) {
                    $allItemSets[$descendant->id()] = $descendant;
                }
            }

            $allItemSetsIds = array_keys($allItemSets);
            $data['item_set_id'] = $allItemSetsIds;

            $request->setContent($data);
        }
    }

    public function onResourceBatchEditAddElement(Event $event)
    {
        $form = $event->getTarget();

        if ($form->getOption('resource_type') !== 'itemSet') {
            return;
        }

        $itemSetsIds = $this->getServiceLocator()
            ->get('ControllerPluginManager')->get('params')()
            ->fromPost('resource_ids', []);

        $query = $this->getServiceLocator()
            ->get('ControllerPluginManager')->get('params')()
            ->fromQuery();

        if (empty($itemSetsIds)) {
            if (empty($query)) {
                return;
            }
            $api = $this->getServiceLocator()->get('Omeka\ApiManager');
            $itemSets = $api->search('item_sets', $query)->getContent();
            $itemSetsIds = [];

            foreach ($itemSets as $itemSet) {
                $itemSetsIds[] = $itemSet->id();
            }
        }

        $options = [];
        $currentSite = $this->getServiceLocator()->get('ControllerPluginManager')->get('currentSite')();
        if ($currentSite) {
            $options['site_id'] = $currentSite->id();
        }

        $itemSetsTree = $this->getServiceLocator()->get('ItemSetsTree')->getItemSetsTree(null, $options);

        $form->add([
            'name' => 'item-sets-tree-parent-id',
            'type' => \Laminas\Form\Element\Select::class,
            'options' => [
                'label' => 'Parent item set', // @translate
                'value_options' => array_merge(
                    [[
                        'value' => '',
                        'label' => '[No change]',// @translate
                    ]],
                    $this->getValueOptionsSelect(
                    $itemSetsTree,
                    $itemSetsIds
                )
            ), ],
        ]);
    }

    public function onResourceBatchUpdateFormAddInputFilters(Event $event)
    {
        $form = $event->getTarget();

        if (!$form->has('item-sets-tree-parent-id')) {
            return;
        }

        $inputFilter = $event->getParam('inputFilter');
        if (!$inputFilter || !$inputFilter->has('item-sets-tree-parent-id')) {
            return;
        }

        $inputFilter->add([
            'name' => 'item-sets-tree-parent-id',
            'required' => false,
        ]);

        $select = $form->get('item-sets-tree-parent-id');
        $options = $select->getValueOptions();

        // Filter out disabled options automatically
        $validValues = [];
        foreach ($options as $option) {
            if (is_array($option)) {
                if (empty($option['disabled'])) {
                    $validValues[] = (string) $option['value'];
                }
            } else {
                $validValues[] = (string) $option;
            }
        }

        $input = $inputFilter->get('item-sets-tree-parent-id');

        // Attach the InArray validator
        $input->getValidatorChain()->attach(new InArray([
            'haystack' => $validValues,
            'strict' => InArray::COMPARE_NOT_STRICT,
        ]));
    }

    public function onSiteSettingsFormAddElements(Event $event)
    {
        $services = $this->getServiceLocator();
        $forms = $services->get('FormElementManager');
        $siteSettings = $services->get('Omeka\Settings\Site');

        $fieldset = $forms->get(SiteSettingsFieldset::class);
        $fieldset->populateValues([
            'itemsetstree_display' => $siteSettings->get('itemsetstree_display', 'all'),
        ]);

        $form = $event->getTarget();

        $groups = $form->getOption('element_groups');
        if (isset($groups)) {
            $groups['itemsetstree'] = $fieldset->getLabel();
            $form->setOption('element_groups', $groups);
            foreach ($fieldset->getElements() as $element) {
                $form->add($element);
            }
        } else {
            $form->add($fieldset);
        }
    }

    public function onSolrValueExtractorFields(Event $event)
    {
        $fields = $event->getParam('fields');
        $fields['item_sets_tree']['label'] = 'Item Sets Tree'; // @translate
        $fields['item_sets_tree']['children']['ancestors']['label'] = 'All item sets (including ancestors) internal identifiers'; // @translate
        $event->setParam('fields', $fields);
    }

    public function onSolrValueExtractorExtractValue(Event $event)
    {
        $item = $event->getTarget();
        $field = $event->getParam('field');

        if ($field === 'item_sets_tree/ancestors') {
            $itemSetsTree = $this->getServiceLocator()->get('ItemSetsTree');
            $value = [];
            foreach ($item->itemSets() as $itemSet) {
                $value[] = $itemSet->id();
                $ancestors = $itemSetsTree->getAncestors($itemSet);
                $value = array_merge($value, array_map(function ($ancestor) {
                    return $ancestor->id();
                }, $ancestors));
            }
            $value = array_unique($value);
            $event->setParam('value', $value);
        }
    }

    public function onApiPreprocessBatchUpdate(Event $event)
    {
        $request = $event->getParam('request');
        $rawData = $request->getContent();
        $data = $event->getParam('data');
        $service = $this->getServiceLocator();

        if (isset($rawData['item-sets-tree-parent-id'])) {
            $data['item-sets-tree-parent-id'] = $rawData['item-sets-tree-parent-id'];
            $event->setParam('data', $data);
        }
    }

    public function onApiHydratePost(Event $event)
    {
        $entity = $event->getParam('entity');
        if (!$entity instanceof \Omeka\Entity\ItemSet) {
            return;
        }

        $request = $event->getParam('request');
        $data = $request->getContent();

        if (!array_key_exists('item-sets-tree-parent-id', $data)) {
            return;
        }

        if ($data['item-sets-tree-parent-id'] === '' || $data['item-sets-tree-parent-id'] === null) {
            return;
        }

        $api = $this->getServiceLocator()->get('Omeka\ApiManager');

        $entityManager = $this->getServiceLocator()->get('Omeka\EntityManager');
        $itemSet = $entityManager->find('Omeka\Entity\ItemSet', $data['item-sets-tree-parent-id']);

        $itemSetsTreeEdges = $api->search('item_sets_tree_edges',
            ['item_set_id' => $entity->getId()])->getContent();

        if (!empty($itemSetsTreeEdges)) {
            $itemSetsTreeEdge = reset($itemSetsTreeEdges);

            $api->update('item_sets_tree_edges', $itemSetsTreeEdge->id(),
                        ['o:parent_item_set' => $itemSet],
                        [],
                        ['isPartial' => true]
                    );
        } else {
            $api->create('item_sets_tree_edges',
            ['o:item_set' => $entity,
            'o:parent_item_set' => $itemSet, ]
        );
        }

        return;
    }

    protected function getValueOptionsSelect($itemSetsTree, $itemSetsIds, $disabled = false, $depth = 0)
    {
        $valueOptions = [];

        foreach ($itemSetsTree as $itemSetsTreeNode) {
            $itemSet = $itemSetsTreeNode['itemSet'];
            $currentDisabled = $disabled || in_array($itemSet->id(), $itemSetsIds);
            $valueOptions[] = [
                'value' => $itemSet->id(),
                'label' => str_repeat('‒', $depth) . ' ' . $itemSet->displayTitle(),
                'disabled' => $currentDisabled,
            ];
            $valueOptions = array_merge($valueOptions, $this->getValueOptionsSelect(
                $itemSetsTreeNode['children'],
                $itemSetsIds,
                $currentDisabled,
                $depth + 1));
        }

        return $valueOptions;
    }

    protected function getValueOptions($itemSetsTree, $itemSetsIds, $disabled = false, $depth = 0)
    {
        $valueOptions = [];

        foreach ($itemSetsTree as $itemSetsTreeNode) {
            $itemSet = $itemSetsTreeNode['itemSet'];
            $currentDisabled = $disabled || in_array($itemSet->id(), $itemSetsIds);

            if (!$currentDisabled) {
                $valueOptions[] = [
                    $itemSet->id(),
                ];
            }

            $valueOptions = array_merge($valueOptions, $this->getValueOptions(
                $itemSetsTreeNode['children'],
                $itemSetsIds,
                $currentDisabled,
                $depth + 1));
        }

        return $valueOptions;
    }
}
