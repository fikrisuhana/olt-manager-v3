#!/bin/sh
set -e

ENV_FILE="/var/www/html/.env"

# Generate .env dari environment variables Docker jika belum ada
if [ ! -f "$ENV_FILE" ]; then
    echo ">>> Generating .env from environment variables..."
    cat > "$ENV_FILE" << EOF
CI_ENVIRONMENT = ${CI_ENVIRONMENT:-production}

app.baseURL       = '${APP_BASE_URL:-http://localhost/}'
app.indexPage     = ''
app.forceGlobalSecureRequests = false

database.default.hostname = ${DB_HOST:-mysql}
database.default.database = ${DB_NAME:-gpon_manager}
database.default.username = ${DB_USER:-gpon}
database.default.password = ${DB_PASS:-secret}
database.default.DBDriver = MySQLi
database.default.DBPrefix  =
database.default.port      = ${DB_PORT:-3306}
database.default.charset   = utf8mb4
database.default.DBCollat  = utf8mb4_unicode_ci

app.sessionExpiration = ${SESSION_EXPIRATION:-7200}
app.sessionSavePath   = '/var/www/html/writable/session'
app.sessionCookieSecure = false

encryption.key = ${ENCRYPTION_KEY:-$(php -r 'echo bin2hex(random_bytes(32));')}
EOF
    echo ">>> .env created."
else
    echo ">>> .env already exists, skipping generation."
fi

# Pastikan direktori writable tersedia dan dimiliki www-data
mkdir -p /var/www/html/writable/{cache,logs,session,uploads,onu_cache,debugbar}
chown -R www-data:www-data /var/www/html/writable

exec "$@"
