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
FUTURE_DATE="$(date -u -d '+30 days' +%Y-%m-%d)"
PAST_DATE="$(date -u -d '-30 days' +%Y-%m-%d)"

TOUR1_ID=1
TOUR2_ID=2
TOUR1_NAME="Hunza Valley Escape"
TOUR2_NAME="Skardu and Shangrila"

if [[ "$DB_AVAILABLE" -eq 1 ]]; then
    TOUR1_ID="$(mysql_q "SELECT id FROM tours ORDER BY id LIMIT 1;" || true)"
    TOUR2_ID="$(mysql_q "SELECT id FROM tours ORDER BY id LIMIT 1 OFFSET 1;" || true)"
    TOUR1_NAME="$(mysql_q "SELECT name FROM tours ORDER BY id LIMIT 1;" || true)"
    TOUR2_NAME="$(mysql_q "SELECT name FROM tours ORDER BY id LIMIT 1 OFFSET 1;" || true)"
    TOUR1_ID="${TOUR1_ID:-1}"
    TOUR2_ID="${TOUR2_ID:-2}"
fi

JAR1="$TMP_DIR/jar1"
JAR2="$TMP_DIR/jar2"
JAR3="$TMP_DIR/jar3"
TOKEN=""

echo '== Public pages and access control =='

status="$(get_page "$BASE_URL/" "$JAR1" "$TMP_DIR/root.html" "$TMP_DIR/root.headers")"
assert_status 200 "$status" 'GET / renders the tour catalogue'
assert_contains "$TMP_DIR/root.html" 'Choose your next journey' 'The catalogue lists the available journeys'

if [[ "$DB_AVAILABLE" -eq 1 ]]; then
    assert_contains "$TMP_DIR/root.html" "$TOUR1_NAME" 'The catalogue shows a seeded tour'
    assert_contains "$TMP_DIR/root.html" 'Reserve this tour' 'A tour card offers a reservation link'
else
    skip 'Seeded tour rendering requires TEST_DB_USER and TEST_DB_PASSWORD'
fi

status="$(get_page "$BASE_URL/signin.php" "$JAR1" "$TMP_DIR/signin.html" "$TMP_DIR/signin.headers")"
assert_status 200 "$status" 'GET /signin.php renders the registration page'
assert_contains "$TMP_DIR/signin.html" 'Create your account' 'Registration page contains the form heading'
TOKEN="$(csrf_from "$TMP_DIR/signin.html")"

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

status="$(curl -sS -D "$TMP_DIR/anon-book.headers" -o /dev/null -w '%{http_code}' "$BASE_URL/book.php?tour=$TOUR1_ID")"
assert_status 302 "$status" 'Anonymous booking access redirects'
assert_location_contains "$TMP_DIR/anon-book.headers" 'login.php' 'Anonymous booking redirects to login'
assert_location_contains "$TMP_DIR/anon-book.headers" 'next=book.php' 'The booking redirect preserves the chosen tour'

for path in config/db.php schema.sql docs/database-setup.md apache/northstar.conf; do
    status="$(curl -sS -o /dev/null -w '%{http_code}' "$BASE_URL/$path")"
    assert_status 403 "$status" "Direct access to $path is blocked"
done

status="$(curl -sS -o /dev/null -w '%{http_code}' "$BASE_URL/tests/run.sh")"
assert_status 404 "$status" 'The test suite is not deployed to the web root'

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
assert_contains "$TMP_DIR/dashboard-one.html" 'No trips booked yet' 'A new account shows the empty bookings state'

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

    status="$(get_page "$BASE_URL/book.php?tour=$TOUR1_ID" "$JAR1" "$TMP_DIR/book.html" "$TMP_DIR/book.headers")"
    assert_status 200 "$status" 'A signed-in user can open the booking form'
    assert_contains "$TMP_DIR/book.html" 'Reserve your place' 'The booking form asks for travel details'
    assert_contains "$TMP_DIR/book.html" "$TOUR1_NAME" 'The booking form names the chosen tour'

    BOOK_TOKEN="$(csrf_from "$TMP_DIR/book.html")"
    status="$(post_form "$BASE_URL/book.php" "$JAR1" "$TMP_DIR/booked.html" "$TMP_DIR/booked.headers" \
        --data-urlencode "csrf_token=$BOOK_TOKEN" \
        --data-urlencode "tour_id=$TOUR1_ID" \
        --data-urlencode "travel_date=$FUTURE_DATE" \
        --data-urlencode 'guests=2' \
        --data-urlencode 'total_price=1')"
    assert_status 302 "$status" 'A valid booking redirects'
    assert_location_contains "$TMP_DIR/booked.headers" 'home.php[?]booked=1' 'A valid booking confirms on the dashboard'

    status="$(get_page "$BASE_URL/home.php?booked=1" "$JAR1" "$TMP_DIR/dashboard-booked.html" "$TMP_DIR/dashboard-booked.headers")"
    assert_status 200 "$status" 'The dashboard reloads after booking'
    assert_contains "$TMP_DIR/dashboard-booked.html" 'Your booking is confirmed' 'The dashboard confirms the booking'
    assert_contains "$TMP_DIR/dashboard-booked.html" "$TOUR1_NAME" 'The booking appears on the dashboard'
    assert_contains "$TMP_DIR/dashboard-booked.html" '1 booking' 'The dashboard counts the booking'

    stored_total="$(mysql_q "SELECT COUNT(*) FROM bookings b JOIN tours t ON t.id = b.tour_id JOIN users u ON u.id = b.user_id WHERE u.email='$EMAIL1' AND b.total_price = t.price_per_person * 2;" || true)"
    if [[ "$stored_total" == "1" ]]; then
        pass 'The booking total is calculated from the tour price, not the submitted value'
    else
        fail "The booking total is calculated from the tour price, not the submitted value (got: $stored_total)"
    fi
