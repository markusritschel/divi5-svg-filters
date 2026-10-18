# divi5-svg-filters — design

Date: 2026-10-16 · Status: draft for approval

## Intent

Markus builds WordPress sites with Divi. The **SVG Filter Kit**
(<https://github.com/markusritschel/wp-svg-filters>, demo:
<https://markusritschel.github.io/wp-svg-filters/>) already provides 23 SVG
filters for themes, applied with CSS classes (`.fx-name`, `.fx-name--bg`). That
works, but it means typing class names and editing SVG files by hand.

This plugin brings the kit into the **Divi 5 Visual Builder** as native-feeling
controls: pick a filter for any element, choose whether it affects the whole
element or only its background, and tune its values per element, live, next to
Divi's own filter settings. Users can also upload their own SVG filter files.

## Guiding rule (anti-goal)

**Never break a page.** If anything goes wrong (Divi update changes its hooks,
plugin deactivated, a filter was deleted, malformed upload, PHP error), the
acceptable outcome is that the SVG filter silently does not apply. The page
must render and look as it would without the plugin. Concretely:

- Never emit `filter: url(#id)` unless the `<filter id="id">` definition is
  guaranteed to be printed on the same page. (Depending on the browser, a
  reference to a missing filter can make the element disappear.)
- No fatal errors: if Divi 5 is not active, or an expected Divi class or hook is
  missing, the plugin does nothing on the frontend and shows an admin notice.
- Deactivating the plugin leaves stored settings inert in the page content; Divi
  must keep rendering those pages normally.
- An uploaded filter that is later deleted: elements using it fall back to no
  SVG filter.

In addition, a warning could be printed to the console. Plus, show a warning in 
the admin backend section of the SVG Filters plugin.

## Goals

1. **Placement (decided by Markus):** the controls live **inside Divi's existing
   Filters option group**, below "Blend", under a small "SVG filter" divider.
   Because they attach to the Filters group, they appear wherever Divi shows
   Filters: on modules, sections, rows, columns, and on sub-elements where the
   user enables "Filters" through Divi's per-group _Toggle Options_ menu.
2. **Layout (decided, "A + C" from the mockups):**
   - Default: one row, **SVG Filter** dropdown, value "None".
   - After choosing a filter: **Apply to** (segmented: _Element_ /
     _Background only_) and a folded **"▸ Tune ‹filter name› (n values)"**
     fold-out containing that filter's tuning controls.
   - The dropdown groups filters by category (Color, Texture, Blur & distortion,
     Shape, Light & depth, Fill & compositing, Motion, Custom; no Glass in v1,
     see goal 9).
3. **Combined filter declaration (decided):** when Divi's own Filters values
   (Hue, Saturate, Brightness, …) are set as well, the element gets **one**
   `filter:` declaration containing both, e.g.
   `filter: url(#fx-duotone) saturate(120%) blur(2px)`, so neither overrides
   the other. Divi's values may differ per breakpoint and on hover; the
   combined output must reproduce each of Divi's variants with the SVG part
   added, so the plugin never removes or changes Divi's own responsive/hover
   filter behaviour.
4. **Per-element tuning (decided):** the fold-out shows the tuning values the
   kit's SVG files declare via `data-tweak-<attribute>="[min, max] description"`
   hints (slider for single numbers with the hint's range and unit, color
   picker for hex colors, text field for value lists — same mapping as the demo
   site). An element whose tuning differs from the file's defaults gets its own
   copy of the filter with a unique ID; untuned elements share the standard
   definition.
5. **One setting per element (decided):** the SVG filter choice, scope and
   tuning are not responsive and have no hover variant in v1.
6. **Live preview** in the Visual Builder: choosing/tuning a filter updates the
   element immediately, including the combined declaration.
7. **Custom filters (decided):**
   - An **"SVG Filters" admin page** (administrators only) to upload SVG files,
     list them with a live preview, and delete them.
   - On upload, only the `<filter>` elements are kept (allow-list of SVG filter
     primitives, their attributes, `<title>`, `<desc>`, `data-*` hints);
     everything else (scripts, event handlers, `<foreignObject>`, external
     references, images) is discarded. Files with no valid `<filter>` are
     rejected with a message.
   - Stored **in the database**, not in the Media Library; WordPress's default
     block on SVG uploads stays untouched.
   - Uploaded filters appear in the dropdown under **"Custom"**. If a file
     carries the kit's metadata (`<title>`, `data-tweak-*`), its name and tuning
     work too; otherwise the dropdown shows its ID and there is no tuning.
   - A filter ID that already exists (kit or upload) is rejected with a clear
     message, not silently replaced.
8. **Kit reuse:** the kit (`wp-svg-filters`, branch `main`) is included via
   `git subtree`, so filters, loader rules and assets have one source of truth
   and kit updates arrive via `git subtree pull`. The kit's behaviour stays:
   filter definitions printed in `wp_footer` only (never `wp_body_open`), the
   `.fx-*` / `.fx-*--bg` classes keep working (including the Divi 4/5 parallax
   handling), assets for glass and the interactive filters (spotlight, melt)
   are loaded. If a theme also contains a copy of the kit, only one loads (the
   kit's existing `SVGF_VERSION` guard).
   This could be open for discussion at a later point if we encorporate the kit
   entirely, making the plugin independent of the kit. But for now, that solution
   sounds good.

## Decided after review (Markus)

9. **Glass is not in the dropdown in v1.** `fx-liquid` stays usable through the
   kit's `.glass .glass--liquid` classes. A proper backdrop mode for it is a
   later feature (see *Later*). *Done in 0.8.0 (Markus, 2026-10-18): "Apply
   to: Backdrop" for every filter; glass is always a backdrop.*
10. **Spotlight and melt are in, independent per element.** Each element using
    them reacts to its own pointer and keeps its own tuning; this needs the kit
    JS to attach per instance (kit change, see calls below).
11. **SVG settings travel with Divi presets.** When the Filters group is saved
    in an element or option-group preset, the SVG filter choice, scope and
    tuning go with it, and elements using the preset get the same result on
    the frontend as in the builder (including combined declaration and tuned
    filter copies).
12. **Public GitHub repo with updates.** The repo
    `markusritschel/divi5-svg-filters` is public (to be created). Installed 
    sites get **automatic updates from GitHub releases** (tag-triggered release 
    zip, like the kit). CI runs PHP and JS unit tests only; the Docker + Divi 
    browser tests stay local, because the licensed Divi zip must never reach 
    CI or the repo.

## Calls made by Claude during review (check these)

- **Theme copy of the kit wins.** WordPress loads plugins before the theme, so
  the plugin must not load its bundled kit until the theme has had its turn
  (`after_setup_theme`). If a theme already loaded the kit (`SVGF_VERSION`
  defined), the plugin uses that copy, including any of the theme's own SVG
  files, and shows an admin notice recommending removal of the theme copy.
  Pages relying on hand-typed `.fx-*` classes keep working either way. The
  dropdown lists what actually loaded; filters without metadata show their ID
  and offer no tuning.
- **Kit changes are allowed** (same owner). Where the plan needs it, the kit
  gets small additions first, released before the plugin depends on them:
  a hook to add filter markup from other sources (the database uploads), an
  accessor for the unstripped metadata (`<title>`, `data-*`) while the printed
  defs stay stripped, and interactive JS that can attach per instance.
- **Uploaded filters also get `.fx-<id>` / `.fx-<id>--bg` classes**, like kit
  filters, for hand use. Check potential name clashes.
- **Uploads keep SMIL animation**: `<animate>` and `<set>` are allowed when
  `attributeName` names an attribute of the parent primitive; no `href` /
  `xlink:href` anywhere. Inner IDs on primitives are kept but must be unique
  site-wide (checked like filter IDs).
- **Multi-filter files:** valid filters are imported, colliding or invalid ones
  are skipped and listed in the message. The admin page lists and deletes per
  filter.
- **Tuning colors are plain hex in v1** (no Divi global colors), so a filter
  copy never depends on a value that can change after rendering.

## Non-goals (v1)

- Divi 4 or the classic builder. We build for Divi 5 only.
- Responsive or hover variants of the SVG filter settings.
- Storing filters in the Media Library or allowing SVG uploads there.
- Editing filter markup in the admin (upload and delete only).
- WordPress.org distribution.
- The glass filter (`fx-liquid`) in the dropdown.

## Later (keep in mind)

- **Glass / backdrop mode:** offer `fx-liquid` (and future backdrop filters)
  as a third "Apply to: Backdrop" option, with the Chromium-only refraction and
  frosted fallback the kit already implements.
- The kit dependency (see comment about later discussion above).

## Constraints and known facts

- **Target:** Divi 5, verified against **Divi 5.15.1** (zip at
  `~/Projekte/webdesign/Divi/Divi.zip`; licensed, must never be committed).
- **Hooks found in Divi 5.15.1** (from its built Visual Builder code):
  - `divi.module.options.filters.group.fields` — `applyFilters(name, fields,
{ attrName, grouped, groupLabel, fieldLabel, visible, defaultGroupAttr })`.
    Runs for every rendering of Divi's Filters group, with `attrName` set to the
    group's storage path (e.g. `module.decoration.filters`, or a sub-element's
    path). Divi's own fields store sub-values under that path via `subName`
    (`hueRotate`, `saturate`, …, `blendMode`).
  - Global `divi.moduleLibrary.moduleAttributes` and
    `divi.moduleLibrary.moduleSettings.groups` exist (applied to every module at
    registration), plus per-module variants.
  - The _Toggle Options_ menu lists a **hard-coded** set of 16 Divi component
    names (`dynamicSubgroupComponentNames`, includes `divi/filters`), with no
    hook — the reason the controls go inside Filters rather than a group of
    their own.
  - Frontend style: `ET\Builder\Packages\StyleLibrary\Declarations\Filters\Filters`
    builds `filter:` from a fixed map with **no hook**; its public static
    `value( $attr_value, $filter_functions )` can be reused to reproduce Divi's
    part exactly.
  - Wrapper/class hooks per the official docs: PHP `divi_module_wrapper_render`
    (receives `attrs`; the docs call it the only way to reach sections, rows,
    columns), `divi_module_classnames_value`; JS `divi.module.classnames.value`,
    `divi.module.wrapper.render`. Builder scripts are registered with
    `PackageBuildManager::register_package_build()` on
    `divi_visual_builder_assets_before_enqueue_scripts`, depending on
    `divi-vendor-wp-hooks`.
- **Testing environment:** Docker and WP-CLI are available locally. A throwaway
  WordPress + Divi 5.15.1 in Docker, driven with headless Chromium, is the
  intended way to verify builder and frontend behaviour.
- Markus's defaults: small, no-build frontends where feasible; atomic commits;
  AGENTS.md as canonical project instructions (CLAUDE.md imports it).

## Left to the builder

- Plugin file layout, prefixes, and the `git subtree` prefix for the kit.
- Attribute storage details: sub-value names inside the Filters attribute (e.g.
  `svgFilter`, `svgScope`, `svgTune`), and the shape of stored tuning values.
- How the combined CSS is emitted (selector strategy, specificity, where the
  per-element rules and per-element filter copies are printed), as long as the
  guiding rule and goal 3 hold.
- How the Visual Builder script is built (plain JS on Divi's exposed
  `window.vendor` packages is preferred if it suffices; a build step only if
  needed).
- Database storage shape for uploads (custom post type vs. option) and the
  sanitizer implementation.
- Minimum WordPress/PHP versions (match Divi 5.15.1's requirements).
- Release packaging (a tag-triggered release zip like the kit's is expected)
  and the update mechanism (e.g. an established GitHub-release updater
  library vs. a small own implementation of WordPress's update hooks).

## Open risks — verify first (spike)

1. Which CSS selector Divi uses for a **sub-element's** Filters (e.g. Text
   enabled via Toggle Options), so the combined rule targets the same element.
2. Reproducing Divi's **responsive/hover inheritance** for its own filter
   values in the combined rule (server: `ModuleUtils::use_attr_value`; builder:
   the JS equivalent).
3. Whether custom sub-values inside the Filters attribute are **persisted** by
   Divi on save, and survive Divi's attribute sanitizing/presets.
4. Which Divi field components are available for the controls (select with
   groups, segmented toggle, range, color picker, text) and how to build the
   **fold-out** inside a group.
5. Injecting filter definitions and generated CSS into the **builder's app
   iframe** for live preview.


## Documentation

Consider a documentation of the plugin. One that showcases the plugin on a 
github page, and also documentation for uploading the plugin to the wordpress
plugin store.
May be open for discussion.
