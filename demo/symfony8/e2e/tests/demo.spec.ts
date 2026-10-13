import { test, expect } from '@playwright/test';

/**
 * REQ-DEMO-013 — happy-path e2e against the PerformanceBundle demo.
 */
test.describe('demo happy path', () => {
  test('home responds', async ({ page }) => {
    const response = await page.goto('/');
    expect(response?.ok()).toBeTruthy();
    await expect(page.locator('body')).toBeVisible();
  });

  test('dashboard lists tracked routes', async ({ page }) => {
    await page.goto('/product/');
    const response = await page.goto('/demo/performance/');
    expect(response?.ok()).toBeTruthy();
    await expect(page.locator('h1').first()).toBeVisible();
    await expect(page.locator('table').first()).toBeVisible();
  });

  test('advanced filters toggle shows only the label for the current state', async ({ page }) => {
    await page.goto('/demo/performance/');
    const toggle = page.locator('button[data-bs-target="#advancedFilters"]');
    await expect(toggle.locator('.when-collapsed')).toBeVisible();
    await expect(toggle.locator('.when-expanded')).toBeHidden();

    await toggle.click();
    await expect(toggle.locator('.when-expanded')).toBeVisible();
    await expect(toggle.locator('.when-collapsed')).toBeHidden();

    await page.goto('/demo/performance/?min_request_time=0.001');
    await expect(toggle.locator('.when-expanded')).toBeVisible();
    await expect(toggle.locator('.when-collapsed')).toBeHidden();
    await expect(page.locator('#advancedFilters')).toBeVisible();
  });

  for (const path of ['/demo/performance/statistics', '/demo/performance/access-statistics', '/demo/performance/diagnose']) {
    test(`${path} responds`, async ({ page }) => {
      const response = await page.goto(path);
      expect(response?.ok()).toBeTruthy();
      await expect(page.locator('h1').first()).toBeVisible();
    });
  }
});
