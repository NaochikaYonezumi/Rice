# Phase 0: 環境セットアップ

## 実施内容
- **モジュールディレクトリの準備**: `laravel/Modules` ディレクトリを作成しました。すべてのカスタム機能はこの配下に実装されます。
- **インフラ構成の更新**:
  - `docker-compose.yml` に **PostgreSQL + pgvector** (`ankane/pgvector`) を追加しました。
  - サービス名: `postgres`
  - ポート: `5432`
  - データベース名: `rice_vector`
  - 不要となった `chromadb` サービスおよびボリュームを削除しました。
- **FreeScout コンテキストの確認**: 現状、ディレクトリ直下に FreeScout のコアは存在しませんが、今後 `/Modules` 配下でモジュール開発を行うための構造を構築します。

## 技術スタックの確定
- Web: Laravel (PHP 8.3)
- DB: MySQL (メール用), PostgreSQL + pgvector (AI用)
- LLM: Ollama
- アーキテクチャ: モジュラーアーキテクチャ

## 次のステップ
- Phase 1: 認証基盤 (Microsoft Entra ID) と AdminLTE/Bootstrap UI の統合
# Phase 1: 認証基盤と UI 刷新

## 実施内容
- **Microsoft Entra ID OAuth 実装**:
  - `socialiteproviders/microsoft-azure` を導入。
  - `OAuthLogin` モジュールを `/Modules/OAuthLogin` に作成。
  - マルチテナント対応のリダイレクトおよびコールバック処理を実装。
  - 既存ユーザーとの紐付けおよび自動登録機能を構築。
- **UI の AdminLTE/Bootstrap 移行**:
  - `layouts/app.blade.php` を AdminLTE ベースに刷新。
  - ログイン画面 (`auth/login.blade.php`) を AdminLTE スタイルに更新。
  - Tailwind 依存の排除を開始。
- **モジュール ServiceProvider の登録**:
  - `Modules\OAuthLogin\Providers\OAuthLoginServiceProvider` を `bootstrap/providers.php` に追加。

## 構成
- ルート:
  - `/auth/microsoft/redirect` (Microsoft ログイン開始)
  - `/auth/microsoft/callback` (コールバック)
- ビュー: `oauthlogin::login` (AdminLTE)

## 次のステップ
- Phase 2: コアメール処理 (POP3/SMTP) とスレッドオーナーシップの実装
# Phase 2: コアメール処理とスレッド管理

## 実施内容
- **MailClient モジュールの構築**:
  - `webklex/laravel-imap` を導入し、POP3/IMAP 取得の基盤を構築。
  - `EmailFetcher` サービスを実装。Message-ID による重複検出、スレッドの自動紐付け（In-Reply-To/References 考慮）、添付ファイルの `/storage/attachments/` 保存をサポート。
  - `EmailSender` サービスを実装。SMTP 送信、エージェント署名の付与、返信時のスレッドオーナーシップ（最終返信エージェントへの割り当て）を自動化。
- **スケジュールタスク**:
  - `mail:fetch` コマンドを作成。
- **ファイル管理**:
  - 添付ファイル最大サイズ 20MB に対応するためのバリデーション準備。

## 構成
- モジュール: `Modules/MailClient`
- コマンド: `php artisan mail:fetch`
- 保存先: `/storage/app/attachments/`

## 次のステップ
- Phase 3: ワークフローエンジンとレポート機能の実装
# Phase 3: ワークフローエンジンとレポート

## 実施内容
- **Workflow モジュールの構築**:
  - `Modules/Workflow` ディレクトリを作成し、Service, Controller, View を配置。
  - **ワークフローエンジン**: `WorkflowEngine` を実装。件名や送信元アドレスに基づく自動タグ付け、担当者割り当てをサポート。
  - **ラウンドロビン**: 担当者が未割り当てのスレッドに対し、メンバー間で均等に割り振るロジックを実装。履歴管理用に `ext_workflow_round_robin` テーブルを導入。
  - **レポート機能**: `ReportService` により、総スレッド数、未対応数、エージェント別返信数、ステータス分布、タグ統計を集計。
- **UI 統合**:
  - AdminLTE スタイルを採用したレポートダッシュボード (`/reports`) を作成。
  - サイドバーに「レポート」メニューを追加。

## 構成
- データベース: `ext_workflow_rules`, `ext_workflow_round_robin`
- モジュール: `Modules/Workflow`
- エンドポイント: `GET /reports`

## 次のステップ
- Phase 4: ナレッジベースとベクターDB統合 (RAG 連携)
# Phase 4: ナレッジベースとベクターDB統合

## 実施内容
- **Python 側の RAG エンジン更新**:
  - `scraper.py`: 再帰的クローリングロジックを実装。内部リンクのみを追跡し、外部ドメインを無視。
  - `rag_engine.py`: LlamaIndex と PostgreSQL + pgvector の統合を実装。埋め込みデータの保存と検索（Top-5）をサポート。
  - `main.py`: クロール実行 (`/scrape`) とクエリ検索 (`/query`) のエンドポイントを公開。
- **PHP 側の Knowledge モジュール構築**:
  - `Modules/Knowledge` ディレクトリを作成。
  - `NeuronService`: Python API と通信するクライアントを実装。
  - **管理 UI**: クロール対象 URL を登録できる AdminLTE 画面を作成。

## 構成
- ベクターDB: PostgreSQL + pgvector ( rice_vector DB )
- RAG フレームワーク: LlamaIndex
- モジュール: `Modules/Knowledge`

## 次のステップ
- Phase 5: AI 返信アシスタント (UI ボタンと生成フローの統合)
# Phase 5: AI 返信アシスタント

## 実施内容
- **AIReply モジュールの構築**:
  - `Modules/AIReply` ディレクトリを作成し、Service, Controller, Provider を配置。
  - **AI 返信案の生成**: `AIReplyService` を実装。RAG エンジン（Phase 4）から取得したナレッジとメール本文を統合し、最適な返信案を構築。
  - **プロンプト制御**: 丁寧なトーンの指定、確信度スコアの付与、および不確実な情報の断定禁止などのセーフティルールをプロンプトに組み込みました。
  - **AI ログ**: `ext_ai_logs` テーブルを作成。生成された内容、プロンプトの要約、確信度スコア、および実行ユーザーを記録。
