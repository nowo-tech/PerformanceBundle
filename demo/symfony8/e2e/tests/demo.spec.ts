import { test, expect, type Page } from '@playwright/test';

/**
 * REQ-DEMO-013 — happy-path e2e against the PerformanceBundle demo (UiKit page contract, REQ-UI-003).
 * `make test-e2e` seeds a week of demo metrics first (app:demo:seed-metrics).
 */
async function expectNoInlineScripts(page: Page): Promise<void> {
  const html = await page.content();
  expect(html).not.toMatch(/\son(click|submit|change)=/i);
  expect(html).not.toContain('confirm(');
  expect(html).not.toMatch(/cdn\.jsdelivr\.net\/npm\/(chart\.js|tailwindcss)/);
}

test.describe('demo happy path', () => {
  test('home responds with the demo masthead', async ({ page }) => {
    const response = await page.goto('/');
    expect(response?.ok()).toBeTruthy();
    await expect(page.getByTestId('demo-masthead')).toBeVisible();
  });

  test('routes page: scaffold, KPI tiles, area tabs, routes table', async ({ page }) => {
    const response = await page.goto('/demo/performance/');
    expect(response?.ok()).toBeTruthy();
    await expect(page.getByTestId('performance-routes')).toBeVisible();
    await expect(page.getByTestId('performance-kpis')).toBeVisible();
    await expect(page.getByTestId('performance-nav-index')).toHaveAttribute('aria-current', /page|true/);
    await expect(page.locator('[data-nowo-ui-region="table"] tbody tr').first()).toBeVisible();
    await expect(page.getByTestId('performance-trend')).toBeVisible();
    await expectNoInlineScripts(page);
  });

  test('filters narrow the routes list', async ({ page }) => {
    await page.goto('/demo/performance/?route=demo_report');
    const rows = page.locator('[data-nowo-ui-region="table"] tbody tr');
    await expect(rows).toHaveCount(1);
    await expect(rows.first()).toContainText('demo_report_export');
  });

  test('clear all asks for confirmation in a UiKit dialog', async ({ page }) => {
    await page.goto('/demo/performance/');
    await page.getByTestId('performance-clear-all').click();
    const dialog = page.locator('dialog[open]');
    await expect(dialog).toBeVisible();
    await dialog.getByRole('button', { name: /cancel/i }).click();
    await expect(page.locator('dialog[open]')).toHaveCount(0);
  });

  test('review opens a dialog with the review form and saves it', async ({ page }) => {
    await page.goto('/demo/performance/?route=demo_home');
    const review = page.locator('[data-testid^="performance-route-review-"]').first();
    await review.click();
    const dialog = page.locator('dialog[open]');
    await expect(dialog).toBeVisible();
    await dialog.locator('select').first().selectOption({ index: 1 });
    await dialog.getByRole('button', { name: /review/i }).click();
    const row = page.locator('[data-nowo-ui-region="table"] tbody tr').first();
    await expect(row.getByText('Reviewed', { exact: true })).toBeVisible();
    await expect(page.locator('[data-testid^="performance-route-review-"]').first()).toHaveAttribute('aria-label', /edit review/i);
  });

  test('statistics, access statistics, access records and diagnose render', async ({ page }) => {
    for (const [path, testId] of [
      ['/demo/performance/statistics', 'performance-statistics'],
      ['/demo/performance/access-statistics', 'performance-access-statistics'],
      ['/demo/performance/access-records', 'performance-access-records'],
      ['/demo/performance/diagnose', 'performance-diagnose'],
    ]) {
      const response = await page.goto(path);
      expect(response?.ok(), path).toBeTruthy();
      await expect(page.getByTestId(testId)).toBeVisible();
      await expect(page.locator('h1').first()).toBeVisible();
      await expectNoInlineScripts(page);
    }
    await page.goto('/demo/performance/access-statistics');
    await expect(page.getByTestId('performance-heatmap').locator('svg')).toBeVisible();
  });

  test('access records sort by response time through the column header', async ({ page }) => {
    await page.goto('/demo/performance/access-records');
    await page.locator('[data-nowo-ui-region="table"] thead').getByRole('link', { name: /response time/i }).click();
    await expect(page).toHaveURL(/sort=responseTime/);
    await expect(page.locator('[data-nowo-ui-region="table"] thead th[aria-sort]')).toContainText(/response time/i);
  });

  test('purge opens its dialog', async ({ page }) => {
    await page.goto('/demo/performance/access-records');
    await page.getByTestId('performance-purge-open').click();
    await expect(page.getByTestId('performance-purge-form')).toBeVisible();
  });
});
