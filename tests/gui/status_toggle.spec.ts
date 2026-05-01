import { test, expect } from '@playwright/test';

test.describe('Email Status Toggle and Persistent Settings', () => {
  
  test.beforeEach(async ({ page }) => {
    await page.goto('/login');
    await page.fill('input[name="email"]', 'admin@example.com');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    await page.waitForURL('/');
  });

  test('Toggle ON should show all statuses and be persistent', async ({ page }) => {
    // 1. Initial state
    const checkbox = page.locator('#all-status-toggle');
    
    // Ensure we start with OFF
    if (await checkbox.isChecked()) {
      await checkbox.click({ force: true });
    }
    await expect(checkbox).not.toBeChecked();

    // 2. Turn Toggle ON
    await checkbox.click({ force: true });
    await expect(checkbox).toBeChecked();
    
    // 3. Verify color badges appear
    await page.waitForTimeout(1500); 
    const badgeCount = await page.locator('.thread-list-row .shadow-sm').count();
    expect(badgeCount).toBeGreaterThan(0);
    
    // 4. Reload page and check persistence
    await page.reload();
    await page.waitForLoadState('networkidle');
    await expect(page.locator('#all-status-toggle')).toBeChecked();
  });

  test('Toggle OFF should filter by selected tab', async ({ page }) => {
    const checkbox = page.locator('#all-status-toggle');
    
    // Ensure Toggle is OFF
    if (await checkbox.isChecked()) {
      await checkbox.click({ force: true });
    }
    await expect(checkbox).not.toBeChecked();

    // Click '完了' tab
    await page.click('button:has-text("完了")');
    await page.waitForTimeout(1000);
    
    // In Toggle OFF mode, status badges shouldn't be visible in the list
    const badgeCount = await page.locator('.thread-list-row [x-text="statusLabels[thread.status] || \'受信\'"]').count();
    expect(badgeCount).toBe(0);
  });

});
