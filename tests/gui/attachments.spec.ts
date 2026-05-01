import { test, expect } from '@playwright/test';
import * as fs from 'fs';
import * as path from 'path';

test.describe('Email Attachments and Size Limits', () => {
  
  test.beforeEach(async ({ page }) => {
    await page.goto('/login');
    await page.fill('input[name="email"]', 'admin@example.com');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    await page.waitForURL('/');
  });

  test('Shows error when attaching file larger than 20MB', async ({ page }) => {
    // 1. Open compose
    await page.click('button:has-text("新規作成")');
    await expect(page.locator('[data-test-id="compose-to-label"]')).toBeVisible();

    // 2. Prepare a 21MB dummy file
    const filePath = path.join(__dirname, 'large_dummy.txt');
    const size = 21 * 1024 * 1024;
    const buffer = Buffer.alloc(size, 'a');
    fs.writeFileSync(filePath, buffer);

    // 3. Attach file
    const fileChooserPromise = page.waitForEvent('filechooser');
    await page.click('label[for="compose-file-input"]');
    const fileChooser = await fileChooserPromise;
    await fileChooser.setFiles(filePath);

    // 4. Modal should be visible
    await expect(page.locator('text=添付ファイルエラー')).toBeVisible();
    await expect(page.locator('text=large_dummy.txt が上限(20MB)を超えています。')).toBeVisible();

    // Cleanup
    fs.unlinkSync(filePath);
  });

});
