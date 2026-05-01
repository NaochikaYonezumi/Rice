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
