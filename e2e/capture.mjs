// README images: one photo unfiltered and under several kit filters, and the SVG filter fields in the Builder
// sidebar. Needs the test site from setup.sh and ffmpeg. Run: cd e2e && node capture.mjs
// Writes docs/screenshots/; everything it creates in the site is deleted again.
// The photo ("Flower meadow in Llano, Texas", public domain CC0, rawpixel.com) ships with WordPress's Twenty
// Twenty-Five theme, so it is in every test site and needs no download.
import { execFileSync } from 'node:child_process';
import { mkdirSync, mkdtempSync, rmSync } from 'node:fs';
import { homedir, tmpdir } from 'node:os';
import { join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { chromium } from 'playwright';

const DIR = fileURLToPath(new URL('.', import.meta.url));
const OUT = fileURLToPath(new URL('../docs/screenshots/', import.meta.url));
const BASE = `http://localhost:${process.env.E2E_PORT || 8086}`;
const env = { ...process.env, DIVI_THEME_DIR: process.env.DIVI_THEME_DIR || `${process.env.XDG_CACHE_HOME || homedir() + '/.cache'}/d5svgf-e2e/Divi` };
const wp = (...args) => execFileSync('docker', ['compose', 'run', '--rm', '-T', 'cli', 'wp', ...args], { cwd: DIR, env, encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'] }).trim();
mkdirSync(OUT, { recursive: true });

// [kit filter, label]; null = the photo without a filter.
// Filters that change a photo visibly at thumbnail size (marble and outline are made for shapes and text).
// Labels are the filters' titles (kit/filters/*.svg), as the Builder lists them.
const FILTERS = [[null, 'Original'], ['fx-duotone', 'Duotone'], ['fx-emboss', 'Emboss'], ['fx-paper', 'Paper relief'], ['fx-posterize', 'Posterize'], ['fx-spotlight', 'Pointer light']];
const COLUMNS = 3;

const cleanup = [];
try {
  const photo = wp('media', 'import', 'wp-content/themes/twentytwentyfive/assets/images/coming-soon-bg-image.webp', '--title=Flower meadow', '--porcelain');
  cleanup.push(() => wp('post', 'delete', photo, '--force'));
  const src = wp('eval', `echo wp_get_attachment_url(${photo}, 'large');`);
  const page = (filters) => {
    const id = wp('post', 'create', '--user=admin', '--post_type=page', '--post_status=publish', '--post_title=SVG Filters', `--post_content=${layout(src, filters)}`, '--meta_input={"_et_pb_use_builder":"on"}', '--porcelain');
    cleanup.push(() => wp('post', 'delete', id, '--force'));
    return id;
  };

  const browser = await chromium.launch(process.env.CHROMIUM_PATH ? { executablePath: process.env.CHROMIUM_PATH } : {});
  try {
    await filterGrid(browser, page(FILTERS.map(([filter]) => filter)));
    await sidebarShot(browser, page(['fx-duotone']));
  } finally {
    await browser.close();
  }
} finally {
  for (const undo of cleanup.reverse()) undo();
}

/** Divi page markup: one Image module showing `src` per entry of `filters` (an SVG filter ID, or null for none). */
function layout(src, filters) {
  const modules = filters.map((filter) => {
    const attrs = { image: { innerContent: { desktop: { value: { src } } } } };
    if (filter) attrs.module = { decoration: { filters: { desktop: { value: { svgFilter: filter } } } } };
    return `<!-- wp:divi/image ${JSON.stringify(attrs)} /-->`;
  }).join('');
  return `<!-- wp:divi/placeholder --><!-- wp:divi/section --><!-- wp:divi/row --><!-- wp:divi/column {"module":{"advanced":{"type":{"desktop":{"value":"4_4"}}}}} -->${modules}<!-- /wp:divi/column --><!-- /wp:divi/row --><!-- /wp:divi/section --><!-- /wp:divi/placeholder -->`;
}

/** Design > Filters with an SVG filter chosen: the plugin's fields. */
async function sidebarShot(browser, id) {
  const context = await browser.newContext({ viewport: { width: 1600, height: 2000 }, deviceScaleFactor: 2 });
  const login = await context.newPage();
  await login.goto(`${BASE}/wp-login.php`);
  await login.fill('#user_login', 'admin');
  await login.fill('#user_pass', 'admin');
  await login.click('#wp-submit');
  await login.waitForURL(/wp-admin/);
  await login.close();

  const page = await context.newPage();
  await page.goto(`${BASE}/?p=${id}&et_fb=1&PageSpeed=off`, { waitUntil: 'domcontentloaded' });
  const app = await (await page.waitForSelector('iframe#et-vb-app-frame', { timeout: 120000 })).contentFrame();
  await app.waitForSelector('.et_pb_image', { timeout: 120000 });
  await page.waitForTimeout(3000); // Deliberate: Divi keeps initialising after the module appears.
  // The settings through Divi's own store (a click on the module can land on the page instead).
  const moduleId = await app.evaluate(() => document.querySelector('.et_pb_image').className.match(/et_pb_image_([0-9a-f-]{36})/)[1]);
  await app.evaluate((owner) => window.divi.data.dispatch('divi/modal-library').open({ owner, name: 'divi/module' }), moduleId);
  await page.getByText('Design', { exact: true }).first().click();
  await page.waitForTimeout(800);
  await page.locator('.et-vb-modal-group-title', { hasText: /^\s*Filters\s*$/ }).first().click();
  await page.waitForTimeout(1200);
  const select = page.locator('#et-vb-module-decoration-filters--svgFilter');
  await select.scrollIntoViewIfNeeded();

  // The plugin's fields at the end of Divi's Filters group: from the "SVG Filter" label to the group's end.
  const sidebar = await page.locator('.et-vb-sidebar--right').boundingBox();
  const group = await page.locator('.et-vb-modal-group-title', { hasText: /^\s*Filters\s*$/ }).first().locator('xpath=..').boundingBox();
  const label = await page.getByText('SVG Filter', { exact: true }).first().boundingBox();
  const top = label.y - 16;
  await page.screenshot({ path: join(OUT, 'builder-svg-filter.png'), clip: { x: sidebar.x, y: top, width: sidebar.width, height: group.y + group.height - top } });
  await context.close();
}

/** Each image (with its filter applied) as a labelled tile, COLUMNS per row, in one PNG. */
async function filterGrid(browser, id) {
  const tab = await browser.newPage({ viewport: { width: 1280, height: 900 } });
  await tab.goto(`${BASE}/?p=${id}`, { waitUntil: 'networkidle' });
  await tab.waitForTimeout(1000); // Deliberate: animated filters settle on a frame; turbulence filters render late.
  const dir = mkdtempSync(join(tmpdir(), 'd5svgf-capture-'));
  const modules = tab.locator('.et_pb_image');
  const files = [];
  for (let i = 0; i < FILTERS.length; i++) {
    files.push(join(dir, `${i}.png`));
    await modules.nth(i).screenshot({ path: files.at(-1) });
  }
  await tab.close();

  const rows = Math.ceil(files.length / COLUMNS);
  const tile = (i) => `[${i}]scale=260:184:force_original_aspect_ratio=increase,crop=260:184,pad=276:224:8:8:white,`
    + `drawtext=font=Sans:text='${FILTERS[i][1]}':fontsize=16:fontcolor=0x333333:x=(w-tw)/2:y=199[v${i}]`;
  // Empty tiles fill the last row, so xstack gets a full grid.
  const blanks = Array.from({ length: rows * COLUMNS - files.length }, (_, k) => `color=white:size=276x224:d=1[v${files.length + k}]`);
  const layoutSpec = Array.from({ length: rows * COLUMNS }, (_, i) => `${(i % COLUMNS) * 276}_${Math.floor(i / COLUMNS) * 224}`).join('|');
  const graph = [...files.map((_, i) => tile(i)), ...blanks, `${Array.from({ length: rows * COLUMNS }, (_, i) => `[v${i}]`).join('')}xstack=inputs=${rows * COLUMNS}:layout=${layoutSpec}`].join(';');
  execFileSync('ffmpeg', ['-y', '-loglevel', 'error', ...files.flatMap((f) => ['-i', f]), '-filter_complex', graph, '-frames:v', '1', join(OUT, 'filters.png')]);
  rmSync(dir, { recursive: true, force: true });
}
