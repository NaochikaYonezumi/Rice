import { test, expect } from '@playwright/test';
import * as fs from 'fs';

const routes = [
  '/',
  '/reports',
  '/knowledge',
  '/chat',
  '/admin/invitations',
  '/settings/mail',
  '/settings/ai',
  '/settings/sso',
  '/master/statuses',
  '/master/tags'
];

test('inventory GUI elements', async ({ page }) => {
  // Login
  await page.goto('/login');
  await page.fill('input[name="email"]', 'admin@example.com');
  await page.fill('input[name="password"]', 'password');
  await page.click('button[type="submit"]');
  await page.waitForURL('/');

  const inventory = [];

  for (const route of routes) {
    await page.goto(route);
    await page.waitForLoadState('networkidle');

    const elements = await page.evaluate((currentRoute) => {
      const result = [];
      const interactives = document.querySelectorAll('button, input, select, textarea, a[href]');
      
      interactives.forEach(el => {
        // Skip hidden elements
        if (el instanceof HTMLElement && (el.offsetWidth === 0 || el.offsetHeight === 0)) return;

        let type = el.tagName.toLowerCase();
        if (el instanceof HTMLInputElement) {
          type = el.getAttribute('type') || 'text';
        }

        result.push({
          route: currentRoute,
          tagName: el.tagName.toLowerCase(),
          type: type,
          id: el.id,
          name: el.getAttribute('name'),
          text: el.innerText.trim(),
          placeholder: el.getAttribute('placeholder'),
          role: el.getAttribute('role')
        });
      });
      return result;
    }, route);

    inventory.push(...elements);
  }

  fs.writeFileSync('inventory.json', JSON.stringify(inventory, null, 2));
});
