# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: reply_sideview.spec.ts >> Rice Mail Reply Sideview Tests >> Reply button opens side panel and displays all components
- Location: reply_sideview.spec.ts:14:7

# Error details

```
Test timeout of 30000ms exceeded.
```

```
Error: page.waitForSelector: Test timeout of 30000ms exceeded.
Call log:
  - waiting for locator('.email-item') to be visible

```

# Page snapshot

```yaml
- generic [ref=e2]:
  - navigation [ref=e3]:
    - list [ref=e4]:
      - listitem [ref=e5]:
        - button "" [ref=e6] [cursor=pointer]:
          - generic [ref=e7]: 
    - list [ref=e8]:
      - listitem [ref=e9]:
        - generic [ref=e10]: Administrator
      - listitem [ref=e11]:
        - button "ログアウト" [ref=e13] [cursor=pointer]
  - complementary [ref=e14]:
    - link "Rice" [ref=e15] [cursor=pointer]:
      - /url: /
    - navigation [ref=e17]:
      - menu [ref=e18]:
        - listitem [ref=e19]:
          - link " メール一覧" [ref=e20] [cursor=pointer]:
            - /url: http://localhost
            - generic [ref=e21]: 
            - paragraph [ref=e22]: メール一覧
        - listitem [ref=e23]:
          - link " レポート" [ref=e24] [cursor=pointer]:
            - /url: http://localhost/reports
            - generic [ref=e25]: 
            - paragraph [ref=e26]: レポート
        - listitem [ref=e27]:
          - link " ナレッジベース" [ref=e28] [cursor=pointer]:
            - /url: http://localhost/knowledge
            - generic [ref=e29]: 
            - paragraph [ref=e30]: ナレッジベース
        - listitem [ref=e31]:
          - link " Rice Chat" [ref=e32] [cursor=pointer]:
            - /url: http://localhost/chat
            - generic [ref=e33]: 
            - paragraph [ref=e34]: Rice Chat
        - listitem [ref=e35]: ADMIN
        - listitem [ref=e36]:
          - link " 招待管理" [ref=e37] [cursor=pointer]:
            - /url: http://localhost/admin/invitations
            - generic [ref=e38]: 
            - paragraph [ref=e39]: 招待管理
        - listitem [ref=e40]:
          - link "メール設定" [ref=e41] [cursor=pointer]:
            - /url: http://localhost/settings/mail
            - paragraph [ref=e42]: メール設定
        - listitem [ref=e43]:
          - link " AI設定" [ref=e44] [cursor=pointer]:
            - /url: http://localhost/settings/ai
            - generic [ref=e45]: 
            - paragraph [ref=e46]: AI設定
        - listitem [ref=e47]:
          - link " SSO設定" [ref=e48] [cursor=pointer]:
            - /url: http://localhost/settings/sso
            - generic [ref=e49]: 
            - paragraph [ref=e50]: SSO設定
        - listitem [ref=e51]:
          - link " ステータス管理" [ref=e52] [cursor=pointer]:
            - /url: http://localhost/master/statuses
            - generic [ref=e53]: 
            - paragraph [ref=e54]: ステータス管理
        - listitem [ref=e55]:
          - link " タグ管理" [ref=e56] [cursor=pointer]:
            - /url: http://localhost/master/tags
            - generic [ref=e57]: 
            - paragraph [ref=e58]: タグ管理
  - generic [ref=e59]:
    - generic [ref=e60]:
      - generic:
        - heading [level=1]
    - generic [ref=e63]:
      - generic [ref=e64]:
        - button "" [ref=e65] [cursor=pointer]:
          - generic [ref=e66]: 
        - button "" [ref=e68] [cursor=pointer]:
          - generic [ref=e69]: 
      - generic [ref=e71]:
        - generic [ref=e72]:
          - generic [ref=e73]:
            - generic [ref=e74]:
              - generic [ref=e75]:
                - button "" [ref=e76] [cursor=pointer]:
                  - generic [ref=e77]: 
                - generic [ref=e78] [cursor=pointer]:
                  - checkbox "全表示" [ref=e79]
                  - text: 全表示
                - button " ピン留め" [ref=e81] [cursor=pointer]:
                  - generic [ref=e82]: 
                  - text: ピン留め
              - button " 新規作成" [ref=e83] [cursor=pointer]:
                - generic [ref=e84]: 
                - text: 新規作成
            - generic [ref=e85]:
              - generic [ref=e86]: "担当者:"
              - combobox [ref=e87] [cursor=pointer]:
                - option "全員を表示" [selected]
                - option "未設定"
                - option "Administrator"
                - option "Administrator"
          - generic [ref=e88]:
            - generic [ref=e89]:
              - button "受信" [ref=e90] [cursor=pointer]
              - button "保留" [ref=e91] [cursor=pointer]
              - button "完了" [ref=e92] [cursor=pointer]
              - button "承認待ち" [ref=e93] [cursor=pointer]
            - button "" [ref=e94] [cursor=pointer]:
              - generic [ref=e95]: 
        - generic [ref=e98]:
          - generic [ref=e99]:
            - generic [ref=e101]: 
            - paragraph [ref=e102]: 閲覧するメールを選択してください
          - text:                         
  - contentinfo [ref=e103]:
    - strong [ref=e104]: Rice (FreeScout Modular Architecture)
```

