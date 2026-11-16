import { mkdir, writeFile } from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { chromium } from 'playwright';
import {
  parseBase,
  expectsNativeBooking,
  bookInView,
  checkSlug,
  renderReport,
  exitCode,
} from './post-deploy-smoke-lib.mjs';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const reportPath = path.join(root, 'tmp', 'deploy-smoke-last.md');
const shotDir = path.join(root, 'tmp', 'deploy-smoke');
const checkTimeout = 20000;

function viewportLabel(viewport) {
  return `${viewport.width}x${viewport.height}`;
}

async function waitInView(page, selector) {
  const started = Date.now();
  let top = null;
  const height = page.viewportSize().height;
  while (Date.now() - started < 3000) {
    const box = await page.locator(selector).boundingBox();
    top = box ? box.y : null;
    if (top != null && bookInView(top, height)) return;
    await page.waitForTimeout(100);
  }
  throw new Error(`${selector} not in view (top=${top}, viewport=${height})`);
}

async function assertBookingSurface(page, base) {
  if (expectsNativeBooking(base)) {
    const wrap = page.locator('#cc-native-wrap');
    if (await wrap.isHidden()) throw new Error('native booking wrap is hidden');
    if ((await page.locator('#cc-booking-form').count()) !== 1) {
      throw new Error('native booking form is missing');
    }
    return;
  }
  if (!(await page.locator('#JotFormIFrame-252817790661061').isVisible())) {
    throw new Error('Jotform iframe is not visible');
  }
  if (await page.locator('#cc-jotform-wrap').getAttribute('hidden') !== null) {
    throw new Error('Jotform wrap is hidden');
  }
}

async function openPage(page, url) {
  const response = await page.goto(url, { waitUntil: 'domcontentloaded', timeout: checkTimeout });
  if (!response) throw new Error(`no response from ${url}`);
  if (response.status() >= 400) throw new Error(`${url} returned ${response.status()}`);
  await page.waitForTimeout(800);
}

async function runCookieClass(page, base, viewport) {
  await page.setViewportSize(viewport);
  await openPage(page, `${base}/cookie-class/`);
  const link = page.getByRole('link', { name: 'Book your seat' });
  await link.scrollIntoViewIfNeeded();
  await link.click();
  await waitInView(page, '#book');
  await assertBookingSurface(page, base);
}

