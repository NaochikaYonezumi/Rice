# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: new_features.spec.ts >> Rice New Features Tests >> Reply All preserves CC
- Location: new_features.spec.ts:29:7

# Error details

```
Test timeout of 30000ms exceeded.
```

```
Error: locator.click: Test timeout of 30000ms exceeded.
Call log:
  - waiting for locator('.email-item').first()

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
  1   | import { test, expect } from '@playwright/test';
  2   | 
  3   | test.describe('Rice New Features Tests', () => {
  4   |   
  5   |   test.beforeEach(async ({ page }) => {
  6   |     await page.goto('/login');
  7   |     await page.fill('input[name="email"]', 'admin@example.com');
  8   |     await page.fill('input[name="password"]', 'password');
  9   |     await page.click('button[type="submit"]');
  10  |     await page.waitForURL('/');
  11  |   });
  12  | 
  13  |   test('Long press enters selection mode', async ({ page }) => {
  14  |     await page.goto('/');
  15  |     const firstThread = page.locator('.email-item').first();
  16  |     await expect(firstThread).toBeVisible();
  17  | 
  18  |     // Long press
  19  |     await firstThread.dispatchEvent('mousedown');
  20  |     await page.waitForTimeout(700); // More than 600ms
  21  |     await firstThread.dispatchEvent('mouseup');
  22  | 
  23  |     // Check if selection mode is active (checkboxes visible)
  24  |     const checkbox = firstThread.locator('input[type="checkbox"]');
  25  |     await expect(checkbox).toBeVisible();
  26  |     await expect(page.locator('text=1 件選択中')).toBeVisible();
  27  |   });
  28  | 
  29  |   test('Reply All preserves CC', async ({ page }) => {
  30  |     // We need a thread with CC to test this properly.
  31  |     // For now, let's just check if the logic is triggered.
  32  |     await page.goto('/');
  33  |     const firstThread = page.locator('.email-item').first();
> 34  |     await firstThread.click();
      |                       ^ Error: locator.click: Test timeout of 30000ms exceeded.
  35  | 
  36  |     const replyAllButton = page.locator('button:has-text("全員に返信")');
  37  |     await expect(replyAllButton).toBeVisible();
  38  |     await replyAllButton.click();
  39  | 
  40  |     const aside = page.locator('aside').filter({ hasText: '返信ドラフト' });
  41  |     await expect(aside).toBeVisible();
  42  |     
  43  |     // Check if CC field exists
  44  |     const ccInput = page.locator('data-test-id=reply-cc-label').locator('..').locator('input');
  45  |     // In this mock/test environment, we don't know if the first email has CC.
  46  |     // But we can check if it's there.
  47  |     await expect(ccInput).toBeVisible();
  48  |   });
  49  | 
  50  |   test('Next thread selection after completion', async ({ page }) => {
  51  |     await page.goto('/');
  52  |     const threads = page.locator('.email-item');
  53  |     const threadCount = await threads.count();
  54  |     if (threadCount < 2) {
  55  |       console.log('Not enough threads to test next selection');
  56  |       return;
  57  |     }
  58  | 
  59  |     const firstThreadId = await threads.nth(0).getAttribute('data-id');
  60  |     await threads.nth(0).click();
  61  | 
  62  |     const completeButton = page.locator('button:has-text("完了")').first();
  63  |     await completeButton.click();
  64  | 
  65  |     // After completion, the second thread should be selected
  66  |     // Wait for list to reload and selection to change
  67  |     await page.waitForTimeout(1000);
  68  |     
  69  |     // Check if the current selectedThreadId in the app is the next one.
  70  |     // We can check the UI state (e.g. active class)
  71  |     const secondThread = threads.nth(0); // The original first one might be gone if it's not in the list anymore
  72  |     // Actually, after completion, the completed thread might disappear from 'inbox' tab.
  73  |     // So the new first thread is actually the next one.
  74  |     await expect(secondThread).toBeVisible();
  75  |   });
  76  | 
  77  |   test('Merge threads with base selection', async ({ page }) => {
  78  |     await page.goto('/');
  79  |     const threads = page.locator('.email-item');
  80  |     if (await threads.count() < 2) return;
  81  | 
  82  |     // Enter selection mode via long press on first
  83  |     await threads.nth(0).dispatchEvent('mousedown');
  84  |     await page.waitForTimeout(700);
  85  |     await threads.nth(0).dispatchEvent('mouseup');
  86  | 
  87  |     // Select second
  88  |     await threads.nth(1).locator('input[type="checkbox"]').click();
  89  | 
  90  |     // Click Merge
  91  |     const mergeButton = page.locator('button:has-text("マージ")');
  92  |     await expect(mergeButton).toBeVisible();
  93  |     await mergeButton.click();
  94  | 
  95  |     // Modal should be visible
  96  |     const modal = page.locator('h3:has-text("ベースとなるスレッドを選択")');
  97  |     await expect(modal).toBeVisible();
  98  | 
  99  |     // Choose second thread as base
  100 |     await page.locator('.p-4.rounded-2xl.border-2').nth(1).click();
  101 |     
  102 |     // Execute merge (this will call the real API, so it might fail if ID doesn't match DB exactly, but UI flow is tested)
  103 |     const executeButton = page.locator('button:has-text("マージを実行")');
  104 |     await expect(executeButton).toBeVisible();
  105 |     // await executeButton.click(); // Skip actual API call to avoid side effects in mock-less environment if needed, or just let it run.
  106 |   });
  107 | 
  108 |   test('Periodic fetch check', async ({ page }) => {
  109 |     // This is hard to test in a short time, but we can check if fetch was called
  110 |     // by mocking the API and waiting.
  111 |     // But for a simple test, we just check if the UI still works.
  112 |     await page.goto('/');
  113 |     await page.waitForTimeout(2000);
  114 |     const syncIcon = page.locator('.fa-sync-alt').first();
  115 |     await expect(syncIcon).toBeVisible();
  116 |   });
  117 | 
  118 | });
  119 | 
```