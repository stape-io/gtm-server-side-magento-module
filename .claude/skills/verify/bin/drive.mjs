#!/usr/bin/env node
// Drives the verification store (verify-env up) in Chromium and writes what a
// shopper or merchant sees, plus every dataLayer push, to an evidence directory.
//
//   node .claude/skills/verify/bin/drive.mjs storefront <path> --out <dir> [--settle-ms 2000]
//   node .claude/skills/verify/bin/drive.mjs admin <path> --out <dir>
//
// e.g. storefront /gear/bags.html   or   admin admin/system_config/edit/section/stape_gtm/
//
// For a journey with several steps, import it from a scratch script under the
// evidence directory instead:
//   import { open, adminLogin, addToCart, capture, settle } from '<repo>/.claude/skills/verify/bin/drive.mjs';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { chromium } from 'playwright';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../../../..');

/** What `verify-env up` recorded: project, ports, storefront/admin/sink URLs, admin login. */
export function environment() {
    const file = path.join(ROOT, '.verify/env.json');
    if (!fs.existsSync(file)) {
        throw new Error(`${file} is missing: start the store with .claude/skills/verify/bin/verify-env up`);
    }

    return JSON.parse(fs.readFileSync(file, 'utf8'));
}

// Runs before any page script: forwards every push, including pushes into an
// array assigned to window.dataLayer later.
function installRecorder() {
    const record = (entry) => {
        try {
            window.__verifyPush(JSON.parse(JSON.stringify(entry ?? null)));
        } catch {
            // Not serialisable: not a push the module makes.
        }
    };
    const wrap = (value) => {
        if (!Array.isArray(value) || value.__verifyRecorded) {
            return value;
        }
        Object.defineProperty(value, '__verifyRecorded', { value: true });
        value.forEach(record);
        let push = Array.prototype.push;
        Object.defineProperty(value, 'push', {
            configurable: true,
            get: () => function (...entries) {
                entries.forEach(record);
                return push.apply(this, entries);
            },
            set: (fn) => {
                push = fn;
            },
        });
        return value;
    };
    let current = wrap(window.dataLayer);
    Object.defineProperty(window, 'dataLayer', { configurable: true, get: () => current, set: (v) => { current = wrap(v); } });
}

/**
 * A fresh browser on the store. `pushes` collects every dataLayer push (with
 * the page URL), `scripts` every script request. gtm.js from
 * googletagmanager.com is answered with an empty script: no real container runs.
 */
export async function open({ headed = false } = {}) {
    const env = environment();
    const browser = await chromium.launch({ headless: !headed });
    const context = await browser.newContext({ baseURL: env.storefront, locale: 'en-US', viewport: { width: 1280, height: 900 } });
    const pushes = [];
    const scripts = [];
    await context.exposeBinding('__verifyPush', ({ frame }, entry) => pushes.push({ seq: pushes.length, url: frame.url(), entry }));
    await context.addInitScript(installRecorder);
    await context.route('https://www.googletagmanager.com/**', (route) => route.fulfill({ status: 200, contentType: 'text/javascript', body: '' }));
    context.on('request', (request) => {
        if (request.resourceType() === 'script') {
            scripts.push(request.url());
        }
    });
    const page = await context.newPage();
    page.setDefaultTimeout(60_000);

    return { env, browser, context, page, pushes, scripts };
}

/** Waits until RequireJS has run and the network is idle (Luma is slow under emulation). */
export async function settle(page, ms = 2000) {
    await page.waitForLoadState('networkidle', { timeout: 120_000 }).catch(() => {});
    await page.waitForTimeout(ms);
}

/** Logs in to the Magento admin with the user `verify-env up` created. */
export async function adminLogin(page) {
    const env = environment();
    await page.goto(env.admin, { timeout: 180_000 });
    await page.getByLabel('Username').fill(env.adminUser);
    await page.getByLabel('Password').fill(env.adminPassword);
    await page.getByRole('button', { name: 'Sign in' }).click();
    await page.locator('.admin__menu').waitFor({ timeout: 180_000 });
}

/** On a product page: set the quantity and press Add to Cart, then wait for the mini cart to refresh. */
export async function addToCart(page, { qty = 1 } = {}) {
    await page.getByRole('spinbutton', { name: 'Qty' }).fill(String(qty));
    const cartLoaded = page.waitForResponse((r) => r.url().includes('customer/section/load') && r.url().includes('cart'), { timeout: 120_000 });
    await page.getByRole('button', { name: 'Add to Cart' }).click();
    await page.locator('.message-success').waitFor({ timeout: 120_000 });
    await cartLoaded;
    await page.waitForTimeout(1000);
}

/** Screenshot, ARIA snapshot and the pushes so far, named `<name>.*` in `dir`. */
export async function capture(page, dir, name, { pushes = [], scripts = [] } = {}) {
    fs.mkdirSync(dir, { recursive: true });
    await page.screenshot({ path: path.join(dir, `${name}.png`), fullPage: true });
    fs.writeFileSync(path.join(dir, `${name}.aria.yml`), await page.locator('body').ariaSnapshot());
    fs.writeFileSync(path.join(dir, `${name}.datalayer.json`), JSON.stringify(pushes, null, 2));
    fs.writeFileSync(path.join(dir, `${name}.scripts.json`), JSON.stringify(scripts, null, 2));
    const cookies = (await page.context().cookies()).map(({ name: n, value, expires, path: p }) => ({ name: n, value, expires, path: p }));
    fs.writeFileSync(path.join(dir, `${name}.cookies.json`), JSON.stringify(cookies, null, 2));
    console.log(`captured ${page.url()} -> ${path.join(dir, name)}.{png,aria.yml,datalayer.json,scripts.json,cookies.json}`);
}

function parseArgs(argv) {
    const [mode, target, ...rest] = argv;
    const options = { settleMs: 2000 };
    for (let i = 0; i < rest.length; i += 2) {
        const key = rest[i].replace(/^--/, '').replace(/-(\w)/g, (_, c) => c.toUpperCase());
        options[key] = rest[i + 1];
    }
    if (!['storefront', 'admin'].includes(mode) || !target || !options.out) {
        console.error(fs.readFileSync(fileURLToPath(import.meta.url), 'utf8').split('\n').slice(4, 8).join('\n'));
        process.exit(2);
    }

    return { mode, target, ...options, settleMs: Number(options.settleMs) };
}

async function main() {
    const args = parseArgs(process.argv.slice(2));
    const { page, pushes, scripts, browser } = await open();
    try {
        if (args.mode === 'admin') {
            await adminLogin(page);
        }
        await page.goto(args.target.replace(/^\//, './'), { timeout: 180_000 });
        await settle(page, args.settleMs);
        await capture(page, args.out, args.mode, { pushes, scripts });
        console.log('events:', pushes.map((p) => p.entry?.event).filter(Boolean).join(', ') || '(none)');
    } finally {
        await browser.close();
    }
}

if (process.argv[1] === fileURLToPath(import.meta.url)) {
    await main();
}
