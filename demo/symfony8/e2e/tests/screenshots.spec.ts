import { test, expect, type Page } from '@playwright/test';
import { mkdirSync } from 'node:fs';
import { resolve } from 'node:path';

/**
 * REQ-DEMO-013 — README screenshots (docs/images/demo/*.webp): demo masthead + the dashboard page in a useful state
 * (a week of seeded metrics, `make demo-screenshots` runs app:demo:seed-metrics) + the Symfony WebProfiler toolbar.
 */
const outDir = process.env.SCREENSHOT_DIR
  ? resolve(process.env.SCREENSHOT_DIR)
  : resolve(__dirname, '../../../../docs/images/demo');

async function capture(page: Page, path: string, file: string, height: number): Promise<void> {
  await page.setViewportSize({ width: 1440, height });
  await page.goto(path);
  await page.waitForLoadState('networkidle');
  await expect(page.getByTestId('demo-masthead')).toBeVisible();
  await expect(page.locator('.sf-toolbar')).toBeVisible();
  await page.screenshot({ path: resolve(outDir, file) });
}

test.beforeAll(() => {
  mkdirSync(outDir, { recursive: true });
});

test.describe('feature screenshots', () => {
  test('routes, advanced statistics and access statistics', async ({ page }) => {
    test.setTimeout(120_000);
    await capture(page, '/demo/performance/', 'dashboard-main.png', 1500);
    await capture(page, '/demo/performance/statistics', 'dashboard-advanced-statistics.png', 1500);
    await capture(page, '/demo/performance/access-statistics', 'dashboard-access-statistics.png', 1400);
  });
});
