# Changelog

All notable changes to SVG Filters for Divi 5. Versions follow
[Semantic Versioning](https://semver.org/); release tags are `vX.Y.Z` and
must match `Version` and `D5SVGF_VERSION` in `divi5-svg-filters.php`.

## [0.5.0]

### Added

- Bevel: a dial for the light direction (the dot shows where the light comes
  from), above the slider.
- Frequency pairs (Water wave, Texture fill, Refractive glass, RGB glitch,
  Hover melt): a 2D pad with a noise swatch above the two sliders.
- Preset buttons show a preview of the preset.
- Duotone and Threshold: color presets (kit 1.6.0) and *Swap colors*.

### Changed

- Inner shadow: the shadow's opacity is set with the color picker's opacity
  instead of a separate slider (saved values still apply).
- Bundles the SVG Filter Kit 1.6.0.

## [0.4.0]

### Added

- Posterize: *Channels* switch — levels, darkest and brightest level for all
  channels together or for red, green and blue separately.
- Threshold: *Balance* moves the split between the two inks (kit 1.5.0).

### Changed

- Clearer labels: *Grain fineness*, *Fibre fineness* (kit 1.5.0).
- Bundles the SVG Filter Kit 1.5.0.

### Note

- Posterize level counts set per channel with 0.3.0 no longer apply.

## [0.3.0]

### Changed

- Tuning values are regular Divi fields: short labels with the explanation
  behind the info button, units in the field, Divi's numeric input and
  color picker with global colors (they stay linked to the global color).
- Duotone and Threshold are tuned with two colors; Posterize with level
  counts plus darkest/brightest level; frequency pairs with two sliders;
  Emboss with a 3×3 kernel grid.
- The "▸ Tune" fold-out is replaced by a "Tune ‹filter›" heading with
  presets and *Reset all*.
- Bundles the SVG Filter Kit 1.4.0.

### Added

- Presets: Emboss (Emboss, Sharpen, Find edges, Soften), Inner shadow (Soft,
  Hard, Wide, Top, Glow).

### Note

- Duotone/Threshold tunings saved with 0.1/0.2 no longer apply; other saved
  values do.

## [0.2.1]

### Fixed

- Tune sliders look like Divi's own sliders (thin track, number box with
  unit) in the light and dark builder theme; numbers use a decimal point
  regardless of the browser locale.

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
