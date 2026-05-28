<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .code-box { display: inline-block; padding: 16px 32px; background-color: #f3f4f6; border-radius: 8px; font-size: 32px; font-weight: bold; letter-spacing: 8px; color: #2563eb; margin: 16px 0; }
        .footer { margin-top: 40px; font-size: 12px; color: #999; }
        .notice { padding: 12px 16px; background-color: #fef3c7; border-left: 4px solid #f59e0b; margin-top: 24px; font-size: 13px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>ログイン認証コード</h1>
        <p>{{ $user->resolvedDisplayName() }} 様</p>
        <p>Riceへのログインを試みています。以下の認証コードを画面に入力してください。</p>
        <p style="text-align: center;">
            <span class="code-box">{{ $code }}</span>
        </p>
        <p>このコードの有効期限は <strong>{{ $lifetimeMinutes }} 分</strong> です。</p>
        <div class="notice">
            <strong>※心当たりがない場合</strong><br>
            このメールに心当たりがない場合は、何も操作せずこのメールを破棄してください。アカウントは安全です。必要に応じてパスワードの変更をご検討ください。
        </div>
        <div class="footer">
            <p>このメールは Rice (メール管理アプリ) から自動送信されています。</p>
        </div>
    </div>
</body>
</html>
