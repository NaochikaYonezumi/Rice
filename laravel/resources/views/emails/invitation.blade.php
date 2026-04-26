<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .button { display: inline-block; padding: 12px 24px; background-color: #2563eb; color: #fff; text-decoration: none; border-radius: 8px; font-weight: bold; }
        .footer { margin-top: 40px; font-size: 12px; color: #999; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Riceへの招待が届いています</h1>
        <p>こんにちは、AdministratorからRice（メール管理アプリ）への招待が届きました。</p>
        <p>以下のボタンをクリックして、アカウント設定を完了させてください。</p>
        <p style="margin: 30px 0;">
            <a href="{{ route('invitations.accept', $invitation->token) }}" class="button">招待を承認してアカウントを作成</a>
        </p>
        <p>招待リンクの有効期限は {{ $invitation->expires_at->format('Y年m月d日 H:i') }} までです。</p>
        <div class="footer">
            <p>※このメールに心当たりがない場合は、破棄してください。</p>
        </div>
    </div>
</body>
</html>
