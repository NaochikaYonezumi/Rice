import { test, expect } from '@playwright/test';

test.describe('Email Sync Functionality', () => {
  
  test.beforeEach(async ({ page }) => {
    page.on('console', msg => console.log('BROWSER LOG:', msg.text()));
    await page.goto('/login');
    await page.fill('input[name="email"]', 'admin@example.com');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    await page.waitForURL('/');
  });

  test('Sync button shows loading state', async ({ page }) => {
    // Increase timeout for real server fetch if needed, 
    // but here we just want to see the spin.
    const syncButton = page.locator('button[title="一覧を更新"] i.fa-sync-alt');
    
    // Trigger sync and don't wait for completion yet
    await page.click('button[title="一覧を更新"]');
    
    // Check for spinning animation immediately
    await expect(syncButton).toHaveClass(/animate-spin/);
    
    // Wait for it to finish
    await expect(syncButton).not.toHaveClass(/animate-spin/, { timeout: 15000 });
  });

  test('Shows error modal on sync failure', async ({ page }) => {
    // Intercept API call. Note: use full path or glob
    await page.route('**/emails/fetch', async route => {
      await new Promise(resolve => setTimeout(resolve, 500)); // Delay to ensure we see the state
      route.fulfill({
        status: 500,
        contentType: 'application/json',
        body: JSON.stringify({ 
          error: 'Mail server connection timeout',
          stack: 'Error: Timeout\n  at EmailFetcher.php:123'
        }),
      });
    });

    // Click sync
    await page.click('button[title="一覧を更新"]');

    // Modal should be visible
    const errorModal = page.locator('text=メールサーバーとの同期に失敗しました');
    await expect(errorModal).toBeVisible({ timeout: 10000 });
    
    // Check for the error message in the detail paragraph specifically
    await expect(page.locator('p[x-text="syncError.detail"]')).toHaveText('Mail server connection timeout');

    // Test detail expansion
    await page.click('text=スタックトレースを表示');
    await expect(page.locator('pre')).toBeVisible();

    // Test retry (un-intercept or intercept as success)
    await page.unroute('/emails/fetch');
    await page.click('button:has-text("リトライ")');

    // Modal should disappear after successful sync
    await expect(errorModal).not.toBeVisible();
  });

});
