import { test, expect } from '@playwright/test';

test.describe('Virtual Scrolling Performance', () => {
  
  test.beforeEach(async ({ page }) => {
    await page.goto('/login');
    await page.fill('input[name="email"]', 'admin@example.com');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    await page.waitForURL('/');
  });

  test('Only visible items are rendered in the DOM', async ({ page }) => {
    // 1. Mock 200 threads
    await page.route('**/emails/search*', async route => {
      const threads = Array.from({ length: 200 }, (_, i) => ({
        id: i + 1,
        subject: `Thread #${i + 1}`,
        status: 'inbox',
        last_email_at: '2026/04/29 10:00',
        tags: [],
        latest_email: { from_label: 'Test User', plain_body: 'Hello' }
      }));
      route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify(threads),
      });
    });

    // Reload to apply mock
    await page.reload();
    await page.waitForLoadState('networkidle');

    // 2. Initially, only around 20-30 items should be in DOM (due to buffer)
    const initialRows = await page.locator('.thread-list-row').count();
    console.log(`Initial rows in DOM: ${initialRows}`);
    expect(initialRows).toBeLessThan(40); 
    await expect(page.getByText('Thread #1', { exact: true })).toBeAttached();

    // 3. Scroll down
    const container = page.locator('#email-list-container');
    await container.evaluate(async el => {
        el.scrollTop = 5000;
        el.dispatchEvent(new Event('scroll'));
        // Wait a bit within evaluate to let Alpine process
        await new Promise(r => setTimeout(r, 500));
    });
    await page.waitForTimeout(2000);

    // 4. Thread #1 should be gone from DOM
    await expect(page.getByText('Thread #1', { exact: true })).not.toBeAttached();
    
    // Middle thread should be present
    await expect(page.getByText('Thread #65', { exact: true })).toBeAttached();
  });

});
