# divi5-svg-filters

WordPress plugin that brings the SVG Filter Kit
([wp-svg-filters](https://github.com/markusritschel/wp-svg-filters)) into
the Divi 5 Visual Builder: an "SVG filter" section inside Divi's Filters
option group (dropdown, Element/Background scope, tuning fold-out), one
combined `filter:` declaration with Divi's own filter values, and an admin
page for uploading custom SVG filters. Divi 5 only. Guiding rule: never
break a page; if anything is missing, the SVG filter silently does not apply.
Design: `docs/superpowers/specs/2026-10-16-divi5-svg-filters-design.md`;
plan and status: `docs/superpowers/plans/2026-10-16-divi5-svg-filters.md`;
verified Divi internals: `docs/spike-findings.md`.

## Commands

```sh
# Dev dependencies (no local Composer needed)
docker run --rm -u 1000:1000 -e COMPOSER_HOME=/tmp/composer -v "$PWD":/app -w /app composer:2 install

# Unit tests (PHP 8.x locally, and the minimum PHP 7.4)
php vendor/bin/phpunit
docker run --rm -u 1000:1000 -v "$PWD":/app -w /app php:7.4-cli php vendor/bin/phpunit

# Kit tests (the bundled kit keeps its own smoke test)
php kit/tests/loader-test.php

# Update the bundled kit (currently from the local branch until v1.3.0 is released)
git subtree pull --prefix=kit ~/Code/wp-svg-filters plugin-hooks --squash
```

Browser tests run against a local Docker WordPress + Divi 5.15.1 that lives
outside this repo (`~/Code/divi5-svg-filters-spike/`, see the findings doc).
The Divi theme is licensed: never copy it, its zip, or extracted code into
this repo or CI.

## Architecture

`divi5-svg-filters.php` defines the constants and calls `Plugin::boot()`,
which only registers two hooks. On `after_setup_theme` (priority 20, after
the theme's `functions.php`) `Plugin::setup()` checks Divi 5 via
`et_builder_d5_enabled()`. Without it the plugin stops there and queues an
admin notice. With it, the plugin uses an SVG Filter Kit that is already
loaded (usually a theme copy; notice recommends removing it, and a copy
older than 1.3.0 disables uploads and tuning via `Plugin::kit_has_hooks()`)
or requires the bundled `kit/` (git subtree). The kit then prints the filter
defs in `wp_footer` and the `.fx-*` classes as before.

Planned (see the plan): a registry over `svgf_filters()` + uploaded filters;
`divi_module_wrapper_render` collects combined rules built with Divi's own
`Utils::style_statements()`; a builder script (app iframe only) adds the
fields, a custom tune component and a live-preview `<style>` through
`divi.module.wrapper.render`; uploads feed the kit via `svgf_extra_sources`,
tuned copies via `svgf_extra_defs`.

Code style: WordPress PHP style, PHP 7.4 compatible (no `str_contains`,
union types, `match`), namespace `D5SVGF`, one class per file in
`includes/`, required explicitly (no autoloader).

## Known Limitations & Improvements

- [high] Only the boot shell exists; fields, frontend CSS, uploads and
  updates follow the plan's phases 3–7.
- [medium] The kit subtree comes from the local branch `plugin-hooks` until
  kit v1.3.0 is pushed and tagged; then re-point the subtree to GitHub.
- [low] Admin notices are not dismissible.
