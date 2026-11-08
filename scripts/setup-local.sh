#!/usr/bin/env bash
set -euo pipefail

PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
WEB_ROOT="${WEB_ROOT:-/var/www/northstar}"
DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3306}"
DB_NAME="${DB_NAME:-web_project_db}"
DB_USER="${DB_USER:-northstar_app}"
TEST_DB_USER="${TEST_DB_USER:-northstar_test}"
BASE_URL="${BASE_URL:-http://localhost}"
ENV_CONF="/etc/apache2/conf-available/northstar-env.conf"

if [[ "$(id -u)" -eq 0 ]]; then
    printf 'Run this script as your normal user. It invokes sudo when needed.\n' >&2
    exit 1
fi

for command in apache2ctl php mysql rsync curl; do
    if ! command -v "$command" >/dev/null 2>&1; then
        printf 'Missing required command: %s\n' "$command" >&2
        exit 1
    fi
done

if ! php -m | grep -qi '^pdo_mysql$'; then
    printf 'The PHP pdo_mysql extension is required.\n' >&2
    exit 1
fi

if ! ls /etc/apache2/mods-enabled/php*.load >/dev/null 2>&1; then
    printf 'Apache is installed, but its PHP module is not enabled.\n' >&2
    exit 1
fi

if [[ ! "$DB_NAME" =~ ^[A-Za-z0-9_]+$ ]]; then
    printf 'DB_NAME may contain only letters, digits, and underscores.\n' >&2
    exit 1
fi

if [[ ! "$DB_USER" =~ ^[A-Za-z0-9_]+$ ]] || [[ ! "$TEST_DB_USER" =~ ^[A-Za-z0-9_]+$ ]]; then
    printf 'Database user names may contain only letters, digits, and underscores.\n' >&2
    exit 1
fi

if [[ ! "$DB_HOST" =~ ^[A-Za-z0-9_.:-]+$ ]]; then
    printf 'DB_HOST contains unsupported characters.\n' >&2
    exit 1
fi

if [[ ! "$DB_PORT" =~ ^[0-9]+$ ]] || ((DB_PORT < 1 || DB_PORT > 65535)); then
    printf 'DB_PORT must be a TCP port between 1 and 65535.\n' >&2
    exit 1
fi

if [[ -n "${SUDO_ASKPASS:-}" ]]; then
    SUDO=(sudo -A)
else
    SUDO=(sudo)
fi
"${SUDO[@]}" -v

if ! systemctl is-active --quiet mysql && ! systemctl is-active --quiet mariadb; then
    printf 'MySQL/MariaDB is not running. Start it with: sudo systemctl start mysql\n' >&2
    exit 1
fi

generate_password() {
    if command -v openssl >/dev/null 2>&1; then
        openssl rand -hex 24
    else
        od -An -N24 -tx1 /dev/urandom | tr -d ' \n'
    fi
}

sql_escape() {
    local value="$1"
    value="${value//\\/\\\\}"
    value="${value//\'/\\\'}"
    printf '%s' "$value"
}

APP_DB_PASSWORD="${DB_PASSWORD:-$(generate_password)}"
TEST_DB_PASSWORD="${TEST_DB_PASSWORD:-$(generate_password)}"
APP_PASSWORD_SQL="$(sql_escape "$APP_DB_PASSWORD")"
TEST_PASSWORD_SQL="$(sql_escape "$TEST_DB_PASSWORD")"

printf 'Importing the Northstar schema...\n'
"${SUDO[@]}" mysql < "$PROJECT_DIR/schema.sql"

printf 'Creating the application and test database accounts...\n'
"${SUDO[@]}" mysql <<SQL
CREATE USER IF NOT EXISTS '${DB_USER}'@'127.0.0.1' IDENTIFIED BY '${APP_PASSWORD_SQL}';
ALTER USER '${DB_USER}'@'127.0.0.1' IDENTIFIED BY '${APP_PASSWORD_SQL}';
GRANT SELECT, INSERT ON ${DB_NAME}.* TO '${DB_USER}'@'127.0.0.1';
CREATE USER IF NOT EXISTS '${TEST_DB_USER}'@'127.0.0.1' IDENTIFIED BY '${TEST_PASSWORD_SQL}';
ALTER USER '${TEST_DB_USER}'@'127.0.0.1' IDENTIFIED BY '${TEST_PASSWORD_SQL}';
GRANT SELECT, INSERT, UPDATE, DELETE ON ${DB_NAME}.* TO '${TEST_DB_USER}'@'127.0.0.1';
FLUSH PRIVILEGES;
SQL

ENV_TMP="$(mktemp)"
trap 'rm -f "$ENV_TMP"' EXIT

cat > "$ENV_TMP" <<EOF
# Northstar database environment for Apache mod_php.
# This file is local deployment state and must not be committed to Git.
SetEnv DB_HOST "${DB_HOST}"
SetEnv DB_PORT "${DB_PORT}"
SetEnv DB_NAME "${DB_NAME}"
SetEnv DB_USER "${DB_USER}"
SetEnv DB_PASSWORD "${APP_DB_PASSWORD}"
EOF

"${SUDO[@]}" install -m 0640 -o root -g www-data "$ENV_TMP" "$ENV_CONF"

umask 077
mkdir -p "$PROJECT_DIR/tests"
cat > "$PROJECT_DIR/tests/.env" <<EOF
DB_HOST=${DB_HOST}
DB_PORT=${DB_PORT}
DB_NAME=${DB_NAME}
DB_USER=${DB_USER}
DB_PASSWORD=${APP_DB_PASSWORD}
TEST_DB_USER=${TEST_DB_USER}
TEST_DB_PASSWORD=${TEST_DB_PASSWORD}
BASE_URL=${BASE_URL}
EOF
chmod 0600 "$PROJECT_DIR/tests/.env"

"${SUDO[@]}" systemctl enable --now apache2 >/dev/null 2>&1

WEB_ROOT="$WEB_ROOT" "$PROJECT_DIR/scripts/deploy.sh"

printf '\nNorthstar is ready.\n'
printf '  URL:          %s/\n' "${BASE_URL%/}"
printf '  Demo login:   demo@example.com / DemoPass123!\n'
printf '  App DB user:  %s (SELECT and INSERT only)\n' "$DB_USER"
printf '  Test DB user: %s (local test suite only)\n' "$TEST_DB_USER"
printf '  Local tests:  %s/tests/.env (gitignored)\n' "$PROJECT_DIR"
printf '  Run tests:    %s/tests/run.sh\n' "$PROJECT_DIR"
printf '\nThe demo account and sample items are for local development only.\n'
