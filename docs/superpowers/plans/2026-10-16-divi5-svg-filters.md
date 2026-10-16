# divi5-svg-filters — implementation plan

Date: 2026-10-16 · Spec: `docs/superpowers/specs/2026-10-16-divi5-svg-filters-design.md` (approved)

**Goal:** a Divi 5 plugin that adds an "SVG filter" section to Divi's Filters
option group (dropdown, Apply-to scope, tuning fold-out). It emits one combined
`filter:` declaration per element and breakpoint/state. It also handles custom
filter uploads, and never breaks a page.

**Approach:** spike first. Phase 0 answers the five open risks in a throwaway
Docker WordPress + Divi 5.15.1. Phases 1–8 are written against the _expected_
answers, and each phase names the spike finding it depends on. If a finding
differs, revise that phase before starting it (log the change under
"Deviations" in `implementation-notes.md`).

**Status:** Phase 0 done (2026-10-16). All five risks resolved, see
`docs/spike-findings.md`. Phases 4 and 5 are revised to the verified
mechanisms.

**Stack:** PHP ≥ 7.4, WP ≥ 6.0 (Divi 5.15.1's own requirements, confirmed).
Plain JS on `window.vendor.React` / `window.vendor.wp.hooks` / `window.divi.*`,
no build step (the spike built fields, a custom field component and the live
preview with `React.createElement`; the rejected alternative is a
`@wordpress/scripts` build). Tests: PHPUnit with Brain Monkey (no WP install) for PHP,
`node --test` for pure JS helpers, Playwright + headless Chromium for local
Docker E2E. pnpm for JS tooling, mise pins Node.

**Hard rules for every phase**

- `Divi.zip` and anything extracted from it stay outside the repo
  (`~/Projekte/webdesign/Divi/Divi.zip` → `$SPIKE/divi/`). `.gitignore` lists
  `docker/divi/`, `*.zip`, `wp-content/` as a backstop. Before each commit, run
  `git status --short | grep -i divi` and confirm no Divi files appear.
- No `git push`, no `gh repo create`, no release tags without asking Markus.
- Guiding rule tests (no `url(#id)` without printed defs; no fatals) are written
  _before_ the code they guard.

---

## Phase 0 — Spike (throwaway, not committed except the findings doc) — DONE

Result: `docs/spike-findings.md`. Verdict per phase: 1–3 **go** unchanged,
4 and 5 **revised** (below), 6–8 **go**. The environment stays in
`~/Code/divi5-svg-filters-spike/` for the Phase 5 E2E tests
(`docker compose -p d5svgf-spike up -d`, port 8085, admin/admin).

Location: `~/Code/divi5-svg-filters-spike/` (outside the repo). Output:
`docs/spike-findings.md` in the repo (committed), one section per risk with
evidence (file:line in Divi's built code, screenshots, stored post_content).

### 0.1 Environment

- [x] `docker-compose.yml` with `wordpress:php8.2-apache` + `mariadb:11`, port
      8085; volume-mount `./wp-content`. Use `wordpress:cli` for WP-CLI.
- [x] `wp core install`, admin/admin, permalinks `/%postname%/`.
- [x] `unzip ~/Projekte/webdesign/Divi/Divi.zip -d wp-content/themes/` and
      `wp theme activate Divi`. Confirm the version is 5.15.1 (`style.css` header).
- [x] Mount the kit read-only as a mu-plugin:
      `~/Code/wp-svg-filters → wp-content/mu-plugins/svg-filters/` plus a
      one-line loader. This proves the kit runs in plugin context (`svgf_url`
      falls back to `plugins_url`).
- [x] Playwright script `login.mjs`: logs in, opens a page in the Visual Builder
      (`?et_fb=1`), saves `storageState` for reuse.

### 0.2 Facts to record before probing

- [x] Divi's `Requires PHP` / `Requires at least` → plugin headers.
- [x] Locate in the extracted theme: `Filters::value()`,
      `ModuleUtils::use_attr_value`, `divi_module_wrapper_render`,
      `divi.module.options.filters.group.fields`, `PackageBuildManager`.
      Record file paths and signatures.

### 0.3 Risk 3 first — persistence (cheapest kill-switch)

- [x] Probe mu-plugin: on `divi_visual_builder_assets_before_enqueue_scripts`,
      register a JS file that hooks `divi.module.options.filters.group.fields`
      and appends a plain text field with `subName: 'svgFilter'`.
- [x] In the builder: set the value on a Text module, a Section, and a
      sub-element (Text → Toggle Options → Filters). Save.
- [x] `wp post get <id> --field=post_content` → is `svgFilter` stored under
      `…decoration.filters.desktop.value.svgFilter`? Does it survive a reload,
      export/import of the layout, and saving as an element preset and an
      option-group preset (`wp option get et_divi_builder_global_presets_d5`
      or the 5.15.1 equivalent)?
- [x] **Gate:** if Divi strips unknown sub-values, stop. Fallback to evaluate:
      store under a sibling attribute registered via
      `divi.moduleLibrary.moduleAttributes`. Before continuing, update the spec
      with Markus's approval (presets requirement 11 is at stake).

### 0.4 Risk 4 — field components and fold-out

- [x] Enumerate `window.divi.fieldLibrary` / `window.divi.modules` exports
      in the builder console. Find the components for select (with optgroups),
      button group / segmented toggle, range with unit, color picker (hex only,
      no global colors), text, and a collapsible container.
- [x] Prove: the fields render below "Blend" under a divider, and fields can be
      conditionally visible based on `svgFilter` (Divi's `visible` callback vs.
      own React state).

### 0.5 Risk 1 — sub-element selector

- [x] Set Divi Saturate on a Text module's sub-element filters, then read the
      selector in the frontend `<style>` (`#et-boc .et_pb_text_0 …`).
      Determine how to derive it in PHP from the module's style declaration
      args (likely `selector` passed alongside `attrName`).

### 0.6 Risk 2 — responsive/hover inheritance

- [x] Set desktop blur, tablet saturate, hover brightness. Compare Divi's CSS
      output with `Filters::value()` called per breakpoint/state through
      `ModuleUtils::use_attr_value`. Record the exact media queries and
      `:hover` selectors Divi emits.

### 0.7 Risk 5 — builder iframe injection

- [x] Find the app iframe (`#et-vb-app-frame` or 5.15.1 equivalent). Prove that a
      `<svg><defs>` injected from the top window renders `url(#fx-duotone)` inside it,
      and that a `<style>` injected there updates live. Alternative: is the kit's
      `wp_footer` output already present in the iframe (it is a frontend render)?

### 0.8 Wrap-up

- [x] Write `docs/spike-findings.md`. Mark each phase below **go / revise**.
- [x] Commit only the findings doc (+ screenshots ≤ 200 KB). Verify no Divi
      code is quoted beyond short signatures.

---

## Phase 1 — Kit changes (in `~/Code/wp-svg-filters`, released first)

Each change is TDD'd in `tests/loader-test.php` and committed atomically in the
kit. Release `v1.3.0` only after Markus approves the push/tag.

**Status:** done on kit branch `plugin-hooks` (worktree
`~/Code/wp-svg-filters/.claude/worktrees/plugin-hooks`, commits `a18427b`,
`786b845`, `17a751d`; not pushed, not tagged). Smoke-tested in the spike
WordPress + Divi (23 filters printed, no `data-*`, no PHP notices). As built:

- [x] **Source hook:** `apply_filters( 'svgf_extra_sources', array() )` returns
      SVG/`<filter>` markup strings, collected after the files with the same
      extraction (file IDs win, extras skipped). Cache signature includes a hash
      of the extras.
- [x] **Metadata accessor:** `svgf_filters()` returns per ID
      `{id, title, desc, category, tweaks[{key, attr, default, hint}], markup}`
      parsed from the _unstripped_ markup; `markup` is the stripped printable
      `<filter>`. Tweak key: `#<id>|<attr>` or `<tag>[:n]|<attr>`.
- [x] **Extra defs hook:** `apply_filters( 'svgf_extra_defs', '' )` (string)
      inside the `<defs>`; the defs SVG prints when either the kit markup or the
      extras are non-empty.
- [x] **Per-instance interactive JS:** elements name their filter copy in
      `data-svgf-spotlight="<id>"` / `data-svgf-melt="<id>"`; the
      `fePointLight` / `feDisplacementMap` is looked up inside that filter by
      tag. Delegated listeners on `document` replace a `svgfAttach()` global
      (builder re-renders need nothing). Verified with an 11-check Chromium
      script (`~/Code/divi5-svg-filters-spike/probe/check-interactive.mjs`),
      touch taps included.
- [x] Kit README and CHANGELOG updated, `SVGF_VERSION` 1.3.0.

Consequence for Phase 4: spotlight and melt elements always get their own
filter copy (even untuned) plus the data attribute, otherwise they share one
light (goal 10). A theme copy of the kit older than 1.3.0 lacks the hooks:
the plugin then offers only untuned kit filters and shows a notice.

## Phase 2 — Plugin skeleton and safety shell

```
divi5-svg-filters.php      header, constants, bootstrap on after_setup_theme
kit/                       git subtree of wp-svg-filters (main)
includes/                  PHP classes (namespace D5SVGF\)
assets/builder.js          VB script (plain JS)
assets/admin.js|css        upload page
tests/php/ tests/js/ e2e/  unit / unit / local-only browser tests
docker/                    compose file for E2E (no Divi inside)
```

**Status:** done (commits `e7b22ed` … `6aa6ee5`). 6 PHPUnit tests green on
PHP 8.5 and 7.4; checked in the spike WordPress: with Divi the bundled kit
loads from `plugins/divi5-svg-filters/kit/`, without Divi nothing is output
and the notice shows; no PHP notices.

- [x] `AGENTS.md` (canonical) + `CLAUDE.md` (`@AGENTS.md`), `.gitignore`,
      `composer.json` (PHPUnit 9.6, platform PHP 7.4; no Brain Monkey, own
      stubs in `tests/php/bootstrap.php`). `package.json` deferred to Phase 5
      (no JS tooling needed before).
- [x] `git subtree add --prefix=kit ~/Code/wp-svg-filters plugin-hooks --squash`
      (local branch; re-point to GitHub after the kit release).
- [x] Bootstrap test first: no Divi 5 (function missing or returning false)
      → kit not loaded, admin notice. The `ET\Builder\…` class guards move to
      Phase 4, where those classes are first used.
- [x] Kit loading on `after_setup_theme` priority 20: an already loaded kit
      (`SVGF_VERSION`) wins with a notice; older than 1.3.0 → second notice
      and `Plugin::kit_has_hooks()` false. Otherwise require `kit/svg-filters.php`.
- [x] Commit per step.

## Phase 3 — Filter registry (PHP, pure, heavily unit-tested)

- [ ] `Registry::all()` merges `svgf_filters()` + DB uploads, minus `fx-liquid`
      (goal 9). Groups by category (`Color`, `Texture`, `Blur & distortion`,
      `Shape`, `Light & depth`, `Fill & compositing`, `Motion`, `Custom`).
- [ ] Tweak hint parser: `"[min, max] description"` → range control with unit
      from the default; hex default → color; otherwise text. Same rules as the
      kit's demo site (port its JS logic; share fixtures).
- [ ] `Registry::tuned_markup( $id, $tune )` → copy with unique ID
      `fx-<id>--<hash8(tune)>`. Values clamped/sanitized per control type.
      Untuned or default-equal → shared ID.
- [ ] Exposed to JS as `window.D5SVGF = { filters, version }` via inline script.

## Phase 4 — Frontend rendering (revised after spike)

Storage (confirmed, persists through builder save and presets): under each
Filters attr path, `desktop.value.{ svgFilter: 'fx-duotone', svgScope: 'element'|'background', svgTune: { '<selector>|<attr>': value } }`.
`'none'`/empty = no filter.

- [ ] **Guard test first:** if a referenced ID is not in the registry
      (deleted upload, kit filter removed), output nothing. Print a console
      warning via the inline script, and record it for the admin warning list.
- [ ] `Css::rules( $attr, $selector, $svg_id )` (pure, unit-tested with
      captured attr fixtures): calls Divi's
      `Utils::style_statements( [ selectors => [desktop => [value => $selector]], attr, returnType => 'string', declarationFunction ] )`.
      The declaration function resolves via
      `ModuleUtils::use_attr_value( … 'getAndInheritAll' … )` and returns
      `filter:url(#ID) <Filters::value( resolved, Divi's map )>;`. This emits
      exactly Divi's breakpoints/states (spike: identical computed values at
      1280/900/400 px + hover). Copy Divi's 8-entry function map into a
      constant with a test that fails if `Filters::style_declaration` gains
      keys (string scan of the class file in the E2E env).
- [ ] Hook `divi_module_wrapper_render( $html, $args )`. For each Filters attr
      in `$args['attrs']` with `svgFilter`, resolve the selector: element name
      = first path segment (`module`, `content`, …), selector =
      `WP_Block_Type_Registry` → `attributes[element]['selector']`, with
      `{{selector}}` → `.` . `ModuleUtils::get_module_order_class_name( $id, $storeInstance )`.
      Non-string selector or missing class → skip (guiding rule). Collect
      rules and print them in `wp_footer` (after Divi's head styles, so equal
      selectors win), only for IDs whose defs are guaranteed printed.
- [ ] Scope `background`: reuse the kit's `--bg` mechanics. Add class
      `fx-<id>--bg` for untuned filters. For tuned copies, generate the
      equivalent `::before` + parallax rules for the unique ID. Divi's own
      values stay on the element rule (combined declaration only for scope
      `element`).
- [ ] Tuned copies appended through `svgf_print_defs_extra`. Dedupe by ID.
- [ ] Spotlight/melt: add `data-svgf-id` to the wrapper so the kit JS
      attaches per instance.
- [ ] Presets: `$args['attrs']` carries **no** preset values. Resolve
      `GlobalPreset::get_selected_preset()` (module preset stack via
      `modulePreset`) and `get_selected_group_presets()`; emit rules for the
      preset class `preset--module--<module>--<id>` from the preset's attrs
      (merged `styleAttrs` as Divi does). Follow Divi's replace rule: the
      module rule uses only the module's own `svgFilter` (pending Markus's
      decision, see findings "Presets"). Unit-test with the spike's preset
      fixture.