- **UI 統合**:
  - スレッド詳細画面（または返信画面）から AI 生成をキックするためのエンドポイント `/threads/{thread}/ai-generate` を公開。

## 構成
- データベース: `ext_ai_logs`
- モジュール: `Modules/AIReply`
- エンドポイント: `POST /threads/{thread}/ai-generate`

## 完了
全開発フェーズが終了しました。
Rice プロジェクトは、Microsoft Entra ID 認証、POP3/SMTP メール処理、ワークフローエンジン、ナレッジベース（RAG）、および AI 返信アシスタントを備えたモジュラーシステムとして構築されました。
# メールサーバー設定例

`laravel/.env` に設定してください。

---

## 汎用SMTPサーバー（自社・レンタルサーバー）

```env
# 送信（SMTP）
MAIL_HOST=mail.example.com
MAIL_PORT=587
MAIL_ENCRYPTION=tls
MAIL_USERNAME=user@example.com
MAIL_PASSWORD=password

# 受信（IMAP）
MAIL_INBOX_PROTOCOL=imap
MAIL_IMAP_HOST=mail.example.com
MAIL_IMAP_PORT=993
MAIL_IMAP_ENCRYPTION=ssl
MAIL_IMAP_USERNAME=user@example.com
MAIL_IMAP_PASSWORD=password
MAIL_IMAP_FOLDER=INBOX
```

---

## さくらインターネット

```env
MAIL_HOST=smtp.example.sakura.ne.jp
MAIL_PORT=587
MAIL_ENCRYPTION=tls
MAIL_USERNAME=user@example.sakura.ne.jp
MAIL_PASSWORD=password

MAIL_INBOX_PROTOCOL=imap
MAIL_IMAP_HOST=imap.example.sakura.ne.jp
MAIL_IMAP_PORT=993
MAIL_IMAP_ENCRYPTION=ssl
MAIL_IMAP_USERNAME=user@example.sakura.ne.jp
MAIL_IMAP_PASSWORD=password
```

---

## Xserver

```env
MAIL_HOST=sv****.xserver.jp
MAIL_PORT=587
MAIL_ENCRYPTION=tls
MAIL_USERNAME=user@example.com
MAIL_PASSWORD=password

MAIL_INBOX_PROTOCOL=imap
MAIL_IMAP_HOST=sv****.xserver.jp
MAIL_IMAP_PORT=993
MAIL_IMAP_ENCRYPTION=ssl
MAIL_IMAP_USERNAME=user@example.com
MAIL_IMAP_PASSWORD=password
```

---

## POP3を使う場合（共通）

```env
MAIL_INBOX_PROTOCOL=pop3
MAIL_POP_HOST=mail.example.com
MAIL_POP_PORT=995
MAIL_POP_ENCRYPTION=ssl
MAIL_POP_USERNAME=user@example.com
MAIL_POP_PASSWORD=password
```

---

## 暗号化なし（社内サーバー等）

```env
MAIL_PORT=25
MAIL_ENCRYPTION=null

MAIL_IMAP_PORT=143
MAIL_IMAP_ENCRYPTION=null
# または
MAIL_POP_PORT=110
MAIL_POP_ENCRYPTION=null
```

---

## 設定後の確認

```bash
# メール取得テスト（コンテナ内から）
docker compose exec laravel php artisan tinker
>>> app(\App\Services\EmailFetchService::class)->fetch()
```
# Rice プロジェクト リファレンス

> 最終更新: 2026-05-02  
> スタック: Laravel 11 + Blade + Alpine.js + Tailwind CSS + AdminLTE 3.2  
> PHP 8.2 / Node.js (Vite)

---

## 1. ディレクトリ構成

```
Rice/
├── docker-compose.yml          # Laravel / RAG API / Ollama の3サービス構成
├── docs/
│   └── project-reference.md   # 本ファイル
└── laravel/
    ├── app/
    │   ├── Http/
    │   │   ├── Controllers/    # リクエスト処理・レスポンス返却
    │   │   │   ├── Admin/      # 管理者専用コントローラー
    │   │   │   └── Auth/       # 認証フロー
    │   │   ├── Middleware/     # リクエスト前後処理
    │   │   └── Requests/       # バリデーション定義
    │   ├── Jobs/               # キューワーカーで実行する非同期処理
    │   ├── Mail/               # Mailable クラス（メール送信テンプレート）
    │   ├── Models/             # Eloquent モデル
    │   ├── Notifications/      # 通知クラス（DBチャンネル）
    │   ├── Providers/          # サービスプロバイダー
    │   └── Services/           # ビジネスロジックのサービス層
    ├── config/
    │   └── ai_skills.php       # AIスキル定義（システムプロンプト）
    ├── database/
    │   ├── factories/
    │   ├── migrations/         # テーブル作成・変更履歴（36ファイル）
    │   └── seeders/
    ├── Modules/                # モジュール式拡張機能
    │   ├── AIReply/            # AI返信生成
    │   ├── Knowledge/          # ナレッジベース（クロール）
    │   ├── MailClient/         # IMAPメール取得
    │   └── Workflow/           # レポート
    ├── resources/
    │   ├── css/app.css
    │   ├── js/app.js
    │   └── views/
    │       ├── admin/          # 管理者専用画面
    │       ├── approvals/      # 承認ページ
    │       ├── attachments/    # 添付ファイル一覧
    │       ├── auth/           # ログイン・登録・招待受諾
    │       ├── chat/           # RAGチャット・スクレイプ
    │       ├── components/     # 共通 Blade コンポーネント
    │       ├── documents/      # ドキュメント管理
    │       ├── drafts/         # 下書き一覧
    │       ├── emails/         # メイン受信トレイ
    │       ├── layouts/        # 共通レイアウト
    │       ├── profile/        # プロフィール編集
    │       ├── settings/       # 各種設定画面
    │       └── tags/           # タグビューア
    ├── routes/
    │   ├── auth.php            # 認証ルート（Breeze）
    │   ├── console.php         # Artisan コマンド
    │   └── web.php             # アプリケーションルート
    └── tests/
        ├── Feature/            # 統合テスト
        └── Unit/               # ユニットテスト
```

---

## 2. ルート一覧

### routes/web.php — 公開ルート

