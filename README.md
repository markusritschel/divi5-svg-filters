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
- **▸ Tune … (n values)** — the filter's tunable values: sliders for numbers,
  color pickers for colors, text fields for value lists. Tuned elements get
  their own copy of the filter; untuned ones share the standard one.

Sub-elements (e.g. a Text module's text) get the same fields when you enable
_Filters_ for them through Divi's _Toggle Options_. The SVG filter setting is
the same on all breakpoints and on hover; Divi's own filter values keep their
responsive and hover variants.

The pointer filters (_Pointer light_, _Hover melt_) react per element.

### Presets

The SVG filter is part of the Filters group, so it is saved in element and
option-group presets. Divi's rule applies unchanged: if an element sets its
own Filters values, they replace the preset's whole filter (including the
SVG part), just as they replace a preset's sepia or blur.

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

## Development

See [AGENTS.md](AGENTS.md) for commands and architecture. License:
GPL-2.0-or-later.
