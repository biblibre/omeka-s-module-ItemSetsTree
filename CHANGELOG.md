# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## Unreleased

### Added

- New resource page block layout "Item sets (with ancestors)"

### Fixed

- Prevent PHP deprecation warning (#19)

## [0.7.2] - 2024-02-15

### Fixed

- Fixed `ItemSetsTree\Service\ItemSetsTree::getParent` that returned the parent
  item set regardless of permissions. Now, if the user does not have access the
  the parent item set, `getParent` returns null.
  This also fixes `getAncestors` which uses `getParent` internally.

## [0.7.1] - 2023-10-13

### Changed

- Moved documentation to GitHub Pages:
  https://biblibre.github.io/omeka-s-module-ItemSetsTree/

### Fixed

- Page block layout now correctly uses the current site settings (#14)

## [0.7.0] - 2023-01-10

This version adds support for Omeka S 4.0; the minimum supported version of
Omeka S is 3.0.

### Fixed

- Fixed REST API endpoint (#13)

## [0.6.0] - 2022-10-20

### Added

- Added ability to filter item sets tree on site page

## [0.5.0] - 2022-10-18

### Added

- Added ability to reorder manually the item sets tree

## [0.4.0] - 2021-04-14

### Added

- Added missing functions in view helper (getRootItemSets, getChildren,
  getDescendants)
- Added ability to display the tree only up to a fixed depth
- Added a page block layout
- Added parameter maxDepth to view helper itemSetsTreeSelect

### Fixed

- Do not retrieve item sets that user has no permission to see
- Hide navigation entry to unauthorized users

## [0.3.0] - 2020-10-14

**BREAKING CHANGE** This module is no longer compatible with Omeka S 2.x

### Added

- Added compatibility with Omeka S 3.x

## [0.2.0] - 2020-07-15

### Added

- Integration with Solr module
- Add ability to display items of descendant item sets
- Show parent item set in admin item set sidebar

### Changed

- Item sets are now ordered by title in the tree view

## [0.1.0] - 2020-04-07

Initial release

[0.7.2]: https://github.com/biblibre/omeka-s-module-ItemSetsTree/releases/tag/v0.7.2
[0.7.1]: https://github.com/biblibre/omeka-s-module-ItemSetsTree/releases/tag/v0.7.1
[0.7.0]: https://github.com/biblibre/omeka-s-module-ItemSetsTree/releases/tag/v0.7.0
[0.6.0]: https://github.com/biblibre/omeka-s-module-ItemSetsTree/releases/tag/v0.6.0
[0.5.0]: https://github.com/biblibre/omeka-s-module-ItemSetsTree/releases/tag/v0.5.0
[0.4.0]: https://github.com/biblibre/omeka-s-module-ItemSetsTree/releases/tag/v0.4.0
[0.3.0]: https://github.com/biblibre/omeka-s-module-ItemSetsTree/releases/tag/v0.3.0
[0.2.0]: https://github.com/biblibre/omeka-s-module-ItemSetsTree/releases/tag/v0.2.0
[0.1.0]: https://github.com/biblibre/omeka-s-module-ItemSetsTree/releases/tag/v0.1.0
