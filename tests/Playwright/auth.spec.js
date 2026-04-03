// @ts-check
const { test, expect } = require('@playwright/test');

/**
 * Authentication E2E Tests
 *
 * @author J.J. Johnson <visionquest716@gmail.com>
 */

test.describe('Authentication', () => {
    test('should display login page', async ({ page }) => {
        await page.goto('/login');
        await expect(page.locator('h2')).toContainText('Claude Scraper');
        await expect(page.locator('input[name="email"]')).toBeVisible();
        await expect(page.locator('input[name="password"]')).toBeVisible();
    });

    test('should redirect to login when not authenticated', async ({ page }) => {
        await page.goto('/');
        await expect(page).toHaveURL(/\/login/);
    });

    test('should show error for invalid credentials', async ({ page }) => {
        await page.goto('/login');
        await page.fill('input[name="email"]', 'wrong@email.com');
        await page.fill('input[name="password"]', 'wrongpassword');
        await page.click('button[type="submit"]');
        await expect(page.locator('.alert-danger')).toBeVisible();
    });

    test('should login with valid credentials', async ({ page }) => {
        await page.goto('/login');
        await page.fill('input[name="email"]', 'email4johnson@gmail.com');
        await page.fill('input[name="password"]', '24AdaPlace');
        await page.click('button[type="submit"]');
        await expect(page).toHaveURL('/');
        await expect(page.locator('.sidebar')).toBeVisible();
    });

    test('should logout successfully', async ({ page }) => {
        // Login first
        await page.goto('/login');
        await page.fill('input[name="email"]', 'email4johnson@gmail.com');
        await page.fill('input[name="password"]', '24AdaPlace');
        await page.click('button[type="submit"]');
        await expect(page).toHaveURL('/');

        // Logout
        await page.click('a[href="/logout"]');
        await expect(page).toHaveURL(/\/login/);
    });
});