## Phase 5 — Visual Builder controls (revised after spike)

- [ ] `assets/builder.js` registered via
      `PackageBuildManager::register_package_build()` on
      `divi_visual_builder_assets_before_enqueue_scripts`, **app window only**
      (`enqueue_top_window => false`), deps
      `divi-vendor-wp-hooks`, `divi-field-library`, `divi-module` (without the
      latter two the script runs before `window.divi.fieldLibrary` exists).
      Every hook callback is wrapped in `try/catch` and returns its input
      unchanged on error.
- [ ] Fields hook `divi.module.options.filters.group.fields`: append after
      `blendMode`, copying Divi's field shape (`groupName: 'divi/filters'`,
      `category: 'layout'`, `features: {hover: false, sticky: false, responsive: false}`):
      `svgFilter` → `divi/select` with grouped options
      `{none, <category>: {label, options}}`; `svgScope` →
      `divi/button-options` (Element / Background only); `svgTune` → own
      component. `visible: (e) => hasFilter(e.attr)` for scope and tune.
      Divider: a label-only field or CSS on the first field (check visually).
- [ ] Fold-out: `window.divi.fieldLibrary.registerFieldComponent({ name: 'd5svgf/tune', component })`.
      Plain `React.createElement`: caret header "▸ Tune ‹name› (n values)",
      body renders controls per tweak (Divi's range/color/text components
      via `window.divi.fieldLibrary`, or plain inputs if their props prove
      awkward), writes the whole object via `props.onChange({ inputValue })`.
