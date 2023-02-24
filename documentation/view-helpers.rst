View helpers
============

.. highlight:: php

itemSetsTree
------------

``itemSetsTree`` view helper provides the following methods:

getRootItemSets
^^^^^^^^^^^^^^^

Returns the root item sets (item sets that do not have a parent).

Synopsis
""""""""

::

    <?php $itemSets = $this->itemSetsTree()->getRootItemSets($options); ?>

Arguments
"""""""""

``$options``
    An associative array which can contains the following entries:

    ``sorting_method``
        Sorting method. Can be ``'none'`` or ``'title'``. If not given, the
        :ref:`global configuration <configuration-sorting-method>` is used.

Returns
"""""""

An array of ``Omeka\Api\Representation\ItemSetRepresentation``

Examples
""""""""

::

    <?php $itemSets = $this->itemSetsTree()->getRootItemSets(); ?>
    <?php $itemSets = $this->itemSetsTree()->getRootItemSets(['sorting_method' => 'title']); ?>

getItemSetsTree
^^^^^^^^^^^^^^^

Returns the item sets in a hierarchical structure

Synopsis
""""""""

::

    <?php $itemSets = $this->itemSetsTree()->getItemSetsTree($maxDepth, $options); ?>

Arguments
"""""""""

``$maxDepth``
    Maximum depth of the tree. If not given or ``null``, returns the whole
    tree.

``$options``
    An associative array which can contains the following entries:

    ``site_id``
        Site identifier. If given, this site's :ref:`configuration
        <site-configuration>` will be used. Defaults to the current site.

    ``sorting_method``
        Sorting method. Can be ``'none'`` or ``'title'``. If not given, the
        :ref:`global configuration <configuration-sorting-method>` is used.

Returns
"""""""

An array of associative arrays. Each associative array represents a tree node
and will contain the following keys:

    ``itemSet``
        The ``Omeka\Api\Representation\ItemSetRepresentation`` corresponding to
        the tree node.

    ``children``
        An array of associative arrays that represent children of the tree node


Example::

    <?php
    [
        [
            'itemSet' => $itemSet1, // ItemSetRepresentation
            'children' => [],
        ],
        [
            'itemSet' => $itemSet2, // ItemSetRepresentation
            'children' => [
                [
                    'itemSet' => $itemSet3, // ItemSetRepresentation
                    'children' => [],
                ],
            ],
        ],
    ]
    ?>


Examples
""""""""

::

    <?php
    // returns the whole tree
    $itemSets = $this->itemSetsTree()->getItemSetsTree();

    // returns only the root item sets
    $itemSets = $this->itemSetsTree()->getItemSetsTree(1);

    // returns an item sets tree corresponding to the current site
    $itemSets = $this->itemSetsTree()->getItemSetsTree(null, [
        'site_id' => $this->layout()->site->id(),
    ]);

    // returns an item sets tree corresponding to the current site, limited to
    // two levels, sorted by title
    $itemSets = $this->itemSetsTree()->getItemSetsTree(2, [
        'site_id' => $this->layout()->site->id(),
        'sorting_method' => 'title',
    ]);
    ?>

getParent
^^^^^^^^^

Returns the parent of an item set.

Synopsis
""""""""

::

    <?php $parentItemSet = $this->itemSetsTree()->getParent($itemSet); ?>

Arguments
"""""""""

``$itemSet``
    An object of type ``Omeka\Api\Representation\ItemSetRepresentation``

Returns
"""""""

An object of type ``Omeka\Api\Representation\ItemSetRepresentation``

getAncestors
^^^^^^^^^^^^

Returns the ancestors of an item set.

Synopsis
""""""""

::

    <?php $ancestors = $this->itemSetsTree()->getAncestors($itemSet); ?>

Arguments
"""""""""

``$itemSet``
    An object of type ``Omeka\Api\Representation\ItemSetRepresentation``

Returns
"""""""

An array of objects of type ``Omeka\Api\Representation\ItemSetRepresentation``.

The first element will be the parent, the second element will be the
grandparent, and so on.

getChildren
^^^^^^^^^^^

Returns the children of an item set.

Synopsis
""""""""

::

    <?php $children = $this->itemSetsTree()->getChildren($itemSet, $options); ?>

Arguments
"""""""""

``$itemSet``
    An object of type ``Omeka\Api\Representation\ItemSetRepresentation``

``$options``
    An associative array which can contains the following entries:

    ``sorting_method``
        Sorting method. Can be ``'none'`` or ``'title'``. If not given, the
        :ref:`global configuration <configuration-sorting-method>` is used.

Returns
"""""""

An array of objects of type ``Omeka\Api\Representation\ItemSetRepresentation``.

getDescendants
^^^^^^^^^^^^^^

Returns the descendants of an item set.

Synopsis
""""""""

::

    <?php $descendants = $this->itemSetsTree()->getDescendants($itemSet); ?>

Arguments
"""""""""

``$itemSet``
    An object of type ``Omeka\Api\Representation\ItemSetRepresentation``

Returns
"""""""

An array of objects of type ``Omeka\Api\Representation\ItemSetRepresentation``.