| メソッド | パス | コントローラー | 名前 |
|---|---|---|---|
| GET | `/auth/{provider}/redirect` | SocialiteController@redirect | auth.redirect |
| GET | `/auth/{provider}/callback` | SocialiteController@callback | auth.callback |
| GET | `/invitations/accept/{token}` | InvitationAcceptController@show | invitations.accept |
| POST | `/invitations/accept/{token}` | InvitationAcceptController@store | invitations.accept.store |

### routes/web.php — 認証済みルート（auth + verified）

| メソッド | パス | コントローラー | 名前 |
|---|---|---|---|
| GET | `/` | EmailController@index | emails.index |
| GET | `/emails/pinned` | EmailController@pinned | emails.pinned |
| GET | `/emails/search` | EmailController@search | emails.search |
| GET | `/emails/{email}` | EmailController@show | emails.show |
| POST | `/emails/{email}/ai` | EmailController@askAi | emails.ai |
| POST | `/emails/{email}/reply` | EmailController@reply | emails.reply |
| POST | `/emails/compose` | EmailController@compose | emails.compose |
| POST | `/emails/fetch` | EmailController@fetch | emails.fetch |
| GET | `/threads/{thread}` | EmailController@thread | threads.show |
| PUT | `/threads/{thread}/tags` | EmailController@updateTags | threads.tags |
| PUT | `/threads/{thread}/status` | EmailController@updateStatus | threads.status |
| POST | `/threads/{thread}/pin` | EmailController@togglePin | threads.pin |
| PUT | `/threads/{thread}/assignee` | EmailController@updateAssignee | threads.assignee |
| DELETE | `/threads/{thread}` | EmailController@deleteThread | threads.delete |
| POST | `/threads/{thread}/merge` | ThreadMergeController@merge | threads.merge |
| DELETE | `/thread-merges/{threadMerge}` | ThreadMergeController@unmerge | thread-merges.unmerge |
| POST | `/threads/{thread}/assign-customer` | CustomerController@assign | threads.assign-customer |
| POST | `/emails/bulk-assign-customer` | EmailController@bulkAssignCustomer | emails.bulk-assign-customer |
| GET | `/threads/{thread}/memos` | ThreadMemoController@index | threads.memos.index |
| POST | `/threads/{thread}/memos` | ThreadMemoController@store | threads.memos.store |
| GET | `/threads/{thread}/comments` | ThreadCommentController@index | threads.comments.index |
| POST | `/threads/{thread}/comments` | ThreadCommentController@store | threads.comments.store |
| DELETE | `/thread-comments/{comment}` | ThreadCommentController@destroy | threads.comments.destroy |
| GET | `/users` | EmailController@users | users.index |
| GET | `/attachments` | AttachmentController@index | attachments.index |
| GET | `/attachments/{attachment}` | EmailController@downloadAttachment | attachments.download |
| GET | `/pending-emails` | PendingEmailController@index | pending.index |
| POST | `/pending-emails/{pending}/approve` | PendingEmailController@approve | pending.approve |
| POST | `/pending-emails/{pending}/reject` | PendingEmailController@reject | pending.reject |
| GET | `/notifications` | （クロージャ） | notifications.index |
| POST | `/notifications/{id}/read` | （クロージャ） | notifications.read |
| POST | `/notifications/read-all` | （クロージャ） | notifications.read-all |
| GET | `/approvals` | ApprovalController@index | approvals.index |
| GET | `/drafts` | DraftController@index | drafts.index |
| GET | `/drafts/list` | DraftController@list | drafts.list |
| POST | `/drafts/{draft}/submit` | DraftController@submit | drafts.submit |
| DELETE | `/drafts/{draft}` | DraftController@destroy | drafts.destroy |
| GET | `/customers` | CustomerController@index | customers.index |
| POST | `/customers` | CustomerController@store | customers.store |
| PUT | `/customers/{customer}` | CustomerController@update | customers.update |
| DELETE | `/customers/{customer}` | CustomerController@destroy | customers.destroy |
| GET | `/customers/data` | CustomerController@data | customers.data |
| POST | `/customers/reorder` | CustomerController@reorder | customers.reorder |
| POST | `/customers/{customer}/move` | CustomerController@moveToGroup | customers.move |
| GET | `/customer-groups` | CustomerGroupController@index | customer-groups.index |
| POST | `/customer-groups` | CustomerGroupController@store | customer-groups.store |
| PUT | `/customer-groups/{group}` | CustomerGroupController@update | customer-groups.update |
| DELETE | `/customer-groups/{group}` | CustomerGroupController@destroy | customer-groups.destroy |
| POST | `/customer-groups/reorder` | CustomerGroupController@reorder | customer-groups.reorder |
| GET | `/tags` | TagController@index | tags.index |
| GET | `/tags/data` | TagController@data | tags.data |
| GET | `/tag-notes/{tag}` | TagNoteController@show | tag-notes.show |
| PUT | `/tag-notes/{tag}` | TagNoteController@update | tag-notes.update |
| GET | `/knowledge` | KnowledgeController@index | knowledge.index |
| POST | `/knowledge/crawl` | KnowledgeController@crawl | knowledge.crawl |
| GET | `/reports` | ReportController@index | reports.index |
| POST | `/threads/{thread}/ai-generate` | AIReplyController@generate | ai.generate |
| GET | `/documents` | DocumentController@index | documents.index |
| POST | `/documents` | DocumentController@store | documents.store |
| DELETE | `/documents/{document}` | DocumentController@destroy | documents.destroy |
| GET | `/chat` | ChatController@index | chat.index |
| GET | `/chat/models` | ChatController@models | chat.models |
| POST | `/query` | ChatController@query | chat.query |
| GET | `/query/{id}/result` | ChatController@result | chat.result |
| GET | `/scrape` | ScrapeController@index | scrape.index |
| POST | `/scrape` | ScrapeController@store | scrape.store |
| DELETE | `/scrape/url/{scrapedUrl}` | ScrapeController@destroyUrl | scrape.url.destroy |
| DELETE | `/scrape/collection/{collection}` | ScrapeController@destroy | scrape.destroy |
| GET | `/settings/ai/default-prompt` | SettingsController@getDefaultPrompt | settings.ai.default-prompt.get |
| POST | `/settings/ai/default-prompt` | SettingsController@saveDefaultPrompt | settings.ai.default-prompt.save |
| GET/POST | `/profile` | ProfileController | profile.edit / profile.update |
| DELETE | `/profile` | ProfileController@destroy | profile.destroy |