- [ ] Live preview: `divi.module.wrapper.render( element, ctx )` returns
      `Fragment(element, <style>)` with the combined rules for
      `.et_pb_<module>_<ctx.id>`, built with Divi's own JS
      (`window.divi.module.StyleStatements` / `styleLibrary.filtersStyleDeclaration`)
      instead of a port; presets via `window.divi.moduleUtils.getActiveModulePreset`.
      Untuned defs are already in the app frame (kit `wp_footer`); tuned
      copies go into one plugin-owned `<svg>` in the app document. Pure helpers
      (rule string building, tune hashing) unit-tested in `tests/js/` against
      the same fixtures as PHP.
- [ ] E2E (local Docker, spike env): choose duotone on Text, tune, save. The
      frontend CSS matches the builder computed style at 3 widths + hover;
      also a section, a sub-element (`content`), a module preset and an
      option-group preset saved through the preset UI. Use the selectors listed
      in the findings.

## Phase 6 — Custom uploads

- [ ] Sanitizer test-first with a hostile fixture set: `<script>`, `on*`,
      `<foreignObject>`, `href`/`xlink:href`, `<image>`, external `url()`,
      `<animate attributeName>` not on parent, duplicate IDs, inner ID clashes,
      no `<filter>`. DOMDocument with `LIBXML_NONET`, allow-list of primitives
      and attributes from the spec.