# Test source

```ts
  1  | import { test, expect } from '@playwright/test';
  2  | 
  3  | test.describe('Rice Mail Reply Sideview Tests', () => {
  4  |   
  5  |   test.beforeEach(async ({ page }) => {
  6  |     // Standard login
  7  |     await page.goto('/login');
  8  |     await page.fill('input[name="email"]', 'admin@example.com');
  9  |     await page.fill('input[name="password"]', 'password');
  10 |     await page.click('button[type="submit"]');
  11 |     await page.waitForURL('/');
  12 |   });
  13 | 
  14 |   test('Reply button opens side panel and displays all components', async ({ page }) => {
  15 |     // 1. Load threads and select one
> 16 |     await page.waitForSelector('.email-item');
     |                ^ Error: page.waitForSelector: Test timeout of 30000ms exceeded.
  17 |     const firstThread = page.locator('.email-item').first();
  18 |     await firstThread.click();
  19 | 
  20 |     // 2. Click "返信" button in the header
  21 |     const replyButton = page.locator('button:has-text("返信")').first();
  22 |     await expect(replyButton).toBeVisible();
  23 |     await replyButton.click();
  24 | 
  25 |     // 3. Verify side panel visibility
  26 |     const sidePanel = page.locator('aside:has-text("返信ドラフト")');
  27 |     await expect(sidePanel).toBeVisible();
  28 | 
  29 |     // 4. Verify input fields in side panel using unique data-test-ids
  30 |     await expect(page.locator('[data-test-id="reply-to-label"]')).toBeVisible();
  31 |     await expect(page.locator('[data-test-id="reply-cc-label"]')).toBeVisible();
  32 |     await expect(page.locator('[data-test-id="reply-bcc-label"]')).toBeVisible();
  33 |     await expect(page.locator('[data-test-id="reply-subject-label"]')).toBeVisible();
  34 |     await expect(page.locator('textarea[placeholder="返信内容を入力してください..."]')).toBeVisible();
  35 | 
  36 |     // 5. Verify action buttons in side panel
  37 |     const submitButton = page.locator('aside button:has-text("承認を依頼する")');
  38 |     await expect(submitButton).toBeVisible();
  39 |     
  40 |     const aiButton = page.locator('aside button:has-text("AIアシスタント")');
  41 |     await expect(aiButton).toBeVisible();
  42 | 
  43 |     // 6. Test AI Assistant panel toggle
  44 |     await aiButton.click();
  45 |     const aiPanel = page.locator('aside[x-show="replyAiPanelOpen"]');
  46 |     await expect(aiPanel).toBeVisible();
  47 | 
  48 |     // 7. Close side panels
  49 |     await page.locator('aside:has-text("返信ドラフト") button:has(.fa-times)').click();
  50 |     await expect(sidePanel).toBeHidden();
  51 |   });
  52 | });
  53 | 
```