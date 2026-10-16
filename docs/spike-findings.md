# Spike findings — Divi 5.15.1

Date: 2026-10-16 · Plan: `docs/superpowers/plans/2026-10-16-divi5-svg-filters.md`, Phase 0

Environment: WordPress 7.1.3 (`wordpress:php8.2-apache`), MariaDB 11, Divi
5.15.1 (`Requires PHP: 7.4`, `Requires at least: 6.0`). Kit `wp-svg-filters`
v1.2.1 mounted read-only and loaded as a mu-plugin. The probes were
mu-plugins plus Playwright 1.56.1 driving `/usr/bin/chromium`. The environment
lives in `~/Code/divi5-svg-filters-spike/` (outside the repo, contains the
licensed Divi theme, never commit).

**Verdict: all five risks resolved. No spec change needed.** One decision is
flagged for Markus (presets + own Divi values, see risk 3b).

## Summary

| Risk                           | Result                                                                                                                                                                                    | Consequence for the plan                                                                         |
| ------------------------------ | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------ |
| 1 Sub-element selector         | Selector = module.json `attributes.<element>.selector` with `{{selector}}` → order class; readable server-side from `WP_Block_Type_Registry` during render                                | Phase 4 resolves selectors from the registry; skip elements whose selector is not a plain string |
| 2 Responsive/hover inheritance | Reproducible exactly by calling Divi's own `Utils::style_statements()` with a custom `declarationFunction`                                                                                | No own inheritance code in PHP; JS uses Divi's exposed equivalents                               |
| 3 Persistence                  | Custom sub-values (`svgFilter`, `svgScope`, nested `svgTune` object) survive builder save; presets store and render them                                                                  | Storage shape confirmed; preset CSS must be emitted for the preset class separately              |
| 4 Field components + fold-out  | `divi/select` (grouped), `divi/button-options`, `divi/range`, `divi/color-picker`, `divi/text` all usable; custom field components via `window.divi.fieldLibrary.registerFieldComponent`  | Fold-out = own registered field component writing one `svgTune` object                           |
| 5 Builder iframe live preview  | Builder app (incl. settings panel) runs in `#et-vb-app-frame`; kit defs already present there; `divi.module.wrapper.render` can return the element plus a `<style>` sibling, updates live | Script registered for the app window only; no cross-frame injection                              |
| Guiding rule                   | Stored sub-values are inert without the plugin: frontend and builder render Divi's values only, no errors                                                                                 | Confirmed                                                                                        |

## Details

### Frontend (PHP)

- **Hook `divi_module_wrapper_render( $html, $args )`** fires for sections,
  rows, columns and modules (`Packages/Module/Module.php`, after
  `ModuleWrapper::render`). `$args` keys used: `attrs` (the module's own
  attributes, **without preset values**), `name`, `id`, `elements`
  (`ModuleElements`), `orderIndex`, `storeInstance`, `moduleClassname`.
- **Order class:** `ModuleUtils::get_module_order_class_name( $id, $store_instance )`
  (used by Divi itself in `Module.php`). The spike's regex on `moduleClassname`
  worked but is the fallback, not the method.
- **Divi's filter CSS** comes from `FiltersStyle::style()`, which passes
  `Filters::style_declaration()` as `declarationFunction` to
  `Utils::style_statements()`. Unknown keys in the attr value are ignored:
  `Filters::value()` reads only its fixed map.
- **Combined declaration:** calling `Utils::style_statements()` with the same
  `attr`, selector `.{orderClass}`, and a declaration function returning
  `filter:url(#id) <Filters::value(resolved)>` reproduces Divi's rule set
  exactly (resolved via `ModuleUtils::use_attr_value(… 'getAndInheritAll' …)`).
  Verified with computed styles at 1280/900/400 px plus hover: identical to
  Divi's own values with `url(#…)` prepended. Tablet `max-width:980px`, phone
  `max-width:767px`; hover rule is unwrapped, so desktop hover applies at all
  widths (Divi's behaviour, reproduced).
- **Cascade:** Divi's rules are in `<head>`
  (`et-core-unified-*-cached-inline-styles`). The plugin's rules, with identical
  selectors and printed later in `wp_footer`, win. The plugin must emit **every**
  breakpoint/state that Divi emits, otherwise its desktop rule would override
  Divi's tablet/phone rules. `style_statements()` guarantees this.
- **Not usable:** `divi_module_options_element_style_components` only covers
  "advanced styles"; the module Filters path (`ElementStyle.php`) calls
  `FiltersStyle` directly.
- **Sub-elements (risk 1):** Text with `content.decoration.filters` renders as
  `.et_pb_text_0 .et_pb_text_inner{filter:…}`, i.e. the `content` attribute's
  selector `{{selector}} .et_pb_text_inner`. During a frontend request
  `WP_Block_Type_Registry::get_instance()->get_registered( 'divi/text' )->attributes['content']['selector']`
  returns exactly that string. Under WP-CLI the registry is empty (modules
  register in the request context only), so unit tests use fixtures.

### Presets (risk 3b)

- Presets: option `builder_global_presets_d5`
  (`GlobalPreset::option_name()`), items at `module[<name>].items[<id>]` with
  `attrs`, `styleAttrs`, `renderAttrs`, `priority`; module selects via the
  `modulePreset` attr (array stack, last wins); group presets via
  `GlobalPreset::get_selected_group_presets()`.
- Divi renders preset styles **separately** under the class
  `preset--module--<module>--<id>` (same class in builder and frontend), calling
  the styles component with the preset's attrs.
- A preset carrying `svgFilter` stores and reaches the renderer unchanged.
- **Divi's own rule:** a module's own Filters values produce a `filter:` on the
  module class that **replaces** the preset's whole `filter:` (A: preset only →
  `sepia(0.4)`; B: preset + own `blur(3px)` → `blur(3px)`, sepia gone).
  Builder and frontend agree.
