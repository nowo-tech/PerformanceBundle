import { test, expect, type Page } from '@playwright/test';
import { mkdirSync } from 'node:fs';
import { resolve } from 'node:path';

/**
 * REQ-DEMO-013 — dashboard screenshots used by README.md (docs/images/demo/*.webp).
 * Captures the dashboard content panel (not the browser chrome / profiler toolbar).
 */
const outDir = process.env.SCREENSHOT_DIR
  ? resolve(process.env.SCREENSHOT_DIR)
  : resolve(__dirname, '../../../../docs/images/demo');

/**
 * Generate tracked traffic (several routes, repeated visits) so the dashboard tables, charts and
 * access statistics have data to show. Metrics are recorded in the default FrankenPHP worker mode.
 */
async function warmUp(page: Page): Promise<void> {
  await page.goto('/product/');
  const productLinks = await page
    .locator('a[href^="/product/"]')
    .evaluateAll((links) => links.map((a) => (a as HTMLAnchorElement).getAttribute('href') ?? ''));
  const productPages = [...new Set(productLinks.filter((href) => /^\/product\/\d+(\/edit)?$/.test(href)))].slice(0, 6);
  const paths = ['/', '/product/', '/product/new', '/performance', ...productPages];
  for (let round = 0; round < 6; round++) {
    for (const path of paths.slice(0, 3 + ((round * 2) % paths.length))) {
      await page.goto(path);
    }
  }
}

async function capture(page: Page, path: string, file: string, maxHeight?: number): Promise<void> {
  await page.goto(path);
  await page.waitForLoadState('networkidle');
  // Let Chart.js animations finish.
  await page.waitForTimeout(1500);
  await page.addStyleTag({ content: '.sf-toolbar, .sf-minitoolbar { display: none !important; }' });
  // The "Optional Dependencies Missing" hint is dismissible and not part of the feature.
  const depsAlertClose = page.locator('.alert-warning .btn-close').first();
  if (await depsAlertClose.isVisible()) {
    await depsAlertClose.click();
    await page.waitForTimeout(400);
  }
  const panel = page.locator('main, .container-fluid, .container').first();
  await expect(panel).toBeVisible();
  const box = await panel.boundingBox();
  if (maxHeight && box && box.height > maxHeight) {
    // Long report pages: keep the top sections (summary panels and first statistics block).
    await page.screenshot({
      path: resolve(outDir, file),
      fullPage: true,
      clip: { x: box.x, y: box.y, width: box.width, height: maxHeight },
    });
    return;
  }
  await panel.screenshot({ path: resolve(outDir, file) });
}

test.beforeAll(() => {
  mkdirSync(outDir, { recursive: true });
});

test.describe('feature screenshots', () => {
  test.use({ viewport: { width: 1440, height: 900 } });

  test('dashboard, advanced statistics and access statistics', async ({ page }) => {
    test.setTimeout(120_000);
    await warmUp(page);
    await capture(page, '/demo/performance/', 'dashboard-main.png');
    await capture(page, '/demo/performance/statistics', 'dashboard-advanced-statistics.png', 2600);
    await capture(page, '/demo/performance/access-statistics', 'dashboard-access-statistics.png', 2000);
  });
});