else
    skip 'Password hash and booking checks require TEST_DB_USER and TEST_DB_PASSWORD'
    cp "$TMP_DIR/dashboard-one.html" "$TMP_DIR/dashboard-booked.html"
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

if [[ "$DB_AVAILABLE" -eq 1 ]]; then
    status="$(get_page "$BASE_URL/book.php?tour=$TOUR1_ID" "$JAR1" "$TMP_DIR/book-invalid.html" "$TMP_DIR/book-invalid.headers")"
    INVALID_TOKEN="$(csrf_from "$TMP_DIR/book-invalid.html")"

    status="$(post_form "$BASE_URL/book.php" "$JAR1" "$TMP_DIR/past-date.html" "$TMP_DIR/past-date.headers" \
        --data-urlencode "csrf_token=$INVALID_TOKEN" \
        --data-urlencode "tour_id=$TOUR1_ID" \
        --data-urlencode "travel_date=$PAST_DATE" \
        --data-urlencode 'guests=2')"
    assert_status 200 "$status" 'A past travel date stays on the booking form'
    assert_contains "$TMP_DIR/past-date.html" 'Choose a travel date' 'A past travel date is rejected'

    status="$(get_page "$BASE_URL/book.php?tour=$TOUR1_ID" "$JAR1" "$TMP_DIR/book-invalid-2.html" "$TMP_DIR/book-invalid-2.headers")"
    INVALID_TOKEN="$(csrf_from "$TMP_DIR/book-invalid-2.html")"

    status="$(post_form "$BASE_URL/book.php" "$JAR1" "$TMP_DIR/too-many.html" "$TMP_DIR/too-many.headers" \
        --data-urlencode "csrf_token=$INVALID_TOKEN" \
        --data-urlencode "tour_id=$TOUR1_ID" \
        --data-urlencode "travel_date=$FUTURE_DATE" \
        --data-urlencode 'guests=13')"
    assert_status 200 "$status" 'An oversized group stays on the booking form'
    assert_contains "$TMP_DIR/too-many.html" 'Choose between 1 and 12 guests' 'An oversized group is rejected'

    status="$(post_form "$BASE_URL/book.php" "$JAR1" "$TMP_DIR/book-csrf.html" "$TMP_DIR/book-csrf.headers" \
        --data-urlencode 'csrf_token=invalid-token' \
        --data-urlencode "tour_id=$TOUR1_ID" \
        --data-urlencode "travel_date=$FUTURE_DATE" \
        --data-urlencode 'guests=2')"
    assert_status 200 "$status" 'A stale CSRF token stays on the booking form'
    assert_contains "$TMP_DIR/book-csrf.html" 'Your session expired. Refresh the page and try again.' 'A stale booking token is rejected'

    status="$(get_page "$BASE_URL/book.php?tour=999999999" "$JAR1" "$TMP_DIR/missing-tour.html" "$TMP_DIR/missing-tour.headers")"
    assert_status 404 "$status" 'An unknown tour returns 404'
    assert_contains "$TMP_DIR/missing-tour.html" 'We could not find that tour' 'An unknown tour is handled gracefully'
else
    skip 'Booking validation checks require TEST_DB_USER and TEST_DB_PASSWORD'
fi

echo
echo '== Sign-out and sign-in =='

LOGOUT_TOKEN="$(csrf_from "$TMP_DIR/dashboard-booked.html")"
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
    status="$(get_page "$BASE_URL/book.php?tour=$TOUR2_ID" "$JAR2" "$TMP_DIR/book-two.html" "$TMP_DIR/book-two.headers")"
    BOOK_TOKEN="$(csrf_from "$TMP_DIR/book-two.html")"
    status="$(post_form "$BASE_URL/book.php" "$JAR2" "$TMP_DIR/booked-two.html" "$TMP_DIR/booked-two.headers" \
        --data-urlencode "csrf_token=$BOOK_TOKEN" \
        --data-urlencode "tour_id=$TOUR2_ID" \
        --data-urlencode "travel_date=$FUTURE_DATE" \
        --data-urlencode 'guests=1')"
    assert_status 302 "$status" 'The second user can book a tour'

    status="$(get_page "$BASE_URL/home.php" "$JAR2" "$TMP_DIR/dashboard-two.html" "$TMP_DIR/dashboard-two.headers")"
    assert_status 200 "$status" 'The second user can open the dashboard'
    assert_contains "$TMP_DIR/dashboard-two.html" "data-booking-tour=\"$TOUR2_ID\"" 'The second user sees their own booking'
    assert_not_contains "$TMP_DIR/dashboard-two.html" "data-booking-tour=\"$TOUR1_ID\"" "The second user does not see the first user's booking"

    status="$(get_page "$BASE_URL/home.php" "$JAR1" "$TMP_DIR/dashboard-one-again.html" "$TMP_DIR/dashboard-one-again.headers")"
    assert_status 200 "$status" 'The first user can reopen the dashboard'
    assert_contains "$TMP_DIR/dashboard-one-again.html" "data-booking-tour=\"$TOUR1_ID\"" 'The first user still sees their own booking'
    assert_not_contains "$TMP_DIR/dashboard-one-again.html" "data-booking-tour=\"$TOUR2_ID\"" "The first user does not see the second user's booking"
else
    skip 'Multi-user booking isolation requires TEST_DB_USER and TEST_DB_PASSWORD'
fi

echo
printf '%d passed, %d failed, %d skipped\n' "$PASS_COUNT" "$FAIL_COUNT" "$SKIP_COUNT"

if [[ "$FAIL_COUNT" -gt 0 ]]; then
    exit 1
fi
