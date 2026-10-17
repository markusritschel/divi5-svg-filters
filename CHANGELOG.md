# Changelog

All notable changes to SVG Filters for Divi 5. Versions follow
[Semantic Versioning](https://semver.org/); release tags are `vX.Y.Z` and
must match `Version` and `D5SVGF_VERSION` in `divi5-svg-filters.php`.

## [0.2.0]

### Changed

- Presets: an element keeps its preset's SVG filter when it sets its own
  Filters values (those still replace the preset's Divi values, as in Divi).
  Choose *None* on the element to drop the preset's SVG filter.

### Fixed

- Builder preview: a later module using the same preset no longer overrides
  an earlier module's own filter.

## [0.1.0]

First release, tested against Divi 5.15.1.

### Added

- SVG Filter section in Divi 5's Filters option group (modules, sections,
  rows, columns, sub-elements): grouped filter dropdown, _Apply to_ element
  or background only, _Tune_ fold-out with the filter's tuning hints.
- One combined `filter:` declaration with Divi's own filter values on every
  breakpoint and on hover; live preview in the Visual Builder.
- Tuned and per-element (pointer light, hover melt) filter copies.
- Tools → SVG Filters: upload SVG filters (allow-list sanitizer), preview,
  delete, warnings for filters that pages still reference.
- Updates from GitHub releases; bundles the SVG Filter Kit 1.3.0.