### routes/web.php — 管理者専用ルート（admin ミドルウェア）

| メソッド | パス | コントローラー | 名前 |
|---|---|---|---|
| GET/POST | `/settings/mail` | SettingsController@mail/updateMail | settings.mail / .update |
| GET/POST | `/settings/ai` | SettingsController@ai/updateAi | settings.ai / .update |
| GET/POST | `/settings/sso` | SettingsController@sso/updateSso | settings.sso / .update |
| GET | `/admin/invitations` | InvitationController@index | admin.invitations.index |
| POST | `/admin/invitations` | InvitationController@store | admin.invitations.store |
| DELETE | `/admin/invitations/{invitation}` | InvitationController@destroy | admin.invitations.destroy |
| GET | `/master/statuses` | StatusMasterController@index | master.statuses |
| POST | `/master/statuses` | StatusMasterController@store | master.statuses.store |
| GET | `/master/tags` | MasterTagController@index | master.tags |
| POST | `/master/tags` | MasterTagController@store | master.tags.store |
| PUT | `/master/tags/{tag}` | MasterTagController@update | master.tags.update |
| DELETE | `/master/tags/{tag}` | MasterTagController@destroy | master.tags.destroy |
| POST | `/master/tags/reorder` | MasterTagController@reorder | master.tags.reorder |

---

## 3. コントローラー一覧

### EmailController
`app/Http/Controllers/EmailController.php`

メール管理のコアコントローラー。

| メソッド | 入力 | 出力 | 説明 |
|---|---|---|---|
| `index()` | — | `View` | 受信トレイ画面（`emails.index`） |
| `pinned()` | — | `View` | ピン留め一覧（`isPinnedView=true`） |
| `fetch(EmailFetcher)` | `$fetcher` | `JsonResponse` | IMAPメール取得。`{status, count}` |
| `askAi(Request, Email)` | `prompt, skill, mask_pii` | `JsonResponse` | RAG API経由でAI返信生成。`{answer, skill_used, sources}` |
| `reply(Request, Email)` | `from_address, to, cc, bcc, body, created_by, attachments[]` | `JsonResponse` | 返信ドラフト保存。`save_as_draft=1` で下書き。`{status, id}` |
| `compose(Request)` | `from_address, to, cc, bcc, body, subject, created_by, attachments[]` | `JsonResponse` | 新規作成ドラフト保存。`{status, id}` |
| `search(Request)` | `q, tags[], status, customer_id, group_id, all_status, is_pinned, assigned_user_id, sort_key, sort_order` | `JsonResponse` | スレッド一覧検索。配列を返す |
| `togglePin(Request, EmailThread)` | `is_pinned?` | `JsonResponse` | ピン留めトグル。`{status, is_pinned}` |
| `updateAssignee(Request, EmailThread)` | `assigned_user_id` | `JsonResponse` | 担当者更新。`{status}` |
| `users()` | — | `JsonResponse` | ユーザー一覧。`[{id, name, email}]` |
| `thread(EmailThread)` | — | `JsonResponse` | スレッド詳細。`{thread, emails[], merges[]}` |
| `updateStatus(Request, EmailThread)` | `status` | `JsonResponse` | ステータス更新。`{status}` |
| `deleteThread(EmailThread)` | — | `JsonResponse` | スレッド削除。`{status}` |
| `updateTags(Request, EmailThread)` | `tags[]` | `JsonResponse` | タグ更新。`{status}` |
| `bulkAssignCustomer(Request)` | `thread_ids[], customer_id` | `JsonResponse` | 一括顧客割り当て。`{success, updated}` |

---

### PendingEmailController
`app/Http/Controllers/PendingEmailController.php`

承認ワークフロー管理。

| メソッド | 入力 | 出力 | 説明 |
|---|---|---|---|
| `index(Request)` | `status?` | `JsonResponse` | 承認待ちメール一覧。ステータスフィルター対応 |
| `approve(PendingEmail, EmailFetcher)` | — | `JsonResponse` | SMTP送信・承認記録。作成者=承認者の場合は拒否 |
| `reject(PendingEmail)` | — | `JsonResponse` | 却下処理 |

---

### DraftController
`app/Http/Controllers/DraftController.php`

下書き管理。

| メソッド | 入力 | 出力 | 説明 |
|---|---|---|---|
| `index()` | — | `View` | 下書き一覧画面 |
| `list()` | — | `JsonResponse` | 自分の下書き一覧。`[{id, subject, to_address, body_preview, ...}]` |
| `submit(Request, PendingEmail)` | — | `JsonResponse` | 下書き→承認依頼に変換。管理者に通知 |
| `destroy(PendingEmail)` | — | `JsonResponse` | 下書き削除 |

---

### CustomerController
`app/Http/Controllers/CustomerController.php`

| メソッド | 入力 | 出力 | 説明 |
|---|---|---|---|
| `index()` | — | `JsonResponse` | 全顧客一覧 |
| `store(Request)` | `name, email?, domain?, notes?, group_id?` | `JsonResponse` | 顧客作成。メールアドレスで既存スレッドを自動紐付け |
| `update(Request, Customer)` | `name, email?, domain?, notes?, group_id?` | `JsonResponse` | 顧客更新 |
| `assign(Request, EmailThread)` | `customer_id` | `JsonResponse` | スレッドに顧客を割り当て |
| `data()` | — | `JsonResponse` | 顧客＋紐付きスレッドの階層データ |
| `reorder(Request)` | `ids[]` | `JsonResponse` | 並び順更新 |
| `moveToGroup(Request, Customer)` | `group_id` | `JsonResponse` | グループ移動 |
| `destroy(Customer)` | — | `JsonResponse` | 顧客削除 |

---

### CustomerGroupController
`app/Http/Controllers/CustomerGroupController.php`

| メソッド | 入力 | 出力 | 説明 |
|---|---|---|---|
| `index()` | — | `JsonResponse` | 3階層の顧客グループ一覧（再帰ロード） |
| `store(Request)` | `name, parent_id?` | `JsonResponse` | グループ作成 |
| `update(Request, CustomerGroup)` | `name, parent_id?` | `JsonResponse` | グループ更新 |
| `reorder(Request)` | `ids[], parent_id?` | `JsonResponse` | 並び順・親グループ変更 |
| `destroy(CustomerGroup)` | — | `JsonResponse` | グループ削除 |

---