- **Decision for Markus:** the plan follows Divi's rule (conservative). The
  preset class gets "preset SVG + preset Divi values", the module class gets
  "module SVG + module Divi values". Consequence: an element that uses an SVG
  preset _and_ sets any own Divi filter loses the preset's SVG filter, exactly
  as it loses the preset's sepia today. Alternative: the module rule falls back
  to the preset's `svgFilter` when the module has none (more intuitive, but
  deviates from Divi's semantics and must be mirrored in the builder).

### Visual Builder (JS)

- **Windows:** top window `?et_fb=1`, app iframe `#et-vb-app-frame`
  (`&app_window=1`). The fields hook, the settings panel React tree and the
  module rendering all run in the app window; the top window never executed
  the script. Register with `enqueue_top_window => false`.
- **Registration:** `PackageBuildManager::register_package_build()` on
  `divi_visual_builder_assets_before_enqueue_scripts`. Deps must include
  `divi-field-library` and `divi-module` (not only `divi-vendor-wp-hooks`),
  otherwise the script runs before `window.divi.fieldLibrary` exists and the
  custom field silently falls back to Divi's `divi/warning` component.
- **Fields hook** `divi.module.options.filters.group.fields( fields, ctx )`:
  `ctx` = `{attrName, grouped, groupLabel, fieldLabel, visible, defaultGroupAttr}`.
  Fires on builder load already, including sub-element paths
  (`content.decoration.filters`). Fields are an object; appending keys after
  `blendMode` places them below Blend. Field shape copied from Divi's own:
  `{attrName, subName, label, description, groupName: 'divi/filters', category: 'layout', render: true, features: {hover: false, sticky: false, responsive: false}, component: {type: 'field', name, props}}`.
- **Components verified in the UI:** `divi/select` with grouped options
  `{none: {label}, color: {label: 'Color', options: {...}}}` renders category
  headers and resolves stored values to labels. `divi/button-options`
  `{options, showLabel, fullwidth}` renders the Element / Background-only
  segmented control. `divi/text` works. `divi/range` and `divi/color-picker` are
  used by Divi with the same field shape (not exercised separately).
- **Conditional fields:** `visible: (e) => bool` receives `attrs`, `attr`,
  `attrName`, `moduleId`, `breakpoint`, `state`, … — so scope/tune fields can
  show only when `attr.desktop.value.svgFilter` is set.
- **Fold-out:** `window.divi.fieldLibrary.registerFieldComponent({name, component})`
  (public; Divi's module library uses it). A plain `React.createElement`
  component receives `value`, `onChange`, `attrs`, `moduleId`, `defaultValue`, …;
  `onChange({inputValue: {...}})` stores an object; it persisted on save.
- **Live preview:** `divi.module.wrapper.render( element, {id, name, attrs, parentAttrs, isSelected, …} )`
  re-runs on every attribute change. Returning
  `Fragment(element, <style>)` with `.et_pb_<name>_<id>{filter:url(#…) …}`
  updated the computed style immediately after choosing a filter. Builder
  order classes are `et_pb_<module>_<uuid>` (UUID changes per load).
  Reusable Divi JS on `window.divi`: `module.StyleStatements`,
  `module.FiltersStyle`, `styleLibrary.filtersStyleDeclaration`,
  `moduleUtils.getActiveModulePreset`, `globalData.*` (presets).
- **Kit in the builder:** `#svgf-defs` and the kit's `.fx-*` CSS are present in
  the app iframe (it is a frontend render), so untuned filters need no
  injection; tuned copies are appended to the app document by the same script.

### Practical notes for later E2E tests

- A page opens in the builder only with post meta `_et_pb_use_builder = on`
  (otherwise `?et_fb=1` shows the plain frontend).
- Stable builder selectors: select `#et-vb-module-decoration-filters--svgFilter`
  with `li[data-value=…]`; segmented `.et-vb-field-module-decoration-filters-svg-scope button[data-name=…]`;
  text `#et-vb-field-input-text-module-decoration-filters--<subName>`.
- The module selection click target in the app frame: `.et_pb_text.et_pb_module`.
- Divi 5 block markup for fixtures:
  `<!-- wp:divi/placeholder --><!-- wp:divi/section --><!-- wp:divi/row --><!-- wp:divi/column {...4_4...} --><!-- wp:divi/text {...} /-->…`.

### Not verified (low risk, cover in Phase 5 E2E)

- Saving an option-group preset of the Filters group through the preset UI
  (data-level preset verified; UI path not driven).
- Layout export/import round trip.
- Modules whose Filters target a selector not declared in module.json (the
  plugin skips non-string selectors; per-module surprises show in E2E).
