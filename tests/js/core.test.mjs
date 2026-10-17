// Pure builder logic (assets/core.js): mirrors includes/Tweaks.php and the layer logic of includes/Frontend.php.
// Run: node --test tests/js/
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { createRequire } from 'node:module';

const core = createRequire(import.meta.url)('../../assets/core.js');

// Controls as includes/Tweaks.php control() produces them (window.D5SVGF.filters[…].controls).
const blur = { key: 'feGaussianBlur|stdDeviation', attr: 'stdDeviation', type: 'range', default: 8, min: 0, max: 30, step: 0.1, unit: 'px', suffix: '' };
const dur = { key: 'animate|dur', attr: 'dur', type: 'range', default: 0.8, min: 0.2, max: 3, step: 0.01, unit: 's', suffix: 's' };
const color = { key: 'feFlood|flood-color', attr: 'flood-color', type: 'color', default: '#000000' };
const pair = { key: 'feTurbulence|baseFrequency', attr: 'baseFrequency', type: 'pair', default: '.008 .03', default_x: 0.008, default_y: 0.03, min: 0, max: 0.1, step: 0.001, unit: '', suffix: '' };
const levels = { key: 'feFuncR|tableValues', attr: 'tableValues', type: 'levels', default: '0 .35 .7 1', count: 4, lo: 0, hi: 1 };
const matrix = { key: 'feConvolveMatrix|kernelMatrix', attr: 'kernelMatrix', type: 'matrix', default: '-2 -1 0 -1 1 1 0 1 2' };
const text = { key: 'feFuncA|tableValues', attr: 'tableValues', type: 'text', default: '0.1 0.9' };
const gc = '$variable({"type":"color","value":{"name":"gcid-primary-color","settings":{}}})$';
const gcs = '$variable({"type":"color","value":{"name":"gcid-primary-color","settings":{"lightness":-10,"opacity":50}}})$';
const v = (value) => ({ value, style: null });

test('compose mirrors Tweaks::compose (same cases as TweaksTest)', () => {
  const cases = [
    [blur, { 'feGaussianBlur|stdDeviation': '12px' }, v('12')],
    [blur, { 'feGaussianBlur|stdDeviation': '12' }, v('12')],
    [blur, { 'feGaussianBlur|stdDeviation': '99px' }, v('30')],
    [blur, { 'feGaussianBlur|stdDeviation': '2em' }, null],
    [blur, { 'feGaussianBlur|stdDeviation': '8px' }, null],
    [dur, { 'animate|dur': '1.5s' }, v('1.5s')],
    [color, { 'feFlood|flood-color': '#FF0000' }, v('#ff0000')],
    [color, { 'feFlood|flood-color': gc }, { value: null, style: 'flood-color:var(--gcid-primary-color)' }],
    [color, { 'feFlood|flood-color': gcs }, { value: null, style: 'flood-color:hsl(from var(--gcid-primary-color) calc(h + 0) calc(s + 0) calc(l - 10) / 0.5)' }],
    [color, { 'feFlood|flood-color': '$variable({"type":"color","value":{"name":"x;}body{"}})$' }, null],
    [color, { 'feFlood|flood-color': '#fff" onload="x' }, null],
    [color, { 'feFlood|flood-color': 'var(--gcid-primary-color)' }, { value: null, style: 'flood-color:var(--gcid-primary-color)' }],
    [color, { 'feFlood|flood-color': 'hsl(from var(--gcid-primary-color) calc(h + 0) calc(s + 0) calc(l - 10) / 0.5)' }, { value: null, style: 'flood-color:hsl(from var(--gcid-primary-color) calc(h + 0) calc(s + 0) calc(l - 10) / 0.5)' }],
    [color, { 'feFlood|flood-color': 'var(--wp-admin-color)' }, null],
    [color, { 'feFlood|flood-color': 'var(--gcid-x);x:url(y)' }, null],
    [color, { 'feFlood|flood-color': 'rgba(51, 102, 153, 0.5)' }, v('rgba(51, 102, 153, 0.5)')],
    [color, { 'feFlood|flood-color': 'rgb(51,102,153)' }, v('rgba(51, 102, 153, 1)')],
    [color, { 'feFlood|flood-color': 'rgba(300, 0, 0, 0.5)' }, null],
    [color, { 'feFlood|flood-color': 'rgba(1, 2, 3, 0.5);x:y' }, null],
    [pair, { 'feTurbulence|baseFrequency@x': '0.02', 'feTurbulence|baseFrequency@y': '0.05' }, v('0.02 0.05')],
    [pair, { 'feTurbulence|baseFrequency@y': '0.09' }, v('0.008 0.09')],
    [pair, { 'feTurbulence|baseFrequency@x': '5' }, v('0.1 0.03')],
    [pair, { 'feTurbulence|baseFrequency': '0.02 0.04' }, v('0.02 0.04')],
    [levels, { '@count': '3' }, v('0 0.5 1')],
    [levels, { '@count': '3', '@min': '0.2', '@max': '0.8' }, v('0.2 0.5 0.8')],
    [levels, { '@max': '0.6' }, v('0 0.2 0.4 0.6')],
    [levels, { '@count': '99' }, v('0 0.111111 0.222222 0.333333 0.444444 0.555556 0.666667 0.777778 0.888889 1')],
    [levels, { '@channels': 'each', 'feFuncR|tableValues@count': '3', 'feFuncR|tableValues@min': '0.2', 'feFuncR|tableValues@max': '0.8' }, v('0.2 0.5 0.8')],
    [levels, { '@channels': 'each', '@count': '3', 'feFuncR|tableValues@max': '0.6' }, v('0 0.2 0.4 0.6')],
    [levels, { '@channels': 'each', '@count': '3' }, null],
    [levels, { 'feFuncR|tableValues@count': '3' }, null],
    [levels, {}, null],
    [levels, { 'feFuncR|tableValues': '0 1' }, v('0 1')],
    [matrix, { 'feConvolveMatrix|kernelMatrix': '0 -1 0 -1 5 -1 0 -1 0' }, v('0 -1 0 -1 5 -1 0 -1 0')],
    [matrix, { 'feConvolveMatrix|kernelMatrix': '1 2 3' }, null],
    [matrix, { 'feConvolveMatrix|kernelMatrix': '-2 -1 0  -1 1 1  0 1 2' }, null],
    [blur, { 'feGaussianBlur|stdDeviation': ['1'] }, null],
    [text, { 'feFuncA|tableValues': ' 0.2   0.8 ' }, v('0.2 0.8')],
    [text, { 'feFuncA|tableValues': '0.2 "><script>' }, null],
  ];
  for (const [control, tune, expected] of cases) {
    assert.deepEqual(core.compose(control, tune), expected, `${control.type} ${JSON.stringify(tune)}`);
  }
});

