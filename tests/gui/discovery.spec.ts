import { test, expect } from '@playwright/test';

test('login and discover routes', async ({ page }) => {
  // Go to login page
  await page.goto('/login');
  await expect(page).toHaveURL(/.*login/);

  // Login
  await page.fill('input[name="email"]', 'admin@example.com');
  await page.fill('input[name="password"]', 'password');
  await page.click('button[type="submit"]');

  // Should be redirected to dashboard or home
  await page.waitForURL('/');
  console.log('Logged in successfully');

  // List all links on the home page
  const links = await page.evaluate(() => {
    return Array.from(document.querySelectorAll('a')).map(a => ({
      text: a.innerText.trim(),
      href: a.href
    }));
  });
  console.log('Discovery Links:', JSON.stringify(links, null, 2));
});
