import { test, expect } from '@playwright/test';

test('UI Visibility and Language Check', async ({ page }) => {
  await page.goto('/login');
  await page.fill('input[name="email"]', 'admin@example.com');
  await page.fill('input[name="password"]', 'password');
  await page.click('button[type="submit"]');
  await page.waitForURL('/');

  // 1. 各カラムが表示されているか
  const sidebar = page.locator('div[x-data="emailApp()"] > div.bg-gray-900').first(); // サイドバー
  await expect(sidebar).toBeVisible();
  
  const threadList = page.locator('#email-list-container'); // スレッド一覧
  await expect(threadList).toBeVisible();

  // 2. 日本語が正しく表示されているか
  await expect(page.locator('text=全表示')).toBeVisible();
  await expect(page.locator('text=新規作成')).toBeVisible();
  
  // 3. ローディング後に要素が消えていないか (x-cloakのチェック)
  const appContainer = page.locator('[x-data="emailApp()"]');
  await expect(appContainer).not.toHaveAttribute('x-cloak', '');

  // スクリーンショット保存
  await page.screenshot({ path: 'visibility-check.png', fullPage: true });
  console.log('Visibility check passed and screenshot saved.');
});
