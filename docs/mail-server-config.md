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