test('composeAll keeps changed controls only, sorted by key', () => {
  const filter = { controls: [text, blur] };
  assert.deepEqual(core.composeAll(filter, { 'feGaussianBlur|stdDeviation': '8px', 'feFuncA|tableValues': '0.2 0.8', 'x|y': '1' }), { 'feFuncA|tableValues': v('0.2 0.8') });
  assert.deepEqual(core.composeAll(filter, null), {});
});

test('presetFields turns preset attribute values into field values', () => {
  const filter = { controls: [blur, color, pair, levels, matrix, dur] };
  assert.deepEqual(core.presetFields(filter, {
    'feGaussianBlur|stdDeviation': '6',
    'feFlood|flood-color': '#FFFFFF',
    'feTurbulence|baseFrequency': '0.02 0.05',
    'feFuncR|tableValues': '0.2 0.5 0.8',
    'feConvolveMatrix|kernelMatrix': '0 -1 0  -1 5 -1  0 -1 0',
    'animate|dur': '1.5s',
    'unknown|attr': '1',
  }), {
    'feGaussianBlur|stdDeviation': '6px',
    'feFlood|flood-color': '#ffffff',
    'feTurbulence|baseFrequency@x': '0.02',
    'feTurbulence|baseFrequency@y': '0.05',
    '@channels': 'each',
    'feFuncR|tableValues@count': '3',
    'feFuncR|tableValues@min': '0.2',
    'feFuncR|tableValues@max': '0.8',
    'feConvolveMatrix|kernelMatrix': '0 -1 0 -1 5 -1 0 -1 0',
    'animate|dur': '1.5s',
  });
});

test('copyId is stable and depends on values and salt', () => {
  const a = core.copyId('fx-blur', { 'k|a': '1' }, '');
  assert.match(a, /^fx-blur--v[0-9a-f]{8}$/);
  assert.equal(a, core.copyId('fx-blur', { 'k|a': '1' }, ''));
  assert.notEqual(a, core.copyId('fx-blur', { 'k|a': '2' }, ''));
  assert.notEqual(a, core.copyId('fx-blur', { 'k|a': '1' }, 'et_pb_text_x'));
});

const templates = { module: '{{selector}}', content: '{{selector}} .et_pb_text_inner' };
const filters = (value, more = {}) => ({ module: { decoration: { filters: { desktop: { value, ...more } } } } });
const has = (id) => ['fx-wave', 'fx-duotone', 'fx-spotlight'].includes(id);
const copy = (id, tune, salt) => (salt || (tune && Object.keys(tune).length) ? { id: `${id}--copy${salt ? '-' + salt : ''}` } : null);
const plan = (layers) => core.plan({ layers, templates, has, copy, interactive: { 'fx-spotlight': 'spotlight' }, functions: ['hueRotate', 'saturate', 'brightness', 'contrast', 'invert', 'sepia', 'opacity', 'blur'] });

test('plan: element rule with raised selector, sub-elements, none and missing', () => {
  const p = plan([{ cls: 'm1', attrs: {
    module: { decoration: { filters: { desktop: { value: { svgFilter: 'fx-wave', blur: '2px' } } } } },
    content: { decoration: { filters: { desktop: { value: { svgFilter: 'fx-gone' } } } } },
  } }]);
  assert.deepEqual(p.chunks.map((c) => [c.kind, c.selector, c.filterId]), [['element', '.m1.m1', 'fx-wave']]);
  assert.deepEqual(p.missing, ['fx-gone']);
  assert.equal(plan([{ cls: 'm1', attrs: filters({ svgFilter: 'none', blur: '2px' }) }]).chunks.length, 0);
});