### SettingsController
`app/Http/Controllers/SettingsController.php`

| メソッド | 入力 | 出力 | 説明 |
|---|---|---|---|
| `mail()` | — | `View` | メール設定画面 |
| `updateMail(Request)` | SMTP/IMAP/POP3の全設定値 | `RedirectResponse` | メール設定保存 |
| `ai(RagApiService)` | — | `View` | AI設定画面（利用可能モデル一覧付き） |
| `updateAi(Request)` | `anthropic_api_key?, gemini_api_key?, default_provider, default_model, ...` | `RedirectResponse` | AI設定保存 |
| `sso()` | — | `View` | SSO設定画面 |
| `updateSso(Request)` | `is_enabled, google_client_id?, ...` | `RedirectResponse` | SSO設定保存 |
| `getDefaultPrompt()` | — | `JsonResponse` | デフォルトプロンプト取得 |
| `saveDefaultPrompt(Request)` | `prompt` | `JsonResponse` | デフォルトプロンプト保存 |

---

### ThreadMergeController
`app/Http/Controllers/ThreadMergeController.php`

| メソッド | 入力 | 出力 | 説明 |
|---|---|---|---|
| `merge(Request, EmailThread)` | `merge_thread_id` | `JsonResponse` | スレッドを仮想マージ（データ移動なし） |
| `unmerge(ThreadMerge)` | — | `JsonResponse` | マージ解除・件名復元 |

---

### AttachmentController
`app/Http/Controllers/AttachmentController.php`

| メソッド | 入力 | 出力 | 説明 |
|---|---|---|---|
| `index(Request)` | `q?, type?, from?, to?, customer?` | `JsonResponse \| View` | 添付ファイル検索 |

---

### ChatController
`app/Http/Controllers/ChatController.php`

| メソッド | 入力 | 出力 | 説明 |
|---|---|---|---|
| `index()` | — | `View` | チャット画面 |
| `models()` | — | `JsonResponse` | 利用可能なLLMモデル一覧 |
| `query(Request)` | `question, provider?, model?` | `JsonResponse` | チャットクエリ作成（ProcessChatQueryジョブをディスパッチ）。`{id}` |
| `result(string $id)` | — | `JsonResponse` | クエリ結果取得。`{status, answer?, sources?}` |

---

### ScrapeController
`app/Http/Controllers/ScrapeController.php`

| メソッド | 入力 | 出力 | 説明 |
|---|---|---|---|
| `index()` | — | `View` | スクレイプ管理画面 |
| `store(Request)` | `url, collection?` | `JsonResponse` | URLをスクレイプしてRAG APIに投入 |
| `destroyUrl(ScrapedUrl)` | — | `JsonResponse` | URLレコード削除 |
| `destroy(string $collection)` | — | `JsonResponse` | コレクション全削除 |

---

### DocumentController
`app/Http/Controllers/DocumentController.php`

| メソッド | 入力 | 出力 | 説明 |
|---|---|---|---|
| `index()` | — | `View` | ドキュメント一覧 |
| `store(Request)` | `file (max 20MB)` | `JsonResponse` | アップロード＋RAG API /ingest-file に送信 |
| `destroy(Document)` | — | `JsonResponse` | ドキュメント削除 |

---

### StatusMasterController
`app/Http/Controllers/StatusMasterController.php`

| メソッド | 入力 | 出力 | 説明 |
|---|---|---|---|
| `index()` | — | `View \| JsonResponse` | ブラウザ: 管理画面。API: ステータス一覧 |
| `store(Request)` | `name, key, color` | `JsonResponse` | ステータス作成 |

---

### MasterTagController
`app/Http/Controllers/MasterTagController.php`

| メソッド | 入力 | 出力 | 説明 |
|---|---|---|---|
| `index()` | — | `View \| JsonResponse` | ブラウザ: 管理画面。API: タグ一覧 |
| `store(Request)` | `name, color` | `JsonResponse` | タグ作成（重複防止） |
| `update(Request, Tag)` | `name, color` | `JsonResponse` | タグ更新 |
| `destroy(Tag)` | — | `JsonResponse` | タグ削除（全スレッドからも除去） |
| `reorder(Request)` | `ids[]` | `JsonResponse` | 並び順更新 |

---

### TagController / TagNoteController
`app/Http/Controllers/Tag*.php`

| メソッド | 入力 | 出力 | 説明 |
|---|---|---|---|
| `TagController::index()` | — | `View` | タグビューア画面 |
| `TagController::data()` | — | `array` | タグ→スレッドのマップ |
| `TagNoteController::show(string)` | `$tag` | `JsonResponse` | タグのノート取得。`[{title, body}]` |
| `TagNoteController::update(Request, string)` | `content[]` | `JsonResponse` | タグのノート更新 |

---

### ThreadMemoController / ThreadCommentController

| メソッド | 入力 | 出力 | 説明 |
|---|---|---|---|
| `MemoController::store(Request, EmailThread)` | `content` | `JsonResponse` | メモ作成 |
| `MemoController::index(EmailThread)` | — | `JsonResponse` | メモ一覧 |
| `CommentController::store(Request, EmailThread)` | `content` | `JsonResponse` | コメント作成 |
| `CommentController::index(EmailThread)` | — | `JsonResponse` | コメント一覧 |
| `CommentController::destroy(ThreadComment)` | — | `JsonResponse` | コメント削除 |

---

### Auth コントローラー

| クラス | 主なメソッド | 説明 |
|---|---|---|
| `AuthenticatedSessionController` | `create(): View` / `store(Request)` / `destroy(Request)` | ログイン・ログアウト |
| `RegisteredUserController` | `create(): View` / `store(Request)` | 新規登録（設定で無効化可） |
| `SocialiteController` | `redirect(string)` / `callback(string)` | Google/Azure OAuthフロー |
| `InvitationAcceptController` | `show(string)` / `store(Request, string)` | 招待リンク経由の登録 |
| `Admin/InvitationController` | `index()` / `store(Request)` / `destroy(Invitation)` | 招待メール送信・管理 |

---

## 4. モデル一覧

### EmailThread
`app/Models/EmailThread.php`

