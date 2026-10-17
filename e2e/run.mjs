// Browser tests against the local WordPress + Divi from e2e/setup.sh.
// Run: cd e2e && pnpm install && pnpm test   (CHROMIUM_PATH=/usr/bin/chromium to use a system browser)
import { test, before, after } from 'node:test';
import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { homedir } from 'node:os';
import { fileURLToPath } from 'node:url';
import { chromium } from 'playwright';

const DIR = fileURLToPath(new URL('.', import.meta.url));
const BASE = `http://localhost:${process.env.E2E_PORT || 8086}`;
const MOD = '.et_pb_text.et_pb_module';
const env = { ...process.env, DIVI_THEME_DIR: process.env.DIVI_THEME_DIR || `${process.env.XDG_CACHE_HOME || homedir() + '/.cache'}/d5svgf-e2e/Divi` };
const wp = (...args) => execFileSync('docker', ['compose', 'run', '--rm', '-T', 'cli', 'wp', ...args], { cwd: DIR, env, encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'] }).trim();
const pageId = (slug) => wp('post', 'list', '--post_type=page', `--name=${slug}`, '--field=ID');
const resetPage = (slug) => wp('post', 'update', pageId(slug), `/fixtures/${slug}.html`);

let browser, context;

before(async () => {
  browser = await chromium.launch(process.env.CHROMIUM_PATH ? { executablePath: process.env.CHROMIUM_PATH } : {});
  context = await browser.newContext({ viewport: { width: 1280, height: 1200 } });
  const page = await context.newPage();
  await page.goto(`${BASE}/wp-login.php`);
  await page.fill('#user_login', 'admin');
  await page.fill('#user_pass', 'admin');
  await page.click('#wp-submit');
  await page.waitForURL(/wp-admin/);
  await page.close();
});

after(async () => browser && browser.close());

/** Frontend page; returns the page and collected console warnings/errors. */
async function frontend(slug, width = 1280) {
  const page = await context.newPage();
  await page.setViewportSize({ width, height: 1200 });
  const logs = [];
  page.on('console', (m) => ['warning', 'error'].includes(m.type()) && logs.push(`${m.type()}: ${m.text()}`));
  page.on('pageerror', (e) => logs.push(`pageerror: ${e}`));
  await page.goto(`${BASE}/${slug}/`, { waitUntil: 'networkidle' });
  return { page, logs };
}

const style = (page, sel, pseudo = null) => page.locator(sel).first().evaluate((e, p) => getComputedStyle(e, p).filter, pseudo);

/** Visual Builder for a page; returns top page and app frame. */
async function builder(slug) {
  const page = await context.newPage();
  await page.setViewportSize({ width: 1600, height: 1000 });
  const errors = [];
  page.on('pageerror', (e) => errors.push(String(e)));
  await page.goto(`${BASE}/${slug}/?et_fb=1&PageSpeed=off`, { waitUntil: 'domcontentloaded' });
  const app = await (await page.waitForSelector('iframe#et-vb-app-frame', { timeout: 120000 })).contentFrame();
  await app.waitForSelector(MOD, { timeout: 120000 });
  await page.waitForTimeout(2500);
  const appStyle = (i, pseudo = null) => app.evaluate(([sel, i, p]) => getComputedStyle(document.querySelectorAll(sel)[i], p).filter, [MOD, i, pseudo]);
  return { page, app, errors, appStyle };
}

test('frontend: combined declaration follows Divi at every breakpoint and on hover', async () => {
  const expect = {
    1280: 'url("#fx-duotone") saturate(1.5) blur(1px)',
    900: 'url("#fx-duotone") saturate(0.5) blur(1px)',
    400: 'url("#fx-duotone") saturate(0.5) blur(1px)',
  };
  for (const [width, filter] of Object.entries(expect)) {
    const { page } = await frontend('e2e-filters', Number(width));
    assert.equal(await style(page, '.et_pb_text_0'), filter, `${width}px`);
    await page.locator('.et_pb_text_0').hover();
    assert.equal(await style(page, '.et_pb_text_0'), 'url("#fx-duotone") saturate(1.5) brightness(1.2) blur(1px)', `${width}px hover`);
    await page.close();
  }
});

test('frontend: copies, background scope, sub-element, missing filter, Divi-only', async () => {
  const { page, logs } = await frontend('e2e-filters');
  const t1 = await style(page, '.et_pb_text_1');
  assert.match(t1, /^url\("#fx-duotone--[0-9a-f]{8}"\)$/);
  const copyId = t1.slice(6, -2);
  assert.equal(await page.evaluate((id) => document.getElementById(id)?.querySelector('feFlood')?.getAttribute('flood-color'), copyId), '#e61919');

  assert.equal(await style(page, '.et_pb_text_2'), 'none');
  assert.equal(await style(page, '.et_pb_text_2', '::before'), 'url("#fx-wave")');
  assert.equal(await style(page, '.et_pb_text_5 .et_pb_text_inner'), 'url("#fx-blur") saturate(0.4)');
  assert.equal(await style(page, '.et_pb_text_6'), 'sepia(0.5)');
  assert.equal(await style(page, '.et_pb_text_7'), 'contrast(1.3)');
  assert.ok(logs.some((l) => l.includes('fx-not-there')), 'console warning for the missing filter');
  assert.deepEqual(logs.filter((l) => l.startsWith('pageerror') || l.startsWith('error')), []);
  await page.close();
});

test('frontend: spotlight elements have independent lights', async () => {
  const { page } = await frontend('e2e-filters');
  const a = await page.locator('.et_pb_text_3').getAttribute('data-svgf-spotlight');
  const b = await page.locator('.et_pb_text_4').getAttribute('data-svgf-spotlight');
  assert.ok(a && b && a !== b);
  const light = (id) => page.evaluate((id) => { const l = document.getElementById(id).getElementsByTagName('fePointLight')[0]; return `${l.getAttribute('x')},${l.getAttribute('y')}`; }, id);
  const before = await light(b);
  const box = await page.locator('.et_pb_text_3').boundingBox();
  await page.mouse.move(box.x + 30, box.y + 10);
  assert.equal(await light(a), '30.0,10.0');
  assert.equal(await light(b), before);
  await page.close();
});

test('presets: the preset\'s SVG filter stays with own Divi values, None switches it off (frontend and builder)', async () => {
  const expected = ['url("#fx-grain") sepia(0.4)', 'url("#fx-grain") blur(3px)', 'sepia(0.4)'];
  const { page } = await frontend('e2e-presets');
  for (const [i, filter] of expected.entries()) assert.equal(await style(page, `.et_pb_text_${i}`), filter, `frontend ${i}`);
  await page.close();

  const vb = await builder('e2e-presets');
  for (const [i, filter] of expected.entries()) assert.equal(await vb.appStyle(i), filter, `builder ${i}`);
  await vb.page.close();
});

test('builder: fields, live preview, tuning, scope, save', async () => {
  const vb = await builder('e2e-filters');
  try {
    const { page, app, appStyle } = vb;
    assert.equal(await appStyle(0), 'url("#fx-duotone") saturate(1.5) blur(1px)', 'preview = frontend');

    await app.locator(MOD).nth(0).click();
    await page.waitForTimeout(1500);
    await page.getByRole('tab', { name: 'Design' }).or(page.locator('text=Design')).first().click();
    await page.waitForTimeout(800);
    await page.locator('text=Filters').first().click();
    await page.waitForTimeout(1000);
    const select = page.locator('#et-vb-module-decoration-filters--svgFilter');
    await select.scrollIntoViewIfNeeded();

    await select.click();
    await select.locator('li[data-value="fx-grain"]').click();
    await page.waitForTimeout(800);
    assert.equal(await appStyle(0), 'url("#fx-grain") saturate(1.5) blur(1px)', 'live switch');

    await select.click();
    await select.locator('li[data-value="fx-duotone"]').click();
    await page.waitForTimeout(800);
    // Shadow color through Divi's color field.
    const shadow = page.locator('.et-vb-field[class*="d5svgfTune_fx-duotone_0"] input[type="text"]').first();
    await shadow.scrollIntoViewIfNeeded();
    await shadow.fill('ff0000');
    await shadow.press('Enter');
    await page.waitForTimeout(1000);
    const tuned = await appStyle(0);
    assert.match(tuned, /^url\("#fx-duotone--v[0-9a-f]{8}"\) saturate\(1\.5\) blur\(1px\)$/, 'tuned copy');
    assert.equal(await app.evaluate((id) => document.getElementById(id).querySelector('feFlood').getAttribute('flood-color'), tuned.slice(6, tuned.indexOf('")'))), '#ff0000');

    await page.locator('.et-vb-field-module-decoration-filters-svg-scope button[data-name="background"]').click();
    await page.waitForTimeout(800);
    assert.equal(await appStyle(0), 'saturate(1.5) blur(1px)');
    assert.match(await appStyle(0, '::before'), /^url\("#fx-duotone--v[0-9a-f]{8}"\)$/);

    await page.locator('button:has-text("Save")').first().click();
    await page.waitForTimeout(6000);
    assert.deepEqual(vb.errors, []);

    const fe = await frontend('e2e-filters');
    assert.equal(await style(fe.page, '.et_pb_text_0'), 'saturate(1.5) blur(1px)');
    assert.match(await style(fe.page, '.et_pb_text_0', '::before'), /^url\("#fx-duotone--[0-9a-f]{8}"\)$/);
    await fe.page.close();
  } finally {
    await vb.page.close();
    resetPage('e2e-filters');
  }
});

test('builder: presets, levels, global colors (builder and frontend)', async () => {
  const vb = await builder('e2e-filters');
  try {
    const { page, app, appStyle } = vb;
    const copyOf = async () => {
      const f = await appStyle(0);
      const m = /url\("#([^"]+)"\)/.exec(f);
      return m ? m[1] : null;
    };
    const attrIn = (id, sel, attr) => app.evaluate(([id, sel, attr]) => document.getElementById(id)?.querySelector(sel)?.getAttribute(attr), [id, sel, attr]);
    const choose = async (filterId) => {
      const select = page.locator('#et-vb-module-decoration-filters--svgFilter');
      await select.scrollIntoViewIfNeeded();
      await select.click();
      await select.locator(`li[data-value="${filterId}"]`).click();
      await page.waitForTimeout(900);
    };

    await app.locator(MOD).nth(0).click();
    await page.waitForTimeout(1500);
    await page.getByRole('tab', { name: 'Design' }).or(page.locator('text=Design')).first().click();
    await page.waitForTimeout(800);
    await page.locator('text=Filters').first().click();
    await page.waitForTimeout(1000);

    await choose('fx-emboss');
    await page.locator('.d5svgf-presets button', { hasText: 'Sharpen' }).click();
    await page.waitForTimeout(800);
    assert.equal(await attrIn(await copyOf(), 'feConvolveMatrix', 'kernelMatrix'), '0 -1 0 -1 5 -1 0 -1 0', 'preset Sharpen');
    assert.equal(await page.locator('.d5svgf-matrix input').nth(4).inputValue(), '5', 'matrix shows the preset');

    await choose('fx-inner-shadow');
    await page.locator('.d5svgf-presets button', { hasText: 'Glow' }).click();
    await page.waitForTimeout(800);
    const glow = await copyOf();
    assert.equal(await attrIn(glow, 'feFlood', 'flood-color'), 'rgba(255, 255, 255, 0.8)', 'preset Glow color with its opacity');
    assert.equal(await attrIn(glow, 'feFlood', 'flood-opacity'), '1', 'opacity carried by the color');
    assert.equal(await page.locator('.et-vb-field', { hasText: /Shadow opacity/i }).count(), 0, 'no separate opacity slider');
    assert.equal(await attrIn(glow, 'feGaussianBlur', 'stdDeviation'), '8', 'preset Glow softness');

    // Posterize fields: 0 Channels, 1–3 shared count/darkest/brightest, 4–6 red, 7–9 green, 10–12 blue.
    await choose('fx-posterize');
    const posterField = (i) => page.locator(`.et-vb-field:is([class*="d5svgfTune_fx-posterize_${i} "], [class$="d5svgfTune_fx-posterize_${i}"])`).first();
    const setLevel = async (i, value) => {
      const input = posterField(i).locator('.et-vb-numeric-input-text input').first();
      await input.scrollIntoViewIfNeeded();
      await input.fill(value);
      await input.press('Enter');
      await page.waitForTimeout(800);
    };
    await setLevel(1, '2');
    let poster = await copyOf();
    assert.equal(await attrIn(poster, 'feFuncR', 'tableValues'), '0 1', 'all channels: red');
    assert.equal(await attrIn(poster, 'feFuncB', 'tableValues'), '0 1', 'all channels: blue');
    assert.equal(await posterField(4).isVisible(), false, 'per-channel fields hidden');
    await posterField(0).locator('button[data-name="each"]').click();
    await page.waitForTimeout(800);
    assert.equal(await posterField(1).isVisible(), false, 'shared fields hidden');
    await setLevel(6, '0.5');
    poster = await copyOf();
    assert.equal(await attrIn(poster, 'feFuncR', 'tableValues'), '0 0.166667 0.333333 0.5', 'each channel: red brightest');
    assert.equal(await attrIn(poster, 'feFuncG', 'tableValues'), '0 .35 .7 1', 'each channel: green untouched');

    await choose('fx-wave');
    const pad = page.locator('.d5svgf-pad');
    await pad.scrollIntoViewIfNeeded();
    const box = await pad.boundingBox();
    await pad.click({ position: { x: box.width / 2, y: box.height / 4 } });
    await page.waitForTimeout(800);
    // Clicks land on whole pixels: allow a step either way.
    const [px, py] = (await attrIn(await copyOf(), 'feTurbulence', 'baseFrequency')).split(' ').map(Number);
    assert.ok(Math.abs(px - 0.05) <= 0.002 && Math.abs(py - 0.075) <= 0.002, `pad sets both frequencies (${px} ${py})`);
    await pad.press('ArrowRight');
    await page.waitForTimeout(800);
    const stepped = `${Math.round((px + 0.001) * 1000) / 1000} ${py}`;
    assert.equal(await attrIn(await copyOf(), 'feTurbulence', 'baseFrequency'), stepped, 'arrow key steps');
    const horiz = page.locator('.et-vb-field:is([class*="d5svgfTune_fx-wave_1 "], [class$="d5svgfTune_fx-wave_1"]) .et-vb-numeric-input-text input').first();
    assert.equal(await horiz.inputValue(), stepped.split(' ')[0], 'slider follows the pad');
    assert.equal(await page.locator('.d5svgf-pad__noise feTurbulence').getAttribute('baseFrequency'), stepped, 'noise swatch follows');

    await choose('fx-duotone');
    await page.locator('.d5svgf-presets button', { hasText: 'Sepia' }).click();
    await page.waitForTimeout(800);
    let duo = await copyOf();
    assert.deepEqual([await attrIn(duo, 'feFlood', 'flood-color'), await attrIn(duo, 'feFlood:nth-of-type(2)', 'flood-color')], ['#2e1d0f', '#f2dfc0'], 'preset Sepia');
    await page.locator('.d5svgf-tune-head__swap').click();
    await page.waitForTimeout(800);
    duo = await copyOf();
    assert.deepEqual([await attrIn(duo, 'feFlood', 'flood-color'), await attrIn(duo, 'feFlood:nth-of-type(2)', 'flood-color')], ['#f2dfc0', '#2e1d0f'], 'swapped');
    const shadowField = page.locator('.et-vb-field[class*="d5svgfTune_fx-duotone_0"]').first();
    await shadowField.scrollIntoViewIfNeeded();
    await shadowField.hover();
    await shadowField.locator('button[aria-label="Dynamic Content Button"]').click();
    await page.waitForTimeout(600);
    await page.locator('li, [role="option"], [class*="item"]').filter({ hasText: /^Primary Color/ }).first().click();
    await page.waitForTimeout(1000);
    const global = await copyOf();
    assert.equal(await attrIn(global, 'feFlood', 'style'), 'flood-color:var(--gcid-primary-color)', 'global color as CSS variable');
    const primaryInBuilder = await app.evaluate((id) => getComputedStyle(document.getElementById(id).querySelector('feFlood')).floodColor, global);

    await page.locator('button:has-text("Save")').first().click();
    await page.waitForTimeout(6000);
    assert.deepEqual(vb.errors, []);

    const fe = await frontend('e2e-filters');
    const f = await style(fe.page, '.et_pb_text_0');
    const feCopy = /url\("#([^"]+)"\)/.exec(f)[1];
    const flood = await fe.page.evaluate((id) => {
      const el = document.getElementById(id).querySelector('feFlood');
      return { style: el.getAttribute('style'), computed: getComputedStyle(el).floodColor };
    }, feCopy);
    assert.equal(flood.style, 'flood-color:var(--gcid-primary-color)');
    assert.equal(flood.computed, primaryInBuilder, 'the global color resolves to the same color on the frontend');
    assert.notEqual(flood.computed, 'rgb(26, 31, 97)', 'not the default shadow color');
    await fe.page.close();
  } finally {
    await vb.page.close();
    resetPage('e2e-filters');
  }
});

