// @ts-check
const { test, expect } = require('@playwright/test');

/**
 * Dashboard E2E Tests
 *
 * @author J.J. Johnson <visionquest716@gmail.com>
 */

test.describe('Dashboard', () => {
    test.beforeEach(async ({ page }) => {
        // Login
        await page.goto('/login');
        await page.fill('input[name="email"]', 'email4johnson@gmail.com');
        await page.fill('input[name="password"]', '24AdaPlace');
        await page.click('button[type="submit"]');
        await expect(page).toHaveURL('/');
    });

    test('should display dashboard with stat cards', async ({ page }) => {
        await expect(page.locator('.stat-card')).toHaveCount(3);
        await expect(page.locator('.page-header h1')).toContainText('Dashboard');
    });

    test('should display sidebar navigation', async ({ page }) => {
        await expect(page.locator('.sidebar-link', { hasText: 'Dashboard' })).toBeVisible();
        await expect(page.locator('.sidebar-link', { hasText: 'New Scan' })).toBeVisible();
        await expect(page.locator('.sidebar-link', { hasText: 'Scan History' })).toBeVisible();
        await expect(page.locator('.sidebar-link', { hasText: 'Imports' })).toBeVisible();
    });

    test('should show quick action cards', async ({ page }) => {
        await expect(page.locator('text=Scan a URL')).toBeVisible();
        await expect(page.locator('text=Scan a Photo')).toBeVisible();
    });

    test('should navigate to new scan page', async ({ page }) => {
        await page.click('.sidebar-link', { hasText: 'New Scan' });
        await expect(page).toHaveURL('/scans/new');
    });

    test('should navigate to scan history', async ({ page }) => {
        await page.click('.sidebar-link', { hasText: 'Scan History' });
        await expect(page).toHaveURL('/scans');
    });
});