| 属性 | 型 | 説明 |
|---|---|---|
| `id` | int | PK |
| `subject` | string | 件名 |
| `status` | string | `inbox` / `hold` / `completed` / `pending` |
| `tags` | array (JSON) | タグ名の配列 |
| `customer_id` | int? | 顧客FK |
| `assigned_user_id` | int? | 担当ユーザーFK |
| `is_pinned` | bool | ピン留めフラグ |
| `last_email_at` | datetime? | 最終メール受信日時 |

| リレーション / メソッド | 説明 |
|---|---|
| `emails()` | HasMany Email |
| `latestEmail()` | HasOne Email（最新） |
| `customer()` | BelongsTo Customer |
| `assignee()` | BelongsTo User |
| `threadMerges()` | HasMany ThreadMerge |
| `threadMemos()` | HasMany ThreadMemo |
| `threadComments()` | HasMany ThreadComment |

---

### Email
`app/Models/Email.php`

| 属性 | 型 | 説明 |
|---|---|---|
| `thread_id` | int | スレッドFK |
| `message_id` | string (unique) | メールMessage-ID |
| `in_reply_to` | string? | In-Reply-Toヘッダ |
| `subject` | string | 件名 |
| `from_address` | string | 送信元アドレス |
| `from_name` | string? | 送信元名 |
| `to_address` | string | 宛先 |
| `cc` | string? | CC |
| `bcc` | string? | BCC |
| `body_text` | text | テキスト本文 |
| `body_html` | text? | HTML本文 |
| `is_read` | bool | 既読フラグ |
| `received_at` | datetime | 受信日時 |

| メソッド | 説明 |
|---|---|
| `from_label` (appended) | `from_name ?? from_address` |
| `plain_body` (appended) | HTMLを除去したテキスト |
| `attachments()` | HasMany EmailAttachment |
| `thread()` | BelongsTo EmailThread |

---

### PendingEmail
`app/Models/PendingEmail.php`

| 定数 | 値 |
|---|---|
| `STATUS_DRAFT` | `'draft'` |
| `STATUS_PENDING` | `'pending'` |
| `STATUS_APPROVED` | `'approved'` |
| `STATUS_REJECTED` | `'rejected'` |
| `TYPE_COMPOSE` | `'compose'` |
| `TYPE_REPLY` | `'reply'` |
| `TYPE_REPLY_ALL` | `'reply_all'` |

| 主な属性 | 説明 |
|---|---|
| `in_reply_to_email_id` | 返信対象メールFK（新規作成時はnull） |
| `reply_type` | compose / reply / reply_all |
| `from_address` | 送信元アドレス（エイリアス対応） |
| `to_address` / `cc` / `bcc` | 宛先 |
| `subject` / `body` | 件名・本文 |
| `attachment_paths` | 添付ファイルパスの配列（JSON） |
| `status` | draft / pending / approved / rejected |
| `created_by` | 作成者名（文字列） |
| `created_by_user_id` | 作成者ユーザーFK |
| `approved_by_user_id` | 承認者ユーザーFK |
| `rejected_by_user_id` | 却下者ユーザーFK |
| `memo` | 却下理由など |

| メソッド | 説明 |
|---|---|
| `reply_type_label` (appended) | 日本語ラベル（返信/全員に返信/新規作成） |
| `body_preview` (appended) | 本文80文字プレビュー |
| `creator()` / `approver()` / `rejecter()` | BelongsTo User |

---

### User
`app/Models/User.php`

| 属性 | 説明 |
|---|---|
| `name` | 表示名 |
| `email` | メールアドレス（unique） |
| `password` | ハッシュ化パスワード |
| `role` | `admin` / `member` |

| メソッド | 説明 |
|---|---|
| `isAdmin(): bool` | `role === 'admin'` |

---

### Customer / CustomerGroup
`app/Models/Customer.php` / `CustomerGroup.php`

| Customer属性 | 説明 |
|---|---|
| `name` | 顧客名 |
| `email` | メールアドレス（unique、自動紐付けに使用） |
| `domain` | ドメイン |
| `notes` | メモ |
| `group_id` | グループFK |
| `sort_order` | 並び順 |

| CustomerGroup属性 | 説明 |
|---|---|
| `name` | グループ名 |
| `parent_id` | 親グループFK（null=ルート） |
| `sort_order` | 並び順 |

---

### 設定系モデル（シングルトンパターン）

| モデル | `getSettings()` | 主な属性 |
|---|---|---|
| `MailSetting` | `firstOrCreate([])` | SMTP/IMAP/POP3の接続設定全般 |
| `AiSetting` | `firstOrCreate([])` | anthropic_api_key（暗号化）、gemini_api_key（暗号化）、default_provider、default_model、agent_name、agent_signature |
| `SsoSetting` | `firstOrCreate([])` | is_enabled、google_client_id/secret/redirect_uri、require_invitation |

---

### その他モデル

| モデル | 主な属性 | 説明 |
|---|---|---|
| `EmailAttachment` | email_id, filename, mime_type, size, disk_path | `isImage(): bool` / `humanSize(): string` |
| `ThreadMerge` | target_thread_id, source_thread_id_original, source_subject, source_tags(JSON), merged_email_ids(JSON) | 仮想マージ記録 |
| `ThreadMemo` | thread_id, user_id, content | 内部メモ |
| `ThreadComment` | thread_id, user_id, content | チームコメント |
| `Tag` | name, color, sort_order | タグマスター |
| `TagNote` | tag(string), content(JSON配列 of {title, body}) | タグ説明ノート |
| `StatusMaster` | name, key, color | ステータスマスター |
| `Document` | original_name, stored_path, mime_type, collection, chunks_indexed, is_indexed | RAGドキュメント |
| `ChatQuery` | question, provider, model, answer, sources(JSON), status, error_message | チャット履歴（UUID PK） |
| `ScrapedUrl` | url, collection, chunks_indexed, status, error_message | スクレイプ済みURL |
| `Invitation` | email, token, role, expires_at, accepted_at, invited_by | `isValid(): bool` |

---

## 5. サービス・ジョブ・通知

### RagApiService
`app/Services/RagApiService.php`

外部Python RAG APIとの通信を担当。ベースURL: `http://rag-api:8000`

| メソッド | 入力 | 出力 | 説明 |
|---|---|---|---|
| `query(string, int, ?string, ?string)` | `$query, $topK, $provider, $model` | `array {answer, sources}` | LLMクエリ実行。claude/geminiの場合はAPIキーをヘッダに付与 |
| `getModels()` | — | `array` | 利用可能モデル一覧 |
| `scrape(string, string)` | `$url, $collection` | `array` | URLスクレイプ |
| `health()` | — | `array` | ヘルスチェック |
| `deleteSource(string, string)` | `$collection, $source` | `array` | ソース削除 |
| `deleteCollection(string)` | `$collection` | `array` | コレクション全削除 |

