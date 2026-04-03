// @ts-check
const { test, expect } = require('@playwright/test');

/**
 * Scan E2E Tests
 *
 * @author J.J. Johnson <visionquest716@gmail.com>
 */

test.describe('Scans', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto('/login');
        await page.fill('input[name="email"]', 'email4johnson@gmail.com');
        await page.fill('input[name="password"]', '24AdaPlace');
        await page.click('button[type="submit"]');
        await expect(page).toHaveURL('/');
    });

    test('should display new scan page with tabs', async ({ page }) => {
        await page.goto('/scans/new');
        await expect(page.locator('text=Scan URL')).toBeVisible();
        await expect(page.locator('text=Scan Photo')).toBeVisible();
    });

    test('should show URL input on URL tab', async ({ page }) => {
        await page.goto('/scans/new');
        await expect(page.locator('input[name="url"]')).toBeVisible();
        await expect(page.locator('#btn-scrape-url')).toBeVisible();
    });

    test('should switch to photo tab', async ({ page }) => {
        await page.goto('/scans/new');
        await page.click('button', { hasText: 'Scan Photo' });
        await expect(page.locator('#drop-zone')).toBeVisible();
    });

    test('should display scan history page', async ({ page }) => {
        await page.goto('/scans');
        await expect(page.locator('.page-header h1')).toContainText('Scan History');
    });

    test('should have filter controls on scan history', async ({ page }) => {
        await page.goto('/scans');
        await expect(page.locator('input[name="search"]')).toBeVisible();
        await expect(page.locator('select[name="status"]')).toBeVisible();
    });

    test('should view sample scan details', async ({ page }) => {
        await page.goto('/scans/1');
        await expect(page.locator('.page-header h1')).toBeVisible();
        await expect(page.locator('#items-table')).toBeVisible();
    });

    test('should export scan as CSV', async ({ page }) => {
        const downloadPromise = page.waitForEvent('download');
        await page.goto('/scans/1');
        await page.click('a', { hasText: 'Export CSV' });
        const download = await downloadPromise;
        expect(download.suggestedFilename()).toContain('.csv');
    });
});
