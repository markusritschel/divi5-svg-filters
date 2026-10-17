# divi5-svg-filters

WordPress plugin that brings the SVG Filter Kit
([wp-svg-filters](https://github.com/markusritschel/wp-svg-filters)) into
the Divi 5 Visual Builder: an "SVG filter" section inside Divi's Filters
option group (dropdown, Element/Background scope, tuning fields), one
combined `filter:` declaration with Divi's own filter values, and an admin
page for uploading custom SVG filters. Divi 5 only. Guiding rule: never
break a page; if anything is missing, the SVG filter silently does not apply.
Design: `docs/superpowers/specs/2026-10-16-divi5-svg-filters-design.md`;
plan and status: `docs/superpowers/plans/2026-10-16-divi5-svg-filters.md`;
verified Divi internals: `docs/spike-findings.md`; deviations:
`implementation-notes.md`. User docs: `README.md`.

## Commands

```sh
# Dev dependencies (no local Composer needed)
docker run --rm -u 1000:1000 -e COMPOSER_HOME=/tmp/composer -v "$PWD":/app -w /app composer:2 install

# Unit tests: PHP (8.x locally and the minimum 7.4), JS, the bundled kit
php vendor/bin/phpunit
docker run --rm -u 1000:1000 -v "$PWD":/app -w /app php:7.4-cli php vendor/bin/phpunit
node --test tests/js/*.test.mjs
php kit/tests/loader-test.php

# Browser tests: local WordPress + Divi 5 in Docker (port 8086), never in CI
./e2e/setup.sh ~/Projekte/webdesign/Divi/Divi.zip
cd e2e && pnpm install && CHROMIUM_PATH=/usr/bin/chromium pnpm test
docker compose -f e2e/docker-compose.yml down -v   # throw the environment away

# Update the bundled kit to a kit release tag
git subtree pull --prefix=kit https://github.com/markusritschel/wp-svg-filters.git vX.Y.Z --squash
```

Releases: bump `Version` and `D5SVGF_VERSION` in `divi5-svg-filters.php`,
tag `vX.Y.Z`, push the tag; `.github/workflows/release.yml` builds the zip
(with the runtime `vendor/` for updates) and attaches it to the release.

The Divi theme is licensed: never copy it, its zip, or extracted code into
this repo or CI. `e2e/setup.sh` unpacks it to `~/.cache/d5svgf-e2e`.

## Architecture

**Boot.** `divi5-svg-filters.php` loads `vendor/autoload.php` if present,
calls `Plugin::boot()` and `Updater::register()` (Plugin Update Checker on
GitHub releases; silently off without the library). On `after_setup_theme`
(priority 20, after the theme) `Plugin::setup()` checks Divi 5 via
`et_builder_d5_enabled()`; without it the plugin stops with an admin notice.
It then uses an already loaded kit (theme copy, with a notice; older than
1.3.0 → no uploads/tuning) or requires the bundled `kit/` (git subtree),
feeds uploads into the kit (`svgf_extra_sources`) and registers the admin
page. On `init` (Divi's classes autoload only then) `Plugin::start()` checks
`Frontend::supported()` and starts `Frontend` and `Builder`; otherwise it
stays off with a notice.

**Filters on offer.** `Registry` wraps the kit's `svgf_filters()` plus
upload IDs: dropdown groups (glass excluded, uploads and uncategorised under
Custom), `Tweaks` controls per hint, and `copy()` for tuned or per-element
copies (hash-suffixed filter and inner IDs, values validated by `Tweaks`).

**Frontend.** `Frontend` hooks `divi_module_wrapper_render`. For each module
it walks Divi's layers in cascade order (nested group presets, module
preset, explicit group presets, the module; presets via `GlobalPreset`).
Every Filters attribute with `svgFilter` gets rules from Divi's own
`Utils::style_statements()` with a declaration "url(#id) + Divi's filter
functions", so breakpoints, hover and sticky match Divi. Selectors are
Divi's prefix + the order/preset class doubled + the module.json element
selector; later layers with Divi values are restated so their Divi values
still win as in Divi, while the nearest earlier SVG filter carries over
unless a layer sets its own or `none` (Markus's decision, 0.2.0). Copies go
into the kit's defs via `svgf_extra_defs`; rules
print in `wp_footer` after the defs, copy rules only if the defs included
them. Unknown filters: no rule, console warning, option `d5svgf_missing`.

**Builder.** `Builder` registers `assets/core.js` (pure logic mirroring
`Tweaks`/`Frontend`, with `window.D5SVGF` data inline) and
`assets/builder.js` for Divi's app iframe, `assets/builder.css` for the
top-window panel. `builder.js` adds the fields through
`divi.module.options.filters.group.fields`, registers its own field
components (`d5svgf/tune-head` with presets and swap, `d5svgf/matrix`,
`d5svgf/pad`, `d5svgf/dial`; pad and dial keep keyboard focus in a hidden
input because Divi's shortcuts take arrow keys from anything else) and renders a `<style>` next to
each module through `divi.module.wrapper.render`, using Divi's
`StyleStatements`, the module's own attrs from the edit-post store and
selector templates from the module-library store.

**Uploads.** `Admin` (Tools → SVG Filters) reads uploaded files from the
request, `Sanitizer` rebuilds each `<filter>` from an allow-list, `Uploads`
stores them in the option `d5svgf_uploads`.

Stored per Filters attribute: `desktop.value.{ svgFilter, svgScope:
'element'|'background', svgTune: { <filter id>: { '<primitive>|<attr>':
value } } }`. Tests: `tests/php` (PHPUnit 9.6 with own WordPress stubs and
Divi fakes), `tests/js` (`node --test`), `e2e/` (Playwright, real Divi).

Code style: WordPress PHP style, PHP 7.4 compatible (no `str_contains`,
union types, `match`), namespace `D5SVGF`, one class per file in
`includes/`, required explicitly. Builder JS: plain ES5-style functions on
`window.vendor.React` / `wp.hooks` / `window.divi`, no build step.

## Known Limitations & Improvements

- [high] Not published yet: the GitHub repo, kit v1.3.0 release and the
  first plugin release need Markus's go; updates are untested end to end
  until then.
- [medium] Option-group presets: frontend only, not in the builder preview;
  saving them through Divi's preset UI is not covered by the e2e tests.
- [medium] Divi API: `Frontend::FILTER_FUNCTIONS` copies Divi 5.15.1's map;
  rerun `e2e/` after Divi updates.
- [low] Pointer filters set through a preset share one light per preset.
- [low] Module elements whose Filters use a selector that is not a plain
  `{{selector}} …` string in module.json are skipped.
- [low] Hover melt's hover strength is fixed in the kit's script.
- [low] Text tuning values must be number lists; the import message does not
  list what the sanitizer removed; admin notices are not dismissible.
- [low] Deliberate: no uninstall cleanup, so uploads survive a reinstall.
