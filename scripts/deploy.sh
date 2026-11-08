#!/usr/bin/env bash
set -euo pipefail

PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
WEB_ROOT="${WEB_ROOT:-/var/www/northstar}"
APACHE_SITE="northstar"
ENV_CONF="/etc/apache2/conf-available/northstar-env.conf"
SITE_CONF="/etc/apache2/sites-available/${APACHE_SITE}.conf"

if [[ ! -f "$ENV_CONF" ]]; then
    printf 'Missing %s.\nRun %s/scripts/setup-local.sh first.\n' "$ENV_CONF" "$PROJECT_DIR" >&2
    exit 1
fi

if [[ "$(id -u)" -eq 0 ]]; then
    SUDO=()
else
    if [[ -n "${SUDO_ASKPASS:-}" ]]; then
        SUDO=(sudo -A)
    else
        SUDO=(sudo)
    fi
    "${SUDO[@]}" -v
fi

"${SUDO[@]}" install -d -o root -g www-data -m 0755 "$WEB_ROOT"
"${SUDO[@]}" rsync -a --delete \
    --exclude='.git' \
    --exclude='scripts' \
    --exclude='tests' \
    "$PROJECT_DIR/" "$WEB_ROOT/"
"${SUDO[@]}" chown -R root:www-data "$WEB_ROOT"
"${SUDO[@]}" find "$WEB_ROOT" -type d -exec chmod 0755 {} +
"${SUDO[@]}" find "$WEB_ROOT" -type f -exec chmod 0644 {} +
"${SUDO[@]}" install -m 0644 "$PROJECT_DIR/apache/northstar.conf" "$SITE_CONF"
"${SUDO[@]}" a2enmod headers >/dev/null
"${SUDO[@]}" a2ensite "${APACHE_SITE}.conf" >/dev/null
"${SUDO[@]}" a2dissite 000-default.conf >/dev/null 2>&1 || true
"${SUDO[@]}" a2enconf northstar-env >/dev/null
"${SUDO[@]}" apache2ctl configtest
"${SUDO[@]}" systemctl reload apache2

printf '\nNorthstar deployed to %s\n' "$WEB_ROOT"
printf 'Open http://localhost/ to use the application.\n'
