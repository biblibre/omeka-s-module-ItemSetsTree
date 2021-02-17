# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- Added missing functions in view helper (getRootItemSets, getChildren,
  getDescendants)
- Added ability to display the tree only up to a fixed depth
- Added parameter maxDepth to view helper itemSetsTreeSelect

### Fixed

- Do not retrieve item sets that user has no permission to see

## [0.2.0] - 2020-07-15

### Added

- Integration with Solr module
- Add ability to display items of descendant item sets
- Show parent item set in admin item set sidebar

### Changed

- Item sets are now ordered by title in the tree view

## [0.1.0] - 2020-04-07

Initial release

[Unreleased]: https://github.com/biblibre/omeka-s-module-ItemSetsTree/compare/v0.2.0...2.x-0.2.x
[0.2.0]: https://github.com/biblibre/omeka-s-module-ItemSetsTree/compare/v0.1.0...v0.2.0
[0.1.0]: https://github.com/biblibre/omeka-s-module-ItemSetsTree/releases/tag/v0.1.0
