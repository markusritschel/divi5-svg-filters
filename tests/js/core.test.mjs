// Pure builder logic (assets/core.js): mirrors includes/Tweaks.php and the layer logic of includes/Frontend.php.
// Run: node --test tests/js/
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { createRequire } from 'node:module';

const core = createRequire(import.meta.url)('../../assets/core.js');

const range = { key: 'feGaussianBlur|stdDeviation', type: 'range', default: 8, min: 0, max: 30, step: 0.1, unit: '' };
const dur = { key: 'animate|dur', type: 'range', default: 0.65, min: 0.2, max: 3, step: 0.01, unit: 's' };
const color = { key: 'feFlood|flood-color', type: 'color', default: '#ffe6b8' };
const text = { key: 'feFuncR|tableValues', type: 'text', default: '0.10 0.99' };

test('sanitize mirrors the PHP rules', () => {
  assert.equal(core.sanitize(color, '#AABBCC'), '#aabbcc');
  assert.equal(core.sanitize(color, '#abc'), null);
  assert.equal(core.sanitize(range, '99'), '30');
  assert.equal(core.sanitize(range, '-4'), '0');
  assert.equal(core.sanitize(range, 0.1 + 0.2), '0.3');
  assert.equal(core.sanitize(range, '8px; x'), null);
  assert.equal(core.sanitize(dur, '1.5'), '1.5s');
  assert.equal(core.sanitize(dur, '2s'), '2s');
  assert.equal(core.sanitize(text, ' 0.2   0.8 '), '0.2 0.8');
  assert.equal(core.sanitize(text, '0 -1 1e-2'), '0 -1 1e-2');
  assert.equal(core.sanitize(text, '0.2 "><script>'), null);
  assert.equal(core.sanitize(text, '1 '.repeat(200)), null);
  assert.equal(core.sanitize(text, ['1']), null);
});

test('tuneValues keeps valid non-default values, sorted by key', () => {
  const filter = { controls: [text, range] };
  assert.deepEqual(core.tuneValues(filter, { 'feGaussianBlur|stdDeviation': '8', 'feFuncR|tableValues': '0.2 0.8', 'x|y': '1' }), { 'feFuncR|tableValues': '0.2 0.8' });
  assert.deepEqual(core.tuneValues(filter, null), {});
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
const plan = (layers) => core.plan({ layers, templates, has, copy, interactive: { 'fx-spotlight': 'spotlight' } });

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
  assert.deepEqual(layered.chunks.map((c) => [c.kind, c.selector, c.filterId]), [['element', '.p1.p1', 'fx-wave'], ['divi', '.m1.m1', '']]);

  const plain = plan([{ cls: 'p1', shared: true, attrs: filters({ sepia: '40%' }) }, { cls: 'm1', attrs: filters({ blur: '3px' }) }]);
  assert.equal(plain.chunks.length, 0, 'nothing of ours, nothing restated');
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
