# Implementation notes

## Deviations

- 2026-10-16, Phase 0.1: no separate `login.mjs`. Login and `storageState`
  live in the spike's `probe/vb.mjs` driver, which also opens the builder
  and runs step scripts. Same purpose, one file fewer.
- 2026-10-16, Phase 0.1: pages open in the builder only with post meta
  `_et_pb_use_builder = on`; the spike sets it via WP-CLI instead of clicking
  "Edit With Divi".
- 2026-10-16, Phase 0.3: the preset test used a preset written at data level
  (`builder_global_presets_d5` via `et_update_option`) instead of saving one
  through the preset UI. Storage, rendering and builder/frontend parity were
  verified that way; the UI save path moves to the Phase 5 E2E list.
- 2026-10-16, Phase 0.8: no screenshots committed (plan allowed ≤ 200 KB).
  They show Divi's licensed UI and add nothing the findings text doesn't say;
  they stay in `~/Code/divi5-svg-filters-spike/shots/`.
- 2026-10-16, Phase 0: the spike environment is kept, not thrown away. Phase 5
  E2E reuses it (it holds the Divi theme, so it stays outside the repo).
- 2026-10-16, Phase 4 presets: conservative choice, the plugin mirrors
  Divi's own rule (a module's own Filters values replace the preset's whole
  `filter:`, SVG part included). Flagged for Markus in
  `docs/spike-findings.md` → "Presets". **Superseded 2026-10-17:** Markus
  chose to keep the preset's SVG filter (0.2.0); `none` switches it off.
- 2026-10-16, Phase 1: kit work happens on a new kit branch `plugin-hooks` in
  a git worktree (`~/Code/wp-svg-filters/.claude/worktrees/plugin-hooks`), so
  Markus's kit checkout and `main` stay untouched. `.claude/` added to the
  kit's local `.git/info/exclude` (not committed).
- 2026-10-16, Phase 1: `svgf_extra_sources` takes markup strings, not
  `{id, markup, meta}` arrays: the kit already extracts IDs and metadata from
  markup, so arrays would duplicate that.
- 2026-10-16, Phase 1: `svgf_extra_defs` is a string filter, not the planned
  `svgf_print_defs_extra` action: returning a string is testable and lets the
  kit decide whether the defs SVG prints at all.
- 2026-10-16, Phase 1: `data-svgf-spotlight` / `data-svgf-melt` instead of one
  `data-svgf-id` (the attribute also says which behaviour applies), and
  delegated events instead of an `svgfAttach()` global.
- 2026-10-16, Phase 1: no JS test added to the kit (it has no Node tooling);
  the Chromium check lives in the spike folder and moves into the plugin's
  E2E suite in Phase 5.
- 2026-10-16, Phase 1: the spike's compose file now mounts the kit worktree
  (`plugin-hooks`) instead of the kit checkout.
- 2026-10-16, Phase 2: no Brain Monkey. Own WordPress stubs in
  `tests/php/bootstrap.php` (like the kit's test); avoids Patchwork on PHP 8.5.
  Composer runs via the `composer:2` Docker image (not installed locally).
- 2026-10-16, Phase 2: kit subtree added from the local branch
  `plugin-hooks`, not from GitHub `main` (v1.3.0 is not pushed yet).
- 2026-10-16, Phase 2: without Divi 5 the plugin does not load the kit
  either (spec: "does nothing on the frontend"), so `.fx-*` classes typed by
  hand need Divi or a theme copy of the kit.
- 2026-10-16, Phase 2: the duplicate-kit notice says "another copy, usually
  from your theme", because a mu-plugin or other plugin can load it too.
- 2026-10-16, Phase 2: the spike WordPress now runs the plugin from this
  worktree (read-only mount in `wp-content/plugins/divi5-svg-filters`); the
  spike's own kit mu-plugin loader is disabled (`00-svg-filter-kit.php.off`).
- 2026-10-16, Phase 3: filters without a known category (e.g. a theme's own
  SVG files without metadata) go to "Custom", next to uploads. Text-type
  tuning values must be number lists; other text values are ignored (all 47
  kit hints qualify; uploads with non-numeric text hints cannot be tuned
  there).
- 2026-10-16, Phase 4: selectors use the order/preset class twice
  (`.et_pb_text_0.et_pb_text_0`, after Divi's own prefix) so the rules beat
  Divi's equal ones even if Divi's CSS loads later. To keep Divi's "later
  layer wins" between presets and module, every later layer with Divi
  filter values is restated with the raised selector (Divi's part only).
  Found in the spike: without this, a preset's SVG filter overrode a
  module's own Divi filter.
- 2026-10-16, Phase 4: the background-scope positioning rule
  (`position/isolation/overflow`) is wrapped in `:where()` so Divi's own
  Position settings always win.
- 2026-10-16, Phase 4: the Divi class guard runs on `init` (Divi's classes
  autoload only then, verified), not at plugin load; failing it shows a
  notice and leaves the frontend off.
- 2026-10-16, Phase 4: missing filters are recorded in the option
  `d5svgf_missing` (filter ID → post ID → time, written only on change) for
  the Phase 6 admin warning.
- 2026-10-16, Phase 4: an interactive filter set on a preset gets one copy
  per preset class, so elements sharing that preset share the light.
- 2026-10-17, Phase 5: `svgTune` is keyed per filter
  (`{ <filter id>: { <tweak key>: value } }`), so switching filters never
  carries values over. No `package.json`: `node --test` and the UMD
  `core.js` need no dependencies.
- 2026-10-17, Phase 5: the builder reads selector templates from Divi's JS
  module metadata and the module's own attrs from the edit-post store (both
  differ from the server, see findings). Option-group presets are not part
  of the builder preview yet.
- 2026-10-17, Phase 6: uploads live in one autoloaded option
  (`d5svgf_uploads`), not a custom post type: kses would strip SVG from post
  content for users without `unfiltered_html`, and the list stays small.
- 2026-10-17, Phase 6: upload IDs must not contain `--` (reserved for
  copies; also prevents a clash with kit classes like `.fx-blur--bg`), max
  64 characters. Files with a DOCTYPE or entities are rejected outright.
- 2026-10-17, Phase 6: deleting asks for confirmation (browser dialog).
  The import message does not list what the sanitizer removed.
- 2026-10-17, tuning controls (0.3.0, design addendum
  `docs/superpowers/specs/2026-10-17-tuning-controls-design.md`): the "▸ Tune"
  fold-out of layout "A + C" is replaced by a heading with presets (real
  Divi fields cannot sit inside a custom fold-out; a stored open flag would
  dirty the page). Duotone/Threshold get two color fields instead of the
  three dual sliders Markus suggested. Global colors are now allowed
  (revises the base spec's "plain hex only"). Angle wheel and 2D pad are
  left for later.
- 2026-10-17, Posterize channels (0.4.0): a "Channels" switch instead of
  always showing nine fields; each mode reads only its own fields, so the
  displayed values always match the output. Per-channel counts saved with
  0.3.0 (released the same day) are not migrated.
- 2026-10-17, intuitive controls (0.5.0): the dial and the 2D pad sit above
  Divi's sliders instead of replacing them (keeps Divi's numeric input). They
  keep keyboard focus in a visually hidden input: Divi's keyboard-shortcuts
  handler takes arrow keys from every element except inputs, textareas and
  contenteditable (ArrowUp/Down switched the selected module). Preset
  swatches are SVG data images, because inline SVG in a Divi button is
  restyled by the builder's icon CSS. Inner shadow's opacity moved into the
  color picker; values saved with the former slider still apply next to a
  plain hex color.
