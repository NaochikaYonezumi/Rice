#!/usr/bin/env bash
# =============================================================================
# issue-cert.sh   (WSL ubuntu-20.04 で実行)
#   - Let's Encrypt の証明書を AWS Route 53 の DNS-01 チャレンジで発行する。
#   - 取得した cert を ./certs/{fullchain,privkey}.pem に配置し、
#     ./certs/.enabled をタッチして nginx の HTTP→HTTPS リダイレクトを有効化。
#   - docker compose の laravel コンテナを再起動して反映。
#
#   前提:
#     - acme.sh が ~/.acme.sh/acme.sh にインストール済 (なければこの script が install)
#     - AWS_ACCESS_KEY_ID, AWS_SECRET_ACCESS_KEY を環境変数で渡す
#         例: AWS_ACCESS_KEY_ID=AKIA... AWS_SECRET_ACCESS_KEY=... bash scripts/issue-cert.sh
#     - AWS IAM ユーザーには route53:ChangeResourceRecordSets と
#       route53:ListResourceRecordSets 権限を Hosted Zone /hostedzone/<ZONE_ID> に付与
# =============================================================================

set -euo pipefail

DOMAIN="rice.cosy.co.jp"
EMAIL="naozumiyonechika@gmail.com"
REPO_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
CERT_DIR="$REPO_ROOT/certs"

# --- 1. AWS credential check -----------------------------------------------
if [ -z "${AWS_ACCESS_KEY_ID:-}" ] || [ -z "${AWS_SECRET_ACCESS_KEY:-}" ]; then
    cat >&2 <<EOF
ERROR: AWS credentials are required.
  export AWS_ACCESS_KEY_ID=AKIA...
  export AWS_SECRET_ACCESS_KEY=...
  bash $0
EOF
    exit 1
fi

# --- 2. acme.sh install (if missing) ---------------------------------------
if [ ! -f "$HOME/.acme.sh/acme.sh" ]; then
    echo "[issue-cert] installing acme.sh..."
    curl https://get.acme.sh | sh -s email="$EMAIL"
fi

ACME="$HOME/.acme.sh/acme.sh"
"$ACME" --set-default-ca --server letsencrypt

# --- 3. issue (DNS-01 via Route 53) ----------------------------------------
mkdir -p "$CERT_DIR"
echo "[issue-cert] requesting cert for $DOMAIN via Route 53..."

# --force を付けて、既に証明書があっても更新可能にする
"$ACME" --issue --dns dns_aws \
    -d "$DOMAIN" \
    --force \
    --reloadcmd "true"

# --- 4. install to ./certs/ ------------------------------------------------
echo "[issue-cert] installing cert to $CERT_DIR ..."
"$ACME" --install-cert -d "$DOMAIN" \
    --key-file       "$CERT_DIR/privkey.pem" \
    --fullchain-file "$CERT_DIR/fullchain.pem" \
    --reloadcmd      "touch $CERT_DIR/.enabled && docker exec rice-laravel-1 nginx -s reload 2>/dev/null || docker compose -f $REPO_ROOT/docker-compose.yml restart laravel"

# entrypoint.sh が cert を見つけて HTTP→HTTPS 301 を有効化する
touch "$CERT_DIR/.enabled"

echo ''
echo '[issue-cert] DONE.'
echo "  fullchain: $CERT_DIR/fullchain.pem"
echo "  privkey:   $CERT_DIR/privkey.pem"
echo ''
echo '  acme.sh は ~/.acme.sh に cron を仕込んだので、'
echo '  60日サイクルで自動更新されます。手動更新は:'
echo "    ~/.acme.sh/acme.sh --renew -d $DOMAIN --force"