---

### FetchEmailsJob
`app/Jobs/FetchEmailsJob.php`

| 設定 | 値 |
|---|---|
| インターフェース | ShouldQueue |
| タイムアウト | 120秒 |

`handle(EmailFetcher): void` — IMAP/POP3からメール取得

---

### ProcessChatQuery
`app/Jobs/ProcessChatQuery.php`

| 設定 | 値 |
|---|---|
| インターフェース | ShouldQueue |
| タイムアウト | 180秒 / 試行回数: 1 |

`handle(RagApiService): void` — RAG APIでクエリ処理、ChatQueryモデルを更新

---

### ApprovalRequestedNotification
`app/Notifications/ApprovalRequestedNotification.php`

| メソッド | 出力 | 説明 |
|---|---|---|
| `via()` | `['database']` | DBチャンネルのみ |
| `toArray()` | `{pending_id, subject, to_address, created_by, reply_type}` | 通知データ |

---

### InvitationMail
`app/Mail/InvitationMail.php`

| 属性 | 値 |
|---|---|
| 件名 | 「Riceへの招待が届いています」 |
| ビュー | `emails.invitation` |
| 入力 | `Invitation $invitation` |

---

### EnsureAdmin（ミドルウェア）
`app/Http/Middleware/EnsureAdmin.php`

`handle(Request, Closure): Response` — `user()->isAdmin()` が false の場合 403 abort

---

### AppServiceProvider
`app/Providers/AppServiceProvider.php`

| メソッド | 説明 |
|---|---|
| `register()` | `Modules/` 配下のカスタムオートローダー登録 |
| `boot()` | モジュールのビュー・マイグレーション読み込み、Azure Socialiteプロバイダー登録、DBからSMTP設定を動的に適用 |

---

## 6. Blade ビュー一覧

### レイアウト

| ファイル | 説明 |
|---|---|
| `layouts/app.blade.php` | 認証済みレイアウト。AdminLTE、通知ベル（Alpine.js `notifApp()`）、サイドバー |
| `layouts/guest.blade.php` | ゲスト（ログイン/登録）レイアウト |

### メイン画面

| ファイル | Alpine.jsコンポーネント | 説明 |
|---|---|---|
| `emails/index.blade.php` | `emailApp()` | 受信トレイ。仮想スクロール、スレッド詳細、返信ドラフト（スプリットビュー）、新規作成、AIアシスタント |
| `approvals/index.blade.php` | — | 承認待ちメール一覧・承認/却下操作 |
| `drafts/index.blade.php` | `draftApp()` | 下書き一覧。承認依頼送信・削除 |
| `attachments/index.blade.php` | — | 添付ファイルブラウザ |
| `tags/index.blade.php` | — | タグ別メール一覧 |
| `chat/index.blade.php` | — | RAGチャット画面 |
| `chat/scrape.blade.php` | — | URL スクレイプ管理 |
| `documents/index.blade.php` | — | ドキュメントアップロード管理 |

### 設定画面（管理者専用）

| ファイル | 説明 |
|---|---|
| `settings/mail.blade.php` | SMTP/IMAP/POP3設定。`fetchApp()`で手動同期 |
| `settings/ai.blade.php` | AIプロバイダー・APIキー設定 |
| `settings/sso.blade.php` | Google/Azure SSO設定 |
| `admin/statuses/index.blade.php` | ステータスCRUD（Alpine.js） |
| `admin/tags/index.blade.php` | タグCRUD（Alpine.js） |
| `admin/invitations/index.blade.php` | 招待管理 |

### 認証画面

| ファイル | 説明 |
|---|---|
| `auth/login.blade.php` | ログインフォーム |
| `auth/register.blade.php` | 新規登録フォーム |
| `auth/invitation-accept.blade.php` | 招待リンク経由の登録 |
| `auth/forgot-password.blade.php` | パスワードリセット申請 |
| `auth/reset-password.blade.php` | パスワードリセット |
| `auth/verify-email.blade.php` | メール確認 |
| `auth/confirm-password.blade.php` | パスワード確認 |
| `profile/edit.blade.php` | プロフィール編集 |

---

## 7. Alpine.js コンポーネント

### emailApp() — `emails/index.blade.php`

メインの受信トレイ全体を管理する中核コンポーネント。

**主な状態**

| 状態 | 型 | 説明 |
|---|---|---|
| `threads` | array | スレッド一覧 |
| `selectedThread` | object? | 選択中スレッド |
| `threadEmails` | array | 選択中スレッドのメール |
| `composeMode` | bool | 新規作成モード |
| `replyingToEmailId` | int? | 返信対象メールID |
| `replyBody/replyToAddress/...` | string | 返信フォームフィールド |
| `replyAiPanelOpen` | bool | AIアシスタントパネル表示 |
| `showCloseConfirm` | bool | ドラフト閉じる確認ダイアログ |
| `draftSaving` | bool | 下書き保存中フラグ |
| `leftTab` | string | 現在のステータスタブ |
| `allStatusMode` | bool | 全ステータス表示トグル |
| `selectionMode` | bool | 複数選択モード |
| `selectedThreadIds` | array | 選択中スレッドID |
| `expandedEmailIds` | array | 展開中メールID |
| `virtualScroll` | object | 仮想スクロール制御 |
| `users` | array | ユーザー一覧 |

**主なメソッド**

| メソッド | 説明 |
|---|---|
| `init()` | スレッド・ユーザー読み込み、ポーリング開始 |
| `loadThreads(isBackground?)` | スレッド一覧取得 |
| `loadThread(id)` | スレッド詳細取得 |
| `fetchEmails(isBackground?)` | IMAP同期 |
| `openReplyForEmail(email, all?)` | 返信フォーム初期化 |
| `openCompose()` | 新規作成フォーム初期化 |
| `closeDraftPanel()` | 入力ありの場合は確認ダイアログ表示 |
| `saveDraftAndClose()` | 下書き保存して閉じる |
| `discardDraft()` | 下書き破棄して閉じる |
| `saveDraft()` | サーバーに `save_as_draft=1` でPOST |
| `submitReply()` | 承認依頼として送信 |
| `askAiForReply()` | AI返信生成 |
| `applyAiDraft()` | AI生成結果を本文に反映 |
| `updateThreadStatus(thread, status)` | ステータス更新 |
| `togglePin(threadId?)` | ピン留めトグル |
| `updateAssignee(userId, threadId?)` | 担当者更新 |
| `mergeSelected()` / `executeMerge()` | スレッドマージ |
| `handleScroll()` / `updateVirtualViewport()` | 仮想スクロール制御 |
| `startResize*(e)` | 各パネルのリサイズドラッグ |