async function runClassCard(page, base) {
  await page.setViewportSize({ width: 1280, height: 800 });
  await openPage(page, `${base}/cookie-class/`);
  const link = page.locator('a[href*="?class="][href*="#book"]').first();
  if ((await link.count()) !== 1) throw new Error('no class card link with ?class= and #book');
  await link.scrollIntoViewIfNeeded();
  await link.click();
  await page.waitForURL(/#book/, { timeout: checkTimeout });
  if (!page.url().includes('#book')) throw new Error('hash is not #book');
  await waitInView(page, '#book');
  await assertBookingSurface(page, base);
}

async function runOrderProducts(page, base) {
  await page.setViewportSize({ width: 1280, height: 800 });
  await openPage(page, `${base}/order/`);
  const tab = page.getByRole('tab', { name: /Tres Leches & Cinnamon Rolls/ });
  if ((await tab.getAttribute('aria-selected')) !== 'true') {
    throw new Error('tres leches and cinnamon roll tab is not selected');
  }
  const panel = page.locator('#tab-order');
  if (!(await panel.evaluate((el) => el.classList.contains('active')))) {
    throw new Error('#tab-order is not the active panel');
  }
  const text = await panel.innerText();
  if (!/Tres Leches/i.test(text)) throw new Error('Tres Leches text is missing');
  if (!/Cinnamon Roll/i.test(text)) throw new Error('Cinnamon Roll text is missing');
}

async function runPresale(page) {
  const closed = page.locator('#presale-closed');
  if (await closed.isVisible()) return;
  const jump = page.getByRole('link', { name: 'Click Here for Order Form' });
  await jump.scrollIntoViewIfNeeded();
  await jump.click();
  await waitInView(page, '#presale-form');
  if ((await page.locator('#presale_first').count()) !== 1) throw new Error('#presale_first is missing');
  if ((await page.locator('#presale_email').count()) !== 1) throw new Error('#presale_email is missing');
  if (await page.locator('#presale-jotform-wrap').isVisible()) {
    throw new Error('Jotform wrap is visible before the contact form is submitted');
  }
}

async function runSugarCookies(page) {
  const tab = page.getByRole('tab', { name: /Decorated sugar cookie sets/ });
  await tab.click();
  const panel = page.locator('#tab-cookies');
  if (!(await panel.evaluate((el) => el.classList.contains('active')))) {
    throw new Error('#tab-cookies is not the active panel');
  }
  if (!(await page.locator('#cookie-order-form').isVisible())) {
    throw new Error('sugar cookie form is not visible');
  }
}

const checks = [
  { name: 'Book your seat', viewport: { width: 1280, height: 800 }, run: runCookieClass },
  { name: 'Book your seat', viewport: { width: 390, height: 844 }, run: runCookieClass },
  { name: 'Class card', viewport: { width: 1280, height: 800 }, run: async (page, base) => runClassCard(page, base) },
  { name: 'Tres leches and cinnamon rolls', viewport: { width: 1280, height: 800 }, run: runOrderProducts },
  { name: 'Presale form', viewport: { width: 1280, height: 800 }, run: async (page) => runPresale(page) },
  { name: 'Sugar cookies', viewport: { width: 1280, height: 800 }, run: async (page) => runSugarCookies(page) },
];

async function main() {
  const started = new Date().toISOString();
  let base = '';
  let runnerError = '';
  let browser;
  const results = [];
  try {
    base = parseBase(process.argv.slice(2));
  } catch (err) {
    runnerError = err.message;
    process.exit(await finish({ base, started, runnerError, results }));
  }
  try {
    browser = await chromium.launch({ headless: true });
  } catch (err) {
    runnerError = `Chromium failed to launch: ${err.message}`;
    process.exit(await finish({ base, started, runnerError, results }));
  }
  const page = await browser.newPage();
  page.setDefaultTimeout(checkTimeout);
  try {
    await openPage(page, `${base}/`);
  } catch (err) {
    runnerError = err.message;
    const code = await finish({ base, started, runnerError, results });
    await browser.close();
    process.exit(code);
  }
  let orderReady = false;
  for (const check of checks) {
    const label = viewportLabel(check.viewport);
    try {
      if (check.name === 'Presale form' || check.name === 'Sugar cookies') {
        if (!orderReady) throw new Error('order page was not opened');
        await page.setViewportSize(check.viewport);
        await check.run(page, base);
      } else {
        await check.run(page, base, check.viewport);
        if (check.name === 'Tres leches and cinnamon rolls') orderReady = true;
      }
      results.push({ name: check.name, viewport: label, pass: true, reason: '' });
    } catch (err) {
      results.push({ name: check.name, viewport: label, pass: false, reason: err.message });
      const slug = checkSlug(`${check.name} ${label}`);
      await mkdir(shotDir, { recursive: true });
      await page.screenshot({ path: path.join(shotDir, `${slug}.png`) });
    }
  }
  const code = await finish({ base, started, runnerError, results });
  await browser.close();
  process.exit(code);
}

async function finish({ base, started, runnerError, results }) {
  const code = exitCode({ runnerError, checks: results });
  const report = renderReport({
    base,
    time: started,
    checks: results,
    runnerError,
  });
  process.stdout.write(report);
  if (code !== 0) {
    await mkdir(path.dirname(reportPath), { recursive: true });
    await writeFile(reportPath, report, 'utf8');
  }
  return code;
}

main().catch(async (err) => {
  const report = renderReport({
    base: '',
    time: new Date().toISOString(),
    checks: [],
    runnerError: err.message,
  });
  process.stdout.write(report);
  await mkdir(path.dirname(reportPath), { recursive: true });
  await writeFile(reportPath, report, 'utf8');
  process.exit(2);
});
