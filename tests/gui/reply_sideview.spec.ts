import { test, expect } from '@playwright/test';

test.describe('Rice Mail Reply Sideview Tests', () => {
  
  test.beforeEach(async ({ page }) => {
    // Standard login
    await page.goto('/login');
    await page.fill('input[name="email"]', 'admin@example.com');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    await page.waitForURL('/');
  });

  test('Reply button opens side panel and displays all components', async ({ page }) => {
    // 1. Load threads and select one
    await page.waitForSelector('.email-item');
    const firstThread = page.locator('.email-item').first();
    await firstThread.click();

    // 2. Click "返信" button in the header
    const replyButton = page.locator('button:has-text("返信")').first();
    await expect(replyButton).toBeVisible();
    await replyButton.click();

    // 3. Verify side panel visibility
    const sidePanel = page.locator('aside:has-text("返信ドラフト")');
    await expect(sidePanel).toBeVisible();

    // 4. Verify input fields in side panel using unique data-test-ids
    await expect(page.locator('[data-test-id="reply-to-label"]')).toBeVisible();
    await expect(page.locator('[data-test-id="reply-cc-label"]')).toBeVisible();
    await expect(page.locator('[data-test-id="reply-bcc-label"]')).toBeVisible();
    await expect(page.locator('[data-test-id="reply-subject-label"]')).toBeVisible();
    await expect(page.locator('textarea[placeholder="返信内容を入力してください..."]')).toBeVisible();

    // 5. Verify action buttons in side panel
    const submitButton = page.locator('aside button:has-text("承認を依頼する")');
    await expect(submitButton).toBeVisible();
    
    const aiButton = page.locator('aside button:has-text("AIアシスタント")');
    await expect(aiButton).toBeVisible();

    // 6. Test AI Assistant panel toggle
    await aiButton.click();
    const aiPanel = page.locator('aside[x-show="replyAiPanelOpen"]');
    await expect(aiPanel).toBeVisible();

    // 7. Close side panels
    await page.locator('aside:has-text("返信ドラフト") button:has(.fa-times)').click();
    await expect(sidePanel).toBeHidden();
  });
});
