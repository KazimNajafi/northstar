#!/usr/bin/env bash
set -euo pipefail

TEST_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

load_env_file() {
    local file="$1"
    local line key value

    while IFS= read -r line || [[ -n "$line" ]]; do
        line="${line%$'\r'}"

        if [[ -z "$line" || "$line" == \#* ]]; then
            continue
        fi

        key="${line%%=*}"
        value="${line#*=}"
        key="${key//[[:space:]]/}"

        if [[ -z "$key" ]]; then
            continue
        fi

        if [[ "$value" == \"*\" && "$value" == *\" ]]; then
            value="${value:1:${#value}-2}"
        fi

        if [[ -z "${!key:-}" ]]; then
            export "$key=$value"
        fi
    done < "$file"
}

if [[ -f "$TEST_DIR/.env" ]]; then
    load_env_file "$TEST_DIR/.env"
fi

BASE_URL="${BASE_URL:-http://localhost}"
BASE_URL="${BASE_URL%/}"
DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3306}"
DB_NAME="${DB_NAME:-web_project_db}"
TEST_DB_USER="${TEST_DB_USER:-}"
TEST_DB_PASSWORD="${TEST_DB_PASSWORD:-}"

TMP_DIR="$(mktemp -d "${TMPDIR:-/tmp}/northstar-http.XXXXXX")"
PASS_COUNT=0
FAIL_COUNT=0
SKIP_COUNT=0
DB_AVAILABLE=0

pass() {
    PASS_COUNT=$((PASS_COUNT + 1))
    printf 'PASS %s\n' "$1"
}

fail() {
    FAIL_COUNT=$((FAIL_COUNT + 1))
    printf 'FAIL %s\n' "$1"
}

skip() {
    SKIP_COUNT=$((SKIP_COUNT + 1))
    printf 'SKIP %s\n' "$1"
}

mysql_q() {
    if [[ -z "${TEST_DB_USER:-}" || -z "${TEST_DB_PASSWORD:-}" ]]; then
        return 1
    fi

    MYSQL_PWD="$TEST_DB_PASSWORD" mysql \
        -h "$DB_HOST" \
        -P "$DB_PORT" \
        -u "$TEST_DB_USER" \
        -D "$DB_NAME" \
        -N -B -e "$1" 2>/dev/null
}

cleanup_test_users() {
    if [[ "$DB_AVAILABLE" -eq 1 ]]; then
        mysql_q "DELETE FROM users WHERE email LIKE 'northstar.http-test.%@example.test';" >/dev/null 2>&1 || true
    fi
}

cleanup() {
    rm -rf "$TMP_DIR"
    cleanup_test_users
}

trap cleanup EXIT

assert_status() {
    local expected="$1"
    local actual="$2"
    local label="$3"

    if [[ "$actual" == "$expected" ]]; then
        pass "$label"
    else
        fail "$label (expected HTTP $expected, got $actual)"
    fi
}

assert_contains() {
    local file="$1"
    local needle="$2"
    local label="$3"

    if grep -Fq -- "$needle" "$file"; then
        pass "$label"
    else
        fail "$label (missing: $needle)"
    fi
}

assert_not_contains() {
    local file="$1"
    local needle="$2"
    local label="$3"

    if grep -Fq -- "$needle" "$file"; then
        fail "$label (unexpected: $needle)"
    else
        pass "$label"
    fi
}

assert_location_contains() {
    local headers="$1"
    local needle="$2"
    local label="$3"

    if grep -Eiq "^Location: .*${needle}" "$headers"; then
        pass "$label"
    else
        fail "$label (no Location header containing: $needle)"
    fi
}

assert_header() {
    local headers="$1"
    local pattern="$2"
    local label="$3"

    if grep -Eiq -- "$pattern" "$headers"; then
        pass "$label"
    else
        fail "$label (header not found: $pattern)"
    fi
}

csrf_from() {
    sed -n 's/.*name="csrf_token" value="\([^"]*\)".*/\1/p' "$1" | head -n 1
}

get_page() {
    local url="$1"
    local jar="$2"
    local body="$3"
    local headers="$4"

    curl -sS -c "$jar" -b "$jar" -D "$headers" -o "$body" -w '%{http_code}' "$url"
}

post_form() {
    local url="$1"
    local jar="$2"
    local body="$3"
    local headers="$4"
    shift 4

    curl -sS -X POST -c "$jar" -b "$jar" -D "$headers" -o "$body" -w '%{http_code}' "$url" "$@"
}

if [[ -n "$TEST_DB_USER" && -n "$TEST_DB_PASSWORD" ]] && mysql_q 'SELECT 1' >/dev/null 2>&1; then
    DB_AVAILABLE=1
    cleanup_test_users
else
    printf 'SKIP database-backed checks: set TEST_DB_USER and TEST_DB_PASSWORD in tests/.env.\n'
fi

SUFFIX="$(date +%s)-$$-${RANDOM}"
EMAIL1="northstar.http-test.one.${SUFFIX}@example.test"
USER1="HTTP Test One ${SUFFIX}"
PASS1="HttpTestOne123!"
EMAIL2="northstar.http-test.two.${SUFFIX}@example.test"
USER2="HTTP Test Two ${SUFFIX}"
PASS2="HttpTestTwo123!"

JAR1="$TMP_DIR/jar1"
JAR2="$TMP_DIR/jar2"
JAR3="$TMP_DIR/jar3"
TOKEN=""

echo '== Public pages and access control =='

status="$(get_page "$BASE_URL/" "$JAR1" "$TMP_DIR/root.html" "$TMP_DIR/root.headers")"
assert_status 200 "$status" 'GET / renders the registration page'
assert_contains "$TMP_DIR/root.html" 'Create your account' 'Registration page contains the form heading'
TOKEN="$(csrf_from "$TMP_DIR/root.html")"

if [[ ${#TOKEN} -eq 64 ]]; then
    pass 'Registration page renders a 64-character CSRF token'
else
    fail 'Registration page renders a 64-character CSRF token'
fi

status="$(get_page "$BASE_URL/login.php" "$JAR1" "$TMP_DIR/login.html" "$TMP_DIR/login.headers")"
assert_status 200 "$status" 'GET /login.php renders the sign-in page'
assert_contains "$TMP_DIR/login.html" 'Sign in to Northstar' 'Sign-in page contains the form heading'

assert_header "$TMP_DIR/root.headers" '^X-Content-Type-Options: nosniff' 'Responses set X-Content-Type-Options'
assert_header "$TMP_DIR/root.headers" '^Referrer-Policy: strict-origin-when-cross-origin' 'Responses set Referrer-Policy'
assert_header "$TMP_DIR/root.headers" '^X-Frame-Options: DENY' 'Responses set X-Frame-Options'
assert_header "$TMP_DIR/root.headers" '^Permissions-Policy: .*geolocation=.*microphone=.*camera=' 'Responses set Permissions-Policy'

status="$(curl -sS -D "$TMP_DIR/anon.headers" -o /dev/null -w '%{http_code}' "$BASE_URL/home.php")"
assert_status 302 "$status" 'Anonymous dashboard access redirects'
assert_location_contains "$TMP_DIR/anon.headers" 'login.php' 'Anonymous dashboard redirects to login'

for path in config/db.php schema.sql docs/database-setup.md apache/northstar.conf; do
    status="$(curl -sS -o /dev/null -w '%{http_code}' "$BASE_URL/$path")"
    assert_status 403 "$status" "Direct access to $path is blocked"
done

echo
echo '== Registration and session establishment =='

status="$(post_form "$BASE_URL/signin.php" "$JAR1" "$TMP_DIR/register.html" "$TMP_DIR/register.headers" \
    --data-urlencode "csrf_token=$TOKEN" \
    --data-urlencode "username=$USER1" \
    --data-urlencode "email=$EMAIL1" \
    --data-urlencode "password=$PASS1" \
    --data-urlencode "confirm_password=$PASS1")"
assert_status 302 "$status" 'Valid registration redirects'
assert_location_contains "$TMP_DIR/register.headers" 'home.php' 'Registration redirects to the dashboard'

status="$(get_page "$BASE_URL/home.php" "$JAR1" "$TMP_DIR/dashboard-one.html" "$TMP_DIR/dashboard-one.headers")"
assert_status 200 "$status" 'A registered user can open the dashboard'
assert_contains "$TMP_DIR/dashboard-one.html" 'Good to see you' 'Dashboard greets the signed-in user'
assert_contains "$TMP_DIR/dashboard-one.html" 'Your space is ready' 'A new dashboard shows the empty state'

if [[ "$DB_AVAILABLE" -eq 1 ]]; then
    stored_password="$(mysql_q "SELECT CONCAT(LEFT(password, 4), ':', LENGTH(password)) FROM users WHERE email='$EMAIL1';" || true)"

    case "$stored_password" in
        \$2*:60|\$argon2*:*)
            pass 'The registered password is stored as a hash, not plain text'
            ;;
        *)
            fail "The registered password is stored as a hash, not plain text (got: $stored_password)"
            ;;
    esac

    if mysql_q "INSERT INTO items (title, description, user_id) SELECT 'HTTP-test item one', 'Owned by the first HTTP test user.', id FROM users WHERE email='$EMAIL1';" >/dev/null 2>&1; then
        status="$(get_page "$BASE_URL/home.php" "$JAR1" "$TMP_DIR/dashboard-one-items.html" "$TMP_DIR/dashboard-one-items.headers")"
        assert_status 200 "$status" 'Dashboard reloads after adding an item'
        assert_contains "$TMP_DIR/dashboard-one-items.html" 'HTTP-test item one' 'Dashboard displays an account-owned item'
    else
        fail 'Test item can be inserted for the first HTTP test user'
    fi
else
    skip 'Password hash and item display checks require TEST_DB_USER and TEST_DB_PASSWORD'
    cp "$TMP_DIR/dashboard-one.html" "$TMP_DIR/dashboard-one-items.html"
fi

echo
echo '== Server-side validation and CSRF =='

status="$(get_page "$BASE_URL/signin.php" "$JAR3" "$TMP_DIR/signin-3.html" "$TMP_DIR/signin-3.headers")"
TOKEN="$(csrf_from "$TMP_DIR/signin-3.html")"
status="$(post_form "$BASE_URL/signin.php" "$JAR3" "$TMP_DIR/duplicate-email.html" "$TMP_DIR/duplicate-email.headers" \
    --data-urlencode "csrf_token=$TOKEN" \
    --data-urlencode "username=Duplicate Email User $SUFFIX" \
    --data-urlencode "email=$EMAIL1" \
    --data-urlencode "password=$PASS2" \
    --data-urlencode "confirm_password=$PASS2")"
assert_status 200 "$status" 'Duplicate email registration stays on the form'
assert_contains "$TMP_DIR/duplicate-email.html" 'An account with that email or username already exists.' 'Duplicate email is rejected'

status="$(get_page "$BASE_URL/signin.php" "$JAR3" "$TMP_DIR/signin-3b.html" "$TMP_DIR/signin-3b.headers")"
TOKEN="$(csrf_from "$TMP_DIR/signin-3b.html")"
status="$(post_form "$BASE_URL/signin.php" "$JAR3" "$TMP_DIR/duplicate-user.html" "$TMP_DIR/duplicate-user.headers" \
    --data-urlencode "csrf_token=$TOKEN" \
    --data-urlencode "username=$USER1" \
    --data-urlencode "email=$EMAIL2" \
    --data-urlencode "password=$PASS2" \
    --data-urlencode "confirm_password=$PASS2")"
assert_status 200 "$status" 'Duplicate username registration stays on the form'
assert_contains "$TMP_DIR/duplicate-user.html" 'An account with that email or username already exists.' 'Duplicate username is rejected'

status="$(get_page "$BASE_URL/signin.php" "$JAR3" "$TMP_DIR/signin-3c.html" "$TMP_DIR/signin-3c.headers")"
TOKEN="$(csrf_from "$TMP_DIR/signin-3c.html")"
status="$(post_form "$BASE_URL/signin.php" "$JAR3" "$TMP_DIR/mismatch.html" "$TMP_DIR/mismatch.headers" \
    --data-urlencode "csrf_token=$TOKEN" \
    --data-urlencode "username=Mismatch User $SUFFIX" \
    --data-urlencode "email=mismatch.$SUFFIX@example.test" \
    --data-urlencode "password=$PASS2" \
    --data-urlencode "confirm_password=DifferentPassword123!")"
assert_status 200 "$status" 'Mismatched passwords stay on the form'
assert_contains "$TMP_DIR/mismatch.html" 'Your passwords do not match.' 'Mismatched passwords are rejected'

status="$(get_page "$BASE_URL/signin.php" "$JAR3" "$TMP_DIR/signin-3d.html" "$TMP_DIR/signin-3d.headers")"
TOKEN="$(csrf_from "$TMP_DIR/signin-3d.html")"
status="$(post_form "$BASE_URL/signin.php" "$JAR3" "$TMP_DIR/invalid-email.html" "$TMP_DIR/invalid-email.headers" \
    --data-urlencode "csrf_token=$TOKEN" \
    --data-urlencode "username=Invalid Email User $SUFFIX" \
    --data-urlencode 'email=not-an-email' \
    --data-urlencode "password=$PASS2" \
    --data-urlencode "confirm_password=$PASS2")"
assert_status 200 "$status" 'An invalid email stays on the form'
assert_contains "$TMP_DIR/invalid-email.html" 'Enter a valid email address.' 'An invalid email is rejected'

status="$(post_form "$BASE_URL/signin.php" "$JAR3" "$TMP_DIR/csrf.html" "$TMP_DIR/csrf.headers" \
    --data-urlencode 'csrf_token=invalid-token' \
    --data-urlencode "username=CSRF User $SUFFIX" \
    --data-urlencode "email=csrf.$SUFFIX@example.test" \
    --data-urlencode "password=$PASS2" \
    --data-urlencode "confirm_password=$PASS2")"
assert_status 200 "$status" 'A stale CSRF token stays on the form'
assert_contains "$TMP_DIR/csrf.html" 'Your session expired. Refresh the page and try again.' 'A stale CSRF token is rejected'

echo
echo '== Sign-out and sign-in =='

LOGOUT_TOKEN="$(csrf_from "$TMP_DIR/dashboard-one-items.html")"
status="$(post_form "$BASE_URL/logout.php" "$JAR1" "$TMP_DIR/logout-bad.html" "$TMP_DIR/logout-bad.headers" \
    --data-urlencode 'csrf_token=invalid-token')"
assert_status 400 "$status" 'A sign-out request with an invalid CSRF token is rejected'

status="$(get_page "$BASE_URL/home.php" "$JAR1" "$TMP_DIR/still-signed-in.html" "$TMP_DIR/still-signed-in.headers")"
assert_status 200 "$status" 'An invalid sign-out request does not end the session'

status="$(post_form "$BASE_URL/logout.php" "$JAR1" "$TMP_DIR/logout.html" "$TMP_DIR/logout.headers" \
    --data-urlencode "csrf_token=$LOGOUT_TOKEN")"
assert_status 302 "$status" 'A valid sign-out request redirects'
assert_location_contains "$TMP_DIR/logout.headers" 'login.php' 'Sign-out redirects to login'

status="$(get_page "$BASE_URL/home.php" "$JAR1" "$TMP_DIR/after-logout.html" "$TMP_DIR/after-logout.headers")"
assert_status 302 "$status" 'The dashboard is protected after sign-out'

status="$(get_page "$BASE_URL/login.php" "$JAR1" "$TMP_DIR/login-again.html" "$TMP_DIR/login-again.headers")"
TOKEN="$(csrf_from "$TMP_DIR/login-again.html")"
status="$(post_form "$BASE_URL/login.php" "$JAR1" "$TMP_DIR/wrong-login.html" "$TMP_DIR/wrong-login.headers" \
    --data-urlencode "csrf_token=$TOKEN" \
    --data-urlencode "email=$EMAIL1" \
    --data-urlencode 'password=WrongPassword123!')"
assert_status 200 "$status" 'A wrong password stays on the sign-in form'
assert_contains "$TMP_DIR/wrong-login.html" 'That email and password combination was not recognized.' 'A wrong password is rejected'

TOKEN="$(csrf_from "$TMP_DIR/wrong-login.html")"
status="$(post_form "$BASE_URL/login.php" "$JAR1" "$TMP_DIR/correct-login.html" "$TMP_DIR/correct-login.headers" \
    --data-urlencode "csrf_token=$TOKEN" \
    --data-urlencode "email=$EMAIL1" \
    --data-urlencode "password=$PASS1")"
assert_status 302 "$status" 'A correct password redirects'
assert_location_contains "$TMP_DIR/correct-login.headers" 'home.php' 'A correct sign-in redirects to the dashboard'

status="$(get_page "$BASE_URL/home.php" "$JAR1" "$TMP_DIR/dashboard-after-login.html" "$TMP_DIR/dashboard-after-login.headers")"
assert_status 200 "$status" 'A signed-in user can reopen the dashboard'
assert_contains "$TMP_DIR/dashboard-after-login.html" 'Good to see you' 'The reopened dashboard greets the user'

echo
echo '== Multi-user isolation =='

status="$(get_page "$BASE_URL/signin.php" "$JAR2" "$TMP_DIR/signin-2.html" "$TMP_DIR/signin-2.headers")"
TOKEN="$(csrf_from "$TMP_DIR/signin-2.html")"
status="$(post_form "$BASE_URL/signin.php" "$JAR2" "$TMP_DIR/register-2.html" "$TMP_DIR/register-2.headers" \
    --data-urlencode "csrf_token=$TOKEN" \
    --data-urlencode "username=$USER2" \
    --data-urlencode "email=$EMAIL2" \
    --data-urlencode "password=$PASS2" \
    --data-urlencode "confirm_password=$PASS2")"
assert_status 302 "$status" 'A second account can register'

if [[ "$DB_AVAILABLE" -eq 1 ]]; then
    if mysql_q "INSERT INTO items (title, description, user_id) SELECT 'HTTP-test item two', 'Owned by the second HTTP test user.', id FROM users WHERE email='$EMAIL2';" >/dev/null 2>&1; then
        status="$(get_page "$BASE_URL/home.php" "$JAR2" "$TMP_DIR/dashboard-two.html" "$TMP_DIR/dashboard-two.headers")"
        assert_status 200 "$status" 'The second user can open the dashboard'
        assert_contains "$TMP_DIR/dashboard-two.html" 'HTTP-test item two' 'The second user sees their own item'
        assert_not_contains "$TMP_DIR/dashboard-two.html" 'HTTP-test item one' "The second user does not see the first user's item"

        status="$(get_page "$BASE_URL/home.php" "$JAR1" "$TMP_DIR/dashboard-one-again.html" "$TMP_DIR/dashboard-one-again.headers")"
        assert_status 200 "$status" 'The first user can reopen the dashboard'
        assert_contains "$TMP_DIR/dashboard-one-again.html" 'HTTP-test item one' 'The first user still sees their own item'
        assert_not_contains "$TMP_DIR/dashboard-one-again.html" 'HTTP-test item two' "The first user does not see the second user's item"
    else
        fail 'Test item can be inserted for the second HTTP test user'
    fi
else
    skip 'Multi-user item isolation requires TEST_DB_USER and TEST_DB_PASSWORD'
fi

echo
printf '%d passed, %d failed, %d skipped\n' "$PASS_COUNT" "$FAIL_COUNT" "$SKIP_COUNT"

if [[ "$FAIL_COUNT" -gt 0 ]]; then
    exit 1
fi
