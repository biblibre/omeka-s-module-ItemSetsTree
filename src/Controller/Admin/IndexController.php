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

namespace ItemSetsTree\Controller\Admin;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use Laminas\Form\Form;

class IndexController extends AbstractActionController
{
    public function indexAction()
    {
    }

    public function editAction()
    {
        $form = $this->getForm(Form::class)->setAttribute('id', 'item-sets-tree-form');

        if ($this->getRequest()->isPost()) {
            $formData = $this->params()->fromPost();
            $form->setData($formData);
            if ($form->isValid()) {
                $jstree = json_decode($formData['jstree'], true);
                $buildTree = function ($treeNode) use (&$buildTree) {
                    $node = [
                        'item-set-id' => $treeNode['li_attr']['data-item-set-id'],
                        'children' => [],
                    ];
                    foreach ($treeNode['children'] as $child) {
                        $node['children'][] = $buildTree($child);
                    }
                    return $node;
                };
                $tree = [];
                foreach ($jstree as $treeNode) {
                    $tree[] = $buildTree($treeNode);
                }
                error_log(json_encode($tree));
                $this->itemSetsTree()->replaceTree($tree);
                $this->messenger()->addSuccess('Item sets tree successfully saved'); // @translate
                return $this->redirect()->toRoute('admin/item-sets-tree');
            } else {
                $this->messenger()->addFormErrors($form);
            }
        }

        $view = new ViewModel;
        $view->setVariable('form', $form);

        return $view;
    }
}
