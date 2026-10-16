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
  `docs/spike-findings.md` → "Presets".