- [ ] Storage: CPT `d5svgf_filter` (one post per filter, markup in content,
      meta for source file). Rationale: per-filter list/delete and no autoload
      bloat. Feeds `svgf_extra_sources`.
- [ ] Admin page (Tools → SVG Filters, `manage_options`, nonce): upload,
      result message listing imported/skipped filters, table with live preview + delete, and a warning panel for missing-filter references from Phase 4.
- [ ] Class-name clash check: an upload ID that would produce an existing
      `.fx-*` class is rejected.

## Phase 7 — Release and updates

- [ ] `.github/workflows/ci.yml`: phpunit + `node --test` on PHP 7.4/8.3.
      No Docker, no Divi.
- [ ] `release.yml` modelled on the kit's (tag = version check, `git archive`
      zip with `export-ignore` for tests/e2e/docker).
- [ ] Updater: Plugin Update Checker (YahnisElsts, MIT, vendored) pointed at
      GitHub releases. The rejected alternative is our own `pre_set_site_transient_update_plugins`
      code, more code to maintain for the same result.
- [ ] **Ask Markus** before `gh repo create markusritschel/divi5-svg-filters --public`,
      push and first tag.

## Phase 8 — Docs

- [ ] README + AGENTS.md (overview, commands, architecture narrative, Known
      Limitations). Docs site / WP.org readme are open for discussion (spec):
      propose after v1, don't build now.

## Verification checklist (before calling v1 done)

- [ ] All unit tests green; E2E green locally against Divi 5.15.1.
- [ ] Deactivate plugin → pages render, no `url(#…)` left dangling.
- [ ] Delete an upload in use → element unfiltered, console + admin warning.
- [ ] Theme copy of kit present → single load, notice shown.
- [ ] `git log --all -- '*Divi*'` and `git ls-files | grep -i divi` are empty.