---

### notifApp() — `layouts/app.blade.php`

ナビバー通知ベルを管理。60秒ポーリング。

| メソッド | 説明 |
|---|---|
| `poll()` | 初回取得＋インターバル開始 |
| `toggle()` | ドロップダウン開閉 |
| `markRead(id)` | 1件既読 |
| `readAll()` | 全件既読 |

---

## 8. データベーススキーマ（主要テーブル）

```
users
  id, name, email(unique), password, role(admin|member),
  email_verified_at, remember_token, timestamps

email_threads
  id, subject, status(inbox|hold|completed|pending),
  tags(JSON), customer_id(FK→customers), assigned_user_id(FK→users),
  is_pinned(bool), last_email_at, timestamps

emails
  id, thread_id(FK), message_id(unique,index),
  in_reply_to(index), subject,
  from_address, from_name, to_address, cc, bcc,
  body_text, body_html, is_read, received_at, timestamps

email_attachments
  id, email_id(FK), filename, mime_type, size, disk_path, timestamps

pending_emails
  id, in_reply_to_email_id(FK→emails,nullable),
  reply_type(compose|reply|reply_all),
  from_address, to_address, cc, bcc, subject, body,
  attachment_paths(JSON), status(draft|pending|approved|rejected),
  created_by(string), created_by_user_id(FK→users),
  approved_by_user_id(FK→users,nullable),
  rejected_by_user_id(FK→users,nullable),
  approved_at, memo, timestamps

customers
  id, name, email(unique,nullable), domain, notes,
  group_id(FK→customer_groups,nullable), sort_order, timestamps

customer_groups
  id, name, parent_id(FK self,nullable), sort_order, timestamps

thread_merges
  id, target_thread_id(FK→email_threads),
  source_thread_id_original(int,index),
  source_subject, source_tags(JSON), merged_email_ids(JSON), timestamps

thread_memos / thread_comments
  id, thread_id(FK), user_id(FK,nullable), content, timestamps

tags
  id, name(unique), color, sort_order, timestamps

tag_notes
  id, tag(string,unique), content(JSON), timestamps

mail_settings / ai_settings / sso_settings
  id, [各設定値], timestamps  ← getSettings()で常に1レコードのみ使用

status_masters
  id, name, key, color, timestamps

invitations
  id, email, token(unique), role, invited_by(FK→users),
  expires_at, accepted_at(nullable), timestamps

documents
  id, original_name, stored_path, mime_type, collection,
  chunks_indexed, is_indexed, extracted_text, timestamps

chat_queries
  id(UUID), question, provider, model, answer,
  sources(JSON), status, error_message, timestamps

scraped_urls
  id, url, collection, chunks_indexed, status, error_message, timestamps

notifications (Laravel標準)
  id(UUID), type, notifiable_type, notifiable_id,
  data(JSON), read_at(nullable), created_at
```

---

## 9. 設定ファイル

### config/ai_skills.php

AIアシスタントのスキル定義。

```php
'skills' => [
    'reply'        => ['name' => '返信案作成',     'system_prompt' => '...', 'description' => '...'],
    'summarize'    => ['name' => '要約',           'system_prompt' => '...', 'description' => '...'],
    'action_items' => ['name' => 'タスク抽出',     'system_prompt' => '...', 'description' => '...'],
]
```

EmailController::askAi() で `$skills[$skillKey]` として参照。

---

## 10. テスト一覧

### tests/Feature/Phase2Test.php（RefreshDatabase使用）

| テストメソッド | 検証内容 |
|---|---|
| `test_reply_saves_from_address` | 返信ドラフトにカスタムfrom_addressが保存される |
| `test_approve_uses_pending_from_address` | 承認時にカスタムfrom_addressでメール送信される |
| `test_merge_creates_virtual_link` | ThreadMergeが作成され件名が統一される |
| `test_search_hides_merged_source_threads` | 検索結果からマージ元スレッドが除外される |
| `test_thread_view_includes_merged_emails` | マージ先スレッドの詳細にマージ元メールが含まれる |
| `test_toggle_pin` | ピン留めトグルが正しく動作する |

### tests/Feature/ExampleTest.php

| テストメソッド | 検証内容 |
|---|---|
| `test_the_application_returns_a_successful_response` | `/` がログインにリダイレクトされる |

---

## 11. Modules（モジュール構成）

`AppServiceProvider` でオートロードされる拡張モジュール群。

| モジュール | 名前空間 | 主なクラス | 説明 |
|---|---|---|---|
| `MailClient` | `Modules\MailClient` | `EmailFetcher` | IMAP/POP3からのメール取得 |
| `Knowledge` | `Modules\Knowledge` | `KnowledgeController` | ナレッジベース管理・クロール |
| `Workflow` | `Modules\Workflow` | `ReportController` | レポート画面 |
| `AIReply` | `Modules\AIReply` | `AIReplyController` | AI返信生成（`/threads/{thread}/ai-generate`） |

---

## 12. 外部サービス連携

| サービス | 接続先 | 用途 |
|---|---|---|
| RAG API (Python) | `http://rag-api:8000` | LLMクエリ・ドキュメントインデックス・スクレイプ |
| Ollama | `http://ollama:11434` | ローカルLLM（cloudstudio/ollama-laravel経由） |
| Anthropic Claude | `https://api.anthropic.com` | クラウドLLM（APIキーはai_settingsに暗号化保存） |
| Google Gemini | `https://generativelanguage.googleapis.com` | クラウドLLM |
| IMAP/POP3サーバー | 設定依存 | メール受信（webklex/laravel-imap） |
| SMTP サーバー | 設定依存 | メール送信（承認後） |
| Google/Azure OAuth | 設定依存 | SSO認証（laravel/socialite） |
