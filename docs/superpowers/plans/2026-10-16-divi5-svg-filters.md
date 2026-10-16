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

**Stack:** PHP ≥ 7.4 (confirm against Divi 5.15.1's `Requires PHP`; see 0.2).
Plain JS on Divi's `window.vendor` packages, no build step, unless the spike
proves JSX/bundling unavoidable (the rejected alternative is a `@wordpress/scripts`
build, used only as a fallback). Tests: PHPUnit with Brain Monkey (no WP install) for PHP,
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

## Phase 0 — Spike (throwaway, not committed except the findings doc)

Location: `~/Code/divi5-svg-filters-spike/` (outside the repo). Output:
`docs/spike-findings.md` in the repo (committed), one section per risk with
evidence (file:line in Divi's built code, screenshots, stored post_content).

### 0.1 Environment

- [ ] `docker-compose.yml` with `wordpress:php8.2-apache` + `mariadb:11`, port
      8085; volume-mount `./wp-content`. Use `wordpress:cli` for WP-CLI.
- [ ] `wp core install`, admin/admin, permalinks `/%postname%/`.
- [ ] `unzip ~/Projekte/webdesign/Divi/Divi.zip -d wp-content/themes/` and
      `wp theme activate Divi`. Confirm the version is 5.15.1 (`style.css` header).
- [ ] Mount the kit read-only as a mu-plugin:
      `~/Code/wp-svg-filters → wp-content/mu-plugins/svg-filters/` plus a
      one-line loader. This proves the kit runs in plugin context (`svgf_url`
      falls back to `plugins_url`).
- [ ] Playwright script `login.mjs`: logs in, opens a page in the Visual Builder
      (`?et_fb=1`), saves `storageState` for reuse.

### 0.2 Facts to record before probing

- [ ] Divi's `Requires PHP` / `Requires at least` → plugin headers.
- [ ] Locate in the extracted theme: `Filters::value()`,
      `ModuleUtils::use_attr_value`, `divi_module_wrapper_render`,
      `divi.module.options.filters.group.fields`, `PackageBuildManager`.
      Record file paths and signatures.

### 0.3 Risk 3 first — persistence (cheapest kill-switch)

- [ ] Probe mu-plugin: on `divi_visual_builder_assets_before_enqueue_scripts`,
      register a JS file that hooks `divi.module.options.filters.group.fields`
      and appends a plain text field with `subName: 'svgFilter'`.
- [ ] In the builder: set the value on a Text module, a Section, and a
      sub-element (Text → Toggle Options → Filters). Save.
- [ ] `wp post get <id> --field=post_content` → is `svgFilter` stored under
      `…decoration.filters.desktop.value.svgFilter`? Does it survive a reload,
      export/import of the layout, and saving as an element preset and an
      option-group preset (`wp option get et_divi_builder_global_presets_d5`
      or the 5.15.1 equivalent)?
- [ ] **Gate:** if Divi strips unknown sub-values, stop. Fallback to evaluate:
      store under a sibling attribute registered via
      `divi.moduleLibrary.moduleAttributes`. Before continuing, update the spec
      with Markus's approval (presets requirement 11 is at stake).

### 0.4 Risk 4 — field components and fold-out

- [ ] Enumerate `window.divi.fieldLibrary` / `window.divi.modules` exports
      in the builder console. Find the components for select (with optgroups),
      button group / segmented toggle, range with unit, color picker (hex only,
      no global colors), text, and a collapsible container.
- [ ] Prove: the fields render below "Blend" under a divider, and fields can be
      conditionally visible based on `svgFilter` (Divi's `visible` callback vs.
      own React state).

### 0.5 Risk 1 — sub-element selector

- [ ] Set Divi Saturate on a Text module's sub-element filters, then read the
      selector in the frontend `<style>` (`#et-boc .et_pb_text_0 …`).
      Determine how to derive it in PHP from the module's style declaration
      args (likely `selector` passed alongside `attrName`).

### 0.6 Risk 2 — responsive/hover inheritance

- [ ] Set desktop blur, tablet saturate, hover brightness. Compare Divi's CSS
      output with `Filters::value()` called per breakpoint/state through
      `ModuleUtils::use_attr_value`. Record the exact media queries and
      `:hover` selectors Divi emits.

### 0.7 Risk 5 — builder iframe injection

- [ ] Find the app iframe (`#et-vb-app-frame` or 5.15.1 equivalent). Prove that a
      `<svg><defs>` injected from the top window renders `url(#fx-duotone)` inside it,
      and that a `<style>` injected there updates live. Alternative: is the kit's
      `wp_footer` output already present in the iframe (it is a frontend render)?

### 0.8 Wrap-up

- [ ] Write `docs/spike-findings.md`. Mark each phase below **go / revise**.
- [ ] Commit only the findings doc (+ screenshots ≤ 200 KB). Verify no Divi
      code is quoted beyond short signatures.

---

## Phase 1 — Kit changes (in `~/Code/wp-svg-filters`, released first)

Each change is TDD'd in `tests/loader-test.php` and committed atomically in the
kit. Release `v1.3.0` only after Markus approves the push/tag.

- [ ] **Source hook:** `apply_filters( 'svgf_extra_sources', array() )` returns
      arrays of `{id, markup, meta}`, merged into `svgf_collect()` after files.
      ID collisions: file wins, extra skipped. Cache signature includes a hash of
      the extras.
- [ ] **Metadata accessor:** `svgf_filters()` returns per-ID
      `{id, title, desc, category, tweaks[{selector, attr, default, hint}]}`
      parsed from the _unstripped_ markup. The printed defs stay stripped.
- [ ] **Extra defs hook:** `do_action( 'svgf_print_defs_extra' )` inside the
      `<defs>` so the plugin can append tuned copies to the same SVG. The defs
      SVG must print whenever extras exist, even if the kit's own markup is empty.
- [ ] **Per-instance interactive JS:** `svg-filters.js` attaches spotlight/melt
      per element. It reads the filter ID from `data-svgf-id` when present,
      falls back to the shared ID, and moves only that filter's
      `fePointLight`/turbulence. A `svgfAttach(el)` global lets the builder
      re-attach. Demo page keeps working.
- [ ] Update the kit README and CHANGELOG, bump `SVGF_VERSION` to 1.3.0.

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

- [ ] `git init` is done; add `AGENTS.md` (canonical) + `CLAUDE.md` (`@AGENTS.md`),
      `.gitignore`, `composer.json` (phpunit, brain/monkey), `package.json`.
- [ ] `git subtree add --prefix=kit git@github.com:markusritschel/wp-svg-filters main --squash`
      (after the Phase 1 release).
- [ ] Bootstrap test first: the plugin doesn't fatal when Divi is absent. It
      registers nothing on the frontend and shows an admin notice. It also
      doesn't fatal when `ET\Builder\…\Filters` is missing (class_exists guards).
- [ ] Kit loading on `after_setup_theme` priority 20: if `SVGF_VERSION` is
      already defined (theme copy), use it and add the "remove theme copy" notice.
      Otherwise require `kit/svg-filters.php`.
- [ ] Commit per step.

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

## Phase 4 — Frontend rendering (depends on spike 0.3, 0.5, 0.6)

Storage (assumed, confirm in 0.3): under each Filters attr path,
`desktop.value.{ svgFilter: 'fx-duotone', svgScope: 'element'|'background', svgTune: { '<selector>|<attr>': value } }`.

- [ ] **Guard test first:** if a referenced ID is not in the registry
      (deleted upload, kit filter removed), output nothing. Print a console
      warning via the inline script, and record it for the admin warning list.
- [ ] Hook `divi_module_wrapper_render` (or the finding from 0.5). Walk the
      module's attrs for Filters groups with `svgFilter`. For each, compute the
      selector and emit per breakpoint/state:
      `filter: url(#ID) <Filters::value(divi part)>`, reproducing Divi's own
      media queries/hover as recorded in 0.6. Print to a collected style block
      in `wp_footer` priority 2, _after_ defs are known to print.
- [ ] Scope `background`: reuse the kit's `--bg` mechanics. Add class
      `fx-<id>--bg` for untuned filters. For tuned copies, generate the
      equivalent `::before` + parallax rules for the unique ID.
- [ ] Tuned copies appended through `svgf_print_defs_extra`. Dedupe by ID.
- [ ] Spotlight/melt: add `data-svgf-id` to the wrapper so the kit JS
      attaches per instance.
- [ ] Presets: resolve preset attrs the same way Divi does before reading
      `svgFilter` (method from 0.3). Unit-test with a captured preset fixture.

## Phase 5 — Visual Builder controls (depends on 0.4, 0.7)

- [ ] `assets/builder.js` registered via `PackageBuildManager` on
      `divi_visual_builder_assets_before_enqueue_scripts`, depending on
      `divi-vendor-wp-hooks`. Every hook callback is wrapped in `try/catch`
      and returns the input unchanged on error.
- [ ] Fields hook: append divider, grouped select (`None` + categories),
      scope toggle (visible when a filter is chosen), fold-out
      "▸ Tune ‹name› (n values)" with generated controls.
- [ ] Live preview: inject defs + tuned copies into the app iframe and build the
      combined declaration in JS (port of Phase 4 logic, unit-tested in
      `tests/js/combine.test.mjs` against the same fixtures as PHP).
- [ ] E2E (local Docker): choose duotone on Text, tune, save. The frontend CSS
      matches the builder computed style.

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
