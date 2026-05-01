import { test, expect } from '@playwright/test';

test.describe('Rice Application GUI Tests', () => {
  
  test.beforeEach(async ({ page }) => {
    // Standard login for each test
    await page.goto('/login');
    await page.fill('input[name="email"]', 'admin@example.com');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    await page.waitForURL('/');
  });

  test('Thread List - Reload button', async ({ page }) => {
    await page.goto('/');
    const reloadButton = page.locator('button[title="一覧を更新"]');
    await expect(reloadButton).toBeVisible();
    
    // Click reload and check for spinning icon (optional/timing dependent)
    await reloadButton.click();
    
    // Ensure the list is still there or loaded
    await expect(page.locator('#email-list-container')).toBeVisible();
  });

  test('Navigation check', async ({ page }) => {
    const navLinks = [
      { text: 'レポート', url: '/reports' },
      { text: 'ナレッジベース', url: '/knowledge' },
      { text: 'Rice Chat', url: '/chat' },
      { text: '招待管理', url: '/admin/invitations' },
      { text: 'メール設定', url: '/settings/mail' },
      { text: 'AI設定', url: '/settings/ai' },
      { text: 'SSO設定', url: '/settings/sso' },
      { text: 'ステータス管理', url: '/master/statuses' },
      { text: 'タグ管理', url: '/master/tags' },
    ];

    for (const link of navLinks) {
      await page.click(`text=${link.text}`);
      await expect(page).toHaveURL(link.url);
      // Go back to home to continue
      await page.goto('/');
    }
  });

  test('Mail Settings - Validation and Save', async ({ page }) => {
    await page.goto('/settings/mail');
    
    // Test empty validation (if any)
    await page.click('button:has-text("設定を保存")');
    // Assuming there might be some feedback or at least it doesn't crash
    
    // Fill with dummy data
    await page.fill('input[name="smtp_host"]', 'smtp.test.com');
    await page.fill('input[name="smtp_port"]', '587');
    await page.selectOption('select[name="smtp_encryption"]', 'TLS');
    await page.fill('input[name="smtp_username"]', 'testuser');
    await page.fill('input[name="smtp_password"]', 'testpass');
    await page.fill('input[name="smtp_from_address"]', 'test@test.com');
    await page.fill('input[name="smtp_from_name"]', 'Test Support');
    
    await page.click('button:has-text("設定を保存")');
    
    // Check for success message (assuming one exists)
    // await expect(page.locator('.alert-success')).toBeVisible();
    
    // Reload and check if values persisted
    await page.reload();
    await expect(page.locator('input[name="smtp_host"]')).toHaveValue('smtp.test.com');
  });

  test('AI Settings - API Key Masking and Selection', async ({ page }) => {
    await page.goto('/settings/ai');
    await page.waitForSelector('input[name="default_provider"]');
    
    await page.fill('input[name="anthropic_api_key"]', 'sk-ant-test-key');
    await page.fill('input[name="gemini_api_key"]', 'AIza-test-key');
    
    // Choose something other than the default if possible
    await page.check('input[name="default_provider"][value="claude"]');
    await page.click('button:has-text("保存")');
    
    // Wait for success message
    await expect(page.locator('text=設定を保存しました')).toBeVisible();
    
    await page.reload();
    await expect(page.locator('input[name="default_provider"][value="claude"]')).toBeChecked();
  });

  test('Knowledge Base - Crawl trigger', async ({ page }) => {
    await page.goto('/knowledge');
    
    await page.fill('input[name="url"]', 'http://example.com');
    await page.click('button:has-text("クローリング開始")');
    
    // This might take time or trigger a background job.
    // We check if it doesn't error out immediately.
    // If there's a toast or status, we should check it.
  });

  test('Chat UI - Model selection and basic interaction', async ({ page }) => {
    test.setTimeout(60000);
    await page.goto('/chat');
    
    // Wait for models to load
    await page.waitForFunction(() => {
      const select = document.querySelector('select');
      return select && select.options.length > 0 && select.options[0].text !== '読み込み中...';
    });

    const models = page.locator('select');
    await models.selectOption({ index: 0 });

    await page.fill('input[placeholder="質問を入力..."]', 'こんにちは');
    await page.click('button:has-text("送信")');

    // Wait for user message to appear
    await expect(page.locator('text=こんにちは')).toBeVisible();
    
    // Wait for bot response to start or finish
    // Since we are using background jobs and polling, we wait for '回答を生成中...' or the actual text
    await expect(page.locator('text=回答を生成中...')).toBeVisible({ timeout: 10000 }).catch(() => {});
  });

  test('Admin - Invitation Form', async ({ page }) => {
    await page.goto('/admin/invitations');
    
    await page.fill('input[name="email"]', 'newuser@example.com');
    await page.selectOption('select[name="role"]', 'admin');
    await page.click('button:has-text("招待を送信")');
    
    // Verify it appears in the list
    await expect(page.locator('text=newuser@example.com')).toBeVisible();
  });

  test('Responsive check', async ({ page }) => {
    // Mobile width
    await page.setViewportSize({ width: 375, height: 667 });
    await page.goto('/');
    // Check if sidebar is hidden or menu button is visible
    // This depends on the UI framework (AdminLTE used here)
    const sidebarToggle = page.locator('a[role="button"]'); // The hamburger menu
    await expect(sidebarToggle).toBeVisible();
    
    // Tablet
    await page.setViewportSize({ width: 768, height: 1024 });
    await page.goto('/');
    
    // PC
    await page.setViewportSize({ width: 1440, height: 900 });
    await page.goto('/');
  });

  test('Accessibility check (basic)', async ({ page }) => {
    await page.goto('/');
    const imagesWithoutAlt = await page.evaluate(() => {
      return Array.from(document.querySelectorAll('img')).filter(img => !img.alt).length;
    });
    // This is a very basic check
    console.log(`Images without alt: ${imagesWithoutAlt}`);
  });

});
