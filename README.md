# SVG Filters for Divi 5

Brings the [SVG Filter Kit](https://github.com/markusritschel/wp-svg-filters)
([demo](https://markusritschel.github.io/wp-svg-filters/)) into the Divi 5
Visual Builder. Pick an SVG filter for any section, row, column, module or
sub-element, apply it to the whole element or only its background, tune its
values per element, and upload your own SVG filters.

The SVG filter is combined with Divi's own Filters settings (hue, saturate,
blur, …) in one `filter:` declaration, so neither overrides the other, on
every breakpoint and on hover.

**Guiding rule:** the plugin never breaks a page. If anything is missing (Divi
updated its internals, a filter was deleted, the plugin is deactivated), the
SVG filter silently does not apply and the page looks as it would without it.

## Requirements

- WordPress 6.0+, PHP 7.4+
- Divi 5 (theme or builder); tested with Divi 5.15.1. Divi 4 is not supported.

## Installation

1. Download `divi5-svg-filters-vX.Y.Z.zip` from the
   [releases](https://github.com/markusritschel/divi5-svg-filters/releases).
2. WordPress → Plugins → Add New → Upload Plugin → activate.

Updates arrive like any plugin update, from the GitHub releases.

## Usage

In the Visual Builder, open an element → **Design** → **Filters**. Below
_Blend_ you find:

- **SVG Filter** — the kit's filters by category (Color, Texture, Blur &
  distortion, Shape, Light & depth, Fill & compositing, Motion) and your
  uploads under _Custom_.
- **Apply to** — _Element_ (the whole element, combined with Divi's filters)
  or _Background only_ (a filtered copy of the background behind the content;
  Divi's own filters stay on the element).
- **Tune ‹filter›** — the filter's tunable values as regular Divi fields:
  sliders with units (px, deg, s), Divi's color picker (including your global
  colors, which stay linked; its opacity is the strength for Color tint and
  the shadow's opacity for Inner shadow), a 2D pad plus two sliders for horizontal/vertical frequencies
  (with a noise swatch), a dial for Bevel's light direction (the dot shows
  where the light comes from), levels for Posterize (for all channels
  together or each channel), a 3×3 kernel for Emboss. The info button on a
  field explains it in more detail. Some filters offer presets with a preview
  (Duotone, Threshold, Texture fill, Emboss, Inner shadow); *Swap colors*
  exchanges the two colors of Duotone, Threshold and Texture fill; *Reset all* returns to the filter's
  defaults. Tuned elements get their own copy of the filter; untuned
  ones share the standard one.

Sub-elements (e.g. a Text module's text) get the same fields when you enable
_Filters_ for them through Divi's _Toggle Options_. The SVG filter setting is
the same on all breakpoints and on hover; Divi's own filter values keep their
responsive and hover variants.

The pointer filters (_Pointer light_, _Hover melt_) react per element.

### Presets

The SVG filter is part of the Filters group, so it is saved in element and
option-group presets. An element using such a preset keeps the preset's SVG
filter even when it sets its own Filters values; those own values replace
the preset's Divi values (sepia, blur, …) as usual in Divi. To drop the
preset's SVG filter on one element, choose **None** there; choosing another
SVG filter replaces it.

### Your own filters

**Tools → SVG Filters** (administrators): upload `.svg` files with one or more
`<filter>` elements. Only the filters are kept: filter primitives and their
attributes, `<title>`, `<desc>`, `data-*` hints and SMIL animation of a
primitive's own attributes. Scripts, event handlers, styles, images
(`feImage`), external references, `foreignObject` and everything else are
removed. Filter IDs must be new (not used by the kit or another upload),
start with a letter and not contain `--`.

Uploads are stored in the database, not in the Media Library (WordPress's
block on SVG uploads stays as it is). They appear under _Custom_ in the
dropdown and, like kit filters, as `.<id>` / `.<id>--bg` classes for use by
hand. A `<title>` and `data-tweak-<attribute>="[min, max] description"`
hints work as in the kit (see its
[README](https://github.com/markusritschel/wp-svg-filters#adding-your-own-filters)).

Deleting an upload never breaks pages: elements using it lose the SVG filter,
the browser console notes it, and the admin page lists the affected pages.

### The kit in your theme

The plugin contains the kit. If your theme also loads it (e.g. via
`functions.php`), the theme's copy is used and an admin notice recommends
removing it; pages with hand-typed `.fx-*` classes keep working either way.
A theme copy older than kit 1.3.0 switches off tuning and uploads.

### Deactivating

Stored settings stay in the page content and are ignored by Divi; pages
render with Divi's own filters only. Uploaded filters remain in the database
(option `d5svgf_uploads`) for a later reactivation.

## Known limitations

- Option-group presets are applied on the frontend but not yet in the builder
  preview.
- Pointer filters set through a preset share one light per preset.
- Text-type tuning values must be number lists.
- The import message does not list what the cleaning removed.
- Tunings of Duotone and Threshold saved before 0.3.0 no longer apply (the
  filters now use two colors); those elements show the default colors.
- Posterize level counts set per channel with 0.3.0 no longer apply; choose
  *Each channel* and set them again (darkest/brightest carry over).
- Film grain tunings saved before 0.6.0 no longer apply (one strength slider
  replaces the two weights).

## Development

See [AGENTS.md](AGENTS.md) for commands and architecture. License:
GPL-2.0-or-later.
