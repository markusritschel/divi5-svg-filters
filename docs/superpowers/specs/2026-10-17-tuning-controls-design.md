# Tuning controls — design addendum

Date: 2026-10-17 · Status: implemented in plugin 0.3.0 / kit 1.4.0, Posterize channels and Threshold balance in 0.4.0 / kit 1.5.0; Markus can veto any point marked **(veto?)**

Found while building: Divi's color picker stores `rgba(…)` once the opacity
is below 100 % (accepted and normalized); on the frontend Divi's block parser
has already resolved `$variable(…)$` to `var(--gcid-…)` (accepted as well);
unitless sliders need `defaultUnit: ''`, otherwise Divi shows px.
Base spec: `2026-10-16-divi5-svg-filters-design.md` (goal 4, layout "A + C")

## Request (Markus, 2026-10-17)

Tuning values should feel like Divi's own fields: short labels with the long
explanation behind an info button, units shown in the field (`0 px`), Divi's
numeric input (spinner wheel, up/down), Divi's color fields with global
colors, and better controls for specific filters: Duotone/Threshold (instead
of three value lists), Posterize (levels), Water wave/Texture fill/RGB glitch
frequencies (two values), Bevel light direction (angle), Emboss (3×3 matrix
with presets), Inner shadow (like Divi's Box Shadow, with presets).

## Verified in the builder (spike, Divi 5.15.1)

- A field added through `divi.module.options.filters.group.fields` with a
  **nested `subName`** (`svgTune.fx-wave.feDisplacementMap|scale`) reads and
  writes `…filters.desktop.value.svgTune['fx-wave']['feDisplacementMap|scale']`.
  So every tuning value can be a **real Divi field**: label, info button with
  `description`, reset, right-click menu, Divi's numeric input.
- `divi/range` shows the unit separately when given a `cssProperty`; the unit
  follows the property: `filter:blur` → px, `transform:rotate` → deg,
  `transition-duration` → s, `filter:opacity` → %. Values are stored with the
  unit (`"40px"`).
- `divi/color-picker` with `features.dynamicContent.type = 'color'` gets the
  "Dynamic Content" button listing the global colors; a global color is stored
  as `$variable({"type":"color","value":{"name":"gcid-…","settings":{…}}})$`.
  Divi's `GlobalData::transform_state_into_global_color_value()` turns it into
  `var(--gcid-…)` or `hsl(from var(--gcid-…) …)`; Divi defines the variable on
  every page whose content mentions the ID.

## Decisions

1. **Real Divi fields** for all tuning values, generated per filter in the
   fields hook and visible only while that filter is selected.
2. **No fold-out (veto?)**: real fields cannot live inside a custom fold-out,
   and a stored open/closed flag would mark the page as changed and fill the
   undo history on every click. Instead a small heading "Tune ‹filter›" with a
   _Reset all_ link precedes the fields, like Divi shows Box Shadow's fields
   right after choosing a style. This replaces the "▸ Tune" fold-out of
   layout "A + C".
3. **Hint syntax 2 (kit 1.4.0)**, backwards compatible:
   `data-tweak-<attr>="[min, max unit] {type} Label | Description"`.
   Unit (`px`, `deg`, `s`, `%`), `{type}` and the description are optional;
   without `|` the whole text is the label. All kit hints get short labels.
   The kit's README and demo site (`docs` branch) follow.
4. **Colors**: every color is a Divi color field. Hex values go into the
   attribute; global colors go into the primitive's `style` as the CSS value
   Divi computes, so the filter follows later changes of the global color.
   This revises the base spec's "plain hex only in v1".
5. **Duotone and Threshold** are rebuilt in the kit with two `feFlood`
   colors ("Shadow color"/"Highlight color", "Dark ink"/"Light ink") blended
   by luminance (`feComposite in` + `over`) — the same linear blend as the old
   two-entry tables, but as real colors, so they get color pickers and
   global colors (instead of the three dual sliders suggested **(veto?)**).
6. **Control types** (from the hint's `{type}` or inferred):
   - number → `divi/range` (+ unit via `cssProperty`)
   - color → `divi/color-picker` with global colors
   - `{pair}` (two numbers, e.g. `baseFrequency`) → two sliders
     "… horizontal" / "… vertical"
   - `{levels}` (Posterize) → a "Channels" switch (All together / Each
     channel) with "Levels" (2–10), "Darkest" and "Brightest" once for all
     channels or per channel (0.4.0, Markus's follow-up); the list is spread
     evenly between darkest and brightest
   - `{matrix}` (Emboss) → custom field: 3×3 numeric grid
   - text → `divi/text` (other lists)
7. **Presets**: a filter can carry `data-presets` (JSON: name → tweak values)
   in the kit; the plugin shows them as one-click buttons above the fields.
   First users: Emboss (Emboss, Sharpen, Find edges, Soften) and Inner shadow
   (Soft, Hard, Wide, Top, Glow).
8. **Added in 0.5.0** (Markus's follow-up, "as intuitive as possible"):
   - dial above the slider for 0–360° angles (Bevel's light direction: the
     dot shows where the light comes from),
   - 2D pad above the two sliders for `{pair}`, with a noise swatch for
     turbulence frequencies,
   - preset buttons with a preview swatch (gradient tile with "Aa" through
     the filter, as an SVG data image),
   - a color and a [0, 1] `<name>-opacity` range on one primitive become one
     color field (`Tweaks::link`): Inner shadow's opacity is the picker's,
   - *Swap colors* for two-color filters; Duotone/Threshold presets (kit 1.6.0).

## Storage

`svgTune[<filter id>]` keeps one entry per **field**: `<tweak key>` for
simple controls (values may carry the unit, `"40px"`, or a `$variable(…)$`),
`<tweak key>@x` / `@y` for pairs; for levels the filter-level `@count` /
`@min` / `@max`, or with `@channels: 'each'` `<tweak key>@count` / `@min` /
`@max` (0.4.0; per-channel counts saved with 0.3.0 are dropped). PHP (`Tweaks::compose`) and JS (`core.compose`)
turn field values into attribute values and validate them as before. Tunings
saved with 0.1/0.2 for Duotone and Threshold no longer match a key and are
ignored (the filter shows its default colors); other keys stay valid.

## Per-filter review (0.7.0 / kit 1.8.0)

Each filter in the dropdown, checked against what it does: can a user set
the aspects that matter for that function, with labels that say what higher
values do, and without having to know SVG? Open for Markus's veto.

| Filter | What it does | Controls now | Verdict |
| --- | --- | --- | --- |
| Soft blur, Solid-edge blur | Blur | Blur radius (px) | Fits. |
| Duotone | Two colors by brightness | Shadow/Highlight color (global colors), presets with previews, *Swap colors* | Fits (0.3–0.5). |
| Threshold | Two inks, split by brightness | Balance, Dark/Light ink, presets, *Swap colors* | Fits; *Balance* added in 0.4 (a threshold without a threshold point before). |
| Posterize | Bands per channel | *Channels* switch, levels, darkest, brightest | Fits (0.4). |
| Color tint | Hue/saturation from a color | Tint color, its opacity = strength | Strength was missing; added in 0.7 (kit 1.8). |
| Film grain | Overlaid noise | Grain fineness, Grain strength | Two coupled weights replaced by one strength (0.6); "Grain size" was inverted (0.4). |
| Living grain | Flickering grain | Grain fineness, Grain strength, Flicker cycle (s) | Strength was fixed at 50 %; added in 0.7. |
| Paper relief | Lit fibre noise | Fibre fineness, Relief depth | Fits; label fixed in 0.4. |
| RGB glitch | Channel offsets + sliced bands | Red/Blue shift (px), slice frequency pad, slice displacement (px) | Fits; the pad's noise swatch shows the bands. |
| Rough edges | Displaced, busy edge | Jitter frequency, Edge roughness (px) | Fits; the live preview shows the effect. |
| Water wave | Ripple displacement | Ripple frequency pad, Distortion strength (px) | Fits (pad 0.5). |
| Gooey merge | Shapes fuse | Merge distance (px) | Fits; the edge sharpness stays fixed (one knob is the point of the effect). |
| Outline only | Ring around the shape | Outline width (px), Outline color | Fits. |
| Bevel | Glossy rim | Width (px), Depth, Highlight color, light direction dial | Fits (dial 0.5). |
| Emboss | 3×3 kernel | Kernel grid, presets with previews | Fits: presets cover the common kernels, the grid stays for experts. |
| Inner shadow | Inset shadow on text | Softness, offsets (px), color with opacity, presets | Fits; one opacity instead of two (0.5). |
| Texture fill | Noise texture inside text | Vein frequency pad, Pattern variant, Vein/Base color, presets | Colors were fixed (multicolor matrix); now two colors with presets (0.7, look changes). |
| Flowing water, Hue cycle | Animations | Flow cycle length / Cycle time (s), Distortion strength (px) | Fits; Markus approved the controls (2026-10-18), *Flow cycle length* renamed at his request. |
| Hover melt | Melts on hover | Melt frequency pad | Fits; the hover strength (45) lives in the kit's script, not tunable (limitation). |
| Pointer light | Light follows the pointer | Light color, Surface bumpiness, Light height (px) | Fits. |
| Refractive glass | Glass pane | — | Not offered in the plugin (category `glass`). |

Considered and left out: a noise swatch next to single frequency sliders
(the builder's live preview already shows the effect at the real size).
