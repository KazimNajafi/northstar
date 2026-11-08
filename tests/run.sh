#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

if [[ -f tests/.env ]]; then
    set -a
    # shellcheck disable=SC1091
    . tests/.env
    set +a
fi

echo '== PHP syntax =='
find . -path ./.git -prune -o -name '*.php' -print0 | xargs -0 -n1 php -l

echo
echo '== JavaScript syntax =='
if command -v node >/dev/null 2>&1; then
    node --check assets/script.js
    echo 'PASS assets/script.js passed node --check'
else
    echo 'SKIP Node.js is not installed; install it with: sudo apt install nodejs'
fi

echo
echo '== Unit checks =='
php tests/unit.php

echo
echo '== Database integration checks =='
php tests/integration.php

echo
echo '== HTTP acceptance checks =='
bash tests/http.sh

echo
echo 'All available checks passed.'
