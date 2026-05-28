#!/bin/sh

# Fix permissions
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# nginx の fastcgi/proxy/client_body 一時ファイル置き場を www-data に渡す。
# Dockerfile ビルド時にも chown しているが、 base image のバージョン違い・
# ビルドキャッシュ・volume マウント等で実 runtime には root 所有になってしまう
# ケースがあり、 そのとき大きな PHP 出力 (受信トレイの inline CSS 等) で
# nginx が一時ファイル書き込みに失敗し ERR_INCOMPLETE_CHUNKED_ENCODING で
# 接続切断 → ブラウザ真っ白、になる。runtime に強制で chown して再発防止する。
mkdir -p /var/lib/nginx/tmp/client_body \
         /var/lib/nginx/tmp/proxy \
         /var/lib/nginx/tmp/fastcgi \
         /var/lib/nginx/tmp/uwsgi \
         /var/lib/nginx/tmp/scgi
chown -R www-data:www-data /var/lib/nginx

# ----------------------------------------------------------------------
# TLS 証明書の準備
#   - /etc/nginx/certs/{fullchain,privkey}.pem が無い場合 (= 初回起動 or
#     acme.sh 実行前) は self-signed の fallback を生成し、nginx を必ず
#     起動できる状態にする
#   - /etc/nginx/certs/.enabled が存在する場合のみ、HTTP→HTTPS の 301
#     リダイレクトが有効化される (= 本番証明書配置時)
# ----------------------------------------------------------------------
CERT_DIR=/etc/nginx/certs
mkdir -p "$CERT_DIR"

if [ ! -f "$CERT_DIR/fullchain.pem" ] || [ ! -f "$CERT_DIR/privkey.pem" ]; then
    echo "[entrypoint] cert not found, generating self-signed fallback..."
    apk add --no-cache openssl >/dev/null 2>&1 || true
    openssl req -x509 -nodes -newkey rsa:2048 \
        -keyout "$CERT_DIR/privkey.pem" \
        -out "$CERT_DIR/fullchain.pem" \
        -days 365 \
        -subj "/CN=rice.local/O=Rice Self-Signed Fallback" \
        >/dev/null 2>&1
    chmod 600 "$CERT_DIR/privkey.pem"
    # self-signed の間は HTTP→HTTPS の自動リダイレクトを「しない」
    rm -f "$CERT_DIR/.enabled"
else
    echo "[entrypoint] cert present at $CERT_DIR (HTTPS redirect enabled)"
fi

# Execute the original command (supervisord)
exec "$@"