test('plan: tuning is read per filter', () => {
  const p = plan([{ cls: 'm1', attrs: filters({ svgFilter: 'fx-duotone', svgTune: { 'fx-duotone': { a: '1' }, 'fx-wave': { b: '2' } } }) }]);
  assert.equal(p.chunks[0].filterId, 'fx-duotone--copy');
  assert.equal(p.chunks[0].tune.a, '1');
});

test('plan: background scope, interactive, later layers restated', () => {
  const bg = plan([{ cls: 'm1', attrs: filters({ svgFilter: 'fx-wave', svgScope: 'background', blur: '1px' }) }]);
  assert.deepEqual(bg.chunks.map((c) => c.kind), ['background']);

  const spot = plan([{ cls: 'm1', attrs: filters({ svgFilter: 'fx-spotlight' }) }]);
  assert.deepEqual(spot.interactive, ['spotlight', 'fx-spotlight--copy-m1']);

  const layered = plan([
    { cls: 'p1', shared: true, attrs: filters({ svgFilter: 'fx-wave', sepia: '40%' }) },
    { cls: 'm1', attrs: filters({ blur: '3px' }) },
  ]);
  // The preset's SVG filter stays, the module's own Divi values replace the preset's (decision 2026-10-17).
  assert.deepEqual(layered.chunks.map((c) => [c.kind, c.selector, c.filterId]), [['element', '.p1.p1', 'fx-wave'], ['element', '.m1.m1', 'fx-wave']]);
  assert.equal(layered.chunks[1].filters.desktop.value.blur, '3px');

  const plain = plan([{ cls: 'p1', shared: true, attrs: filters({ sepia: '40%' }) }, { cls: 'm1', attrs: filters({ blur: '3px' }) }]);
  assert.equal(plain.chunks.length, 0, 'nothing of ours, nothing restated');
});

test('plan: a layer can raise its selector further (builder: module above shared preset rules)', () => {
  const p = plan([
    { cls: 'p1', shared: true, attrs: filters({ svgFilter: 'fx-wave' }) },
    { cls: 'm1', repeat: 3, attrs: filters({ blur: '3px' }) },
  ]);
  assert.deepEqual(p.chunks.map((c) => c.selector), ['.p1.p1', '.m1.m1.m1']);
});

test('plan: none switches an inherited SVG filter off, own SVG filter replaces it', () => {
  const preset = { cls: 'p1', shared: true, attrs: filters({ svgFilter: 'fx-wave', sepia: '40%' }) };
  const kinds = (p) => p.chunks.slice(1).map((c) => [c.kind, c.selector, c.filterId || '', c.filters ? JSON.stringify(c.filters.desktop.value) : '']);

  assert.deepEqual(kinds(plan([preset, { cls: 'm1', attrs: filters({ svgFilter: 'none', blur: '3px' }) }])),
    [['divi', '.m1.m1', '', '{"svgFilter":"none","blur":"3px"}']], 'own Divi values only');
  assert.deepEqual(kinds(plan([preset, { cls: 'm1', attrs: filters({ svgFilter: 'none' }) }])),
    [['divi', '.m1.m1', '', '{"svgFilter":"fx-wave","sepia":"40%"}']], "the preset's Divi values without the SVG filter");
  assert.deepEqual(kinds(plan([{ cls: 'p1', shared: true, attrs: filters({ svgFilter: 'fx-wave' }) }, { cls: 'm1', attrs: filters({ svgFilter: 'none' }) }])),
    [['none', '.m1.m1', '', '']], 'nothing left: filter:none');
  assert.deepEqual(kinds(plan([preset, { cls: 'm1', attrs: filters({ svgFilter: 'fx-duotone' }) }])),
    [['element', '.m1.m1', 'fx-duotone', '{"svgFilter":"fx-duotone"}']]);
});

test('templates keep plain single selectors only (as Frontend::selector_templates)', () => {
  assert.deepEqual(core.templates({
    module: { selector: '{{selector}}' },
    content: { selector: '{{selector}} .et_pb_text_inner' },
    title: { selector: { desktop: '{{selector}} h1' } },
    list: { selector: '{{selector}} li, {{selector}} p' },
    other: { selector: '.foo' },
    none: {},
  }), { module: '{{selector}}', content: '{{selector}} .et_pb_text_inner' });
  assert.deepEqual(core.templates(null), {});
});

test('backgroundCss matches the PHP rules', () => {
  const s = '.m1.m1';
  assert.equal(
    core.backgroundCss(s, 'fx-wave', 'fx-wave', ['fx-wave']),
    ":where(.m1.m1){position:relative;isolation:isolate;overflow:hidden}"
      + ".m1.m1::before{content:'';position:absolute;inset:-5%;z-index:-1;background:inherit;filter:url(#fx-wave);pointer-events:none}"
      + '.m1.m1 .et_parallax_bg,.m1.m1 .et-pb-parallax-background{filter:url(#fx-wave)}',
  );
});