test('uploads: import, sanitize, use, delete', async () => {
  const page = await context.newPage();
  const notices = () => page.evaluate(() => [...document.querySelectorAll('.wrap .notice')].map((n) => n.textContent.replace(/\s+/g, ' ').trim()));
  try {
    await page.goto(`${BASE}/wp-admin/tools.php?page=d5svgf`);
    await page.setInputFiles('input[name="d5svgf_file"]', `${DIR}fixtures/upload-good.svg`);
    await page.click('form[enctype] button[type="submit"]');
    await page.waitForLoadState('networkidle');
    let n = await notices();
    assert.ok(n.some((t) => t.startsWith('Imported: e2e-glow')), n.join(' | '));
    assert.ok(n.some((t) => t.startsWith('Skipped fx-duotone')), 'collision with a kit ID');

    await page.setInputFiles('input[name="d5svgf_file"]', `${DIR}fixtures/upload-evil.svg`);
    await page.click('form[enctype] button[type="submit"]');
    await page.waitForLoadState('networkidle');
    const stored = JSON.parse(wp('option', 'get', 'd5svgf_uploads', '--format=json'));
    assert.equal(stored['e2e-evil'].markup, '<filter id="e2e-evil"><feFlood result="f"/><feOffset dx="3" dy="3"/></filter>');

    const content = '<!-- wp:divi/placeholder --><!-- wp:divi/section --><!-- wp:divi/row --><!-- wp:divi/column {"module":{"advanced":{"type":{"desktop":{"value":"4_4"}}}}} -->'
      + '<!-- wp:divi/text {"module":{"decoration":{"filters":{"desktop":{"value":{"svgFilter":"e2e-glow"}}}}},"content":{"innerContent":{"desktop":{"value":"<p>Upload</p>"}}}} /-->'
      + '<!-- /wp:divi/column --><!-- /wp:divi/row --><!-- /wp:divi/section --><!-- /wp:divi/placeholder -->';
    wp('post', 'create', '--post_type=page', '--post_status=publish', '--post_name=e2e-upload', '--post_title=e2e-upload', `--post_content=${content}`);
    let fe = await frontend('e2e-upload');
    assert.equal(await style(fe.page, '.et_pb_text_0'), 'url("#e2e-glow")');
    await fe.page.close();

    await page.goto(`${BASE}/wp-admin/tools.php?page=d5svgf`);
    page.once('dialog', (d) => d.accept());
    await page.locator('tr:has(code:text-is("e2e-glow")) button:has-text("Delete")').click();
    await page.waitForLoadState('networkidle');
    fe = await frontend('e2e-upload');
    assert.equal(await style(fe.page, '.et_pb_text_0'), 'none', 'deleted filter: no SVG filter');
    assert.ok(fe.logs.some((l) => l.includes('e2e-glow')));
    await fe.page.close();
    await page.goto(`${BASE}/wp-admin/tools.php?page=d5svgf`);
    n = await notices();
    assert.ok(n.some((t) => t.includes('Missing SVG filters') && t.includes('e2e-glow')));
  } finally {
    await page.close();
    const id = pageId('e2e-upload');
    if (id) wp('post', 'delete', id, '--force');
    wp('option', 'delete', 'd5svgf_uploads');
    wp('option', 'delete', 'd5svgf_missing');
  }
});

test('no PHP warnings or notices', () => {
  const log = execFileSync('docker', ['compose', 'exec', '-T', 'wp', 'sh', '-c', 'cat wp-content/debug.log 2>/dev/null || true'], { cwd: DIR, env, encoding: 'utf8' });
  assert.equal(log.trim(), '');
});
