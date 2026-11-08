<?php
declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'bootstrap.php';

$assertions = 0;
$failures = 0;

function check(bool $condition, string $label): void
{
    global $assertions, $failures;

    $assertions++;

    if ($condition) {
        echo 'PASS ' . $label . PHP_EOL;

        return;
    }

    $failures++;
    echo 'FAIL ' . $label . PHP_EOL;
}

ini_set('session.use_cookies', '0');
ini_set('session.cache_limiter', '');
ini_set('session.use_strict_mode', '0');
ini_set('session.use_trans_sid', '1');
ini_set('error_log', '/dev/null');
session_id('northstar-unit-' . bin2hex(random_bytes(8)));

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'app.php';

$_SESSION = [];

check(ini_get('session.use_strict_mode') === '1', 'Strict session mode is enabled');
check(ini_get('session.use_trans_sid') === '0', 'Session IDs are not accepted from URLs');

check(
    e('<script>alert("x")</script>') === '&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt;',
    'e() escapes HTML special characters'
);

check(post_string('missing') === '', 'post_string() returns an empty string for a missing key');

$_POST['array_value'] = ['not', 'a', 'string'];
check(post_string('array_value') === '', 'post_string() rejects non-string input');
unset($_POST['array_value']);

$token = csrf_token();
check(strlen($token) === 64 && ctype_xdigit($token), 'csrf_token() returns 64 hexadecimal characters');
check(csrf_token() === $token, 'csrf_token() is stable within a session');

$_POST['csrf_token'] = $token;
check(csrf_is_valid(), 'csrf_is_valid() accepts the session token');

$_POST['csrf_token'] = str_repeat('0', 64);
check(!csrf_is_valid(), 'csrf_is_valid() rejects a different token');

unset($_POST['csrf_token']);
check(!csrf_is_valid(), 'csrf_is_valid() rejects a missing token');

$hash = password_hash('CorrectHorse123!', PASSWORD_DEFAULT);
check($hash !== 'CorrectHorse123!', 'password_hash() does not store the plain-text password');
check(password_verify('CorrectHorse123!', $hash), 'password_verify() accepts the correct password');
check(!password_verify('wrong-password', $hash), 'password_verify() rejects an incorrect password');

$message = database_error_message(new RuntimeException('secret-dsn-password'));
check(!str_contains($message, 'secret-dsn-password'), 'database_error_message() hides diagnostic details');
check(str_contains($message, 'try again'), 'database_error_message() gives the user a retry message');

sign_in_user(['id' => 42, 'username' => 'Kazim']);
check(
    ($_SESSION['user_id'] ?? null) === 42 && ($_SESSION['username'] ?? null) === 'Kazim',
    'sign_in_user() stores the authenticated identity'
);
check(!isset($_SESSION['csrf_token']), 'sign_in_user() rotates the CSRF token');

check(format_price('85000') === 'Rs 85,000', 'format_price() renders a whole-rupee price');
check(format_price(170000.5) === 'Rs 170,001', 'format_price() rounds to whole rupees');

$today = new DateTimeImmutable('2026-11-08');
check(parse_travel_date('2026-11-08', $today) === '2026-11-08', 'A travel date of today is accepted');
check(parse_travel_date('2026-11-09', $today) === '2026-11-09', 'A future travel date is accepted');
check(parse_travel_date('2026-11-07', $today) === null, 'A past travel date is rejected');
check(parse_travel_date('2028-11-07', $today) === '2028-11-07', 'A date inside the two-year window is accepted');
check(parse_travel_date('2028-11-08', $today) === null, 'A date beyond the two-year window is rejected');
check(parse_travel_date('2026-02-30', $today) === null, 'An impossible calendar date is rejected');
check(parse_travel_date('not-a-date', $today) === null, 'A malformed travel date is rejected');
check(parse_travel_date('', $today) === null, 'An empty travel date is rejected');

check(parse_guest_count('1') === 1, 'One guest is accepted');
check(parse_guest_count('12') === 12, 'Twelve guests is accepted');
check(parse_guest_count('0') === null, 'Zero guests is rejected');
check(parse_guest_count('13') === null, 'Thirteen guests is rejected');
check(parse_guest_count('2.5') === null, 'A fractional guest count is rejected');
check(parse_guest_count('many') === null, 'A non-numeric guest count is rejected');

check(format_travel_date('2026-12-18') === '18 Dec 2026', 'format_travel_date() renders a readable date');
check(format_travel_date('broken') === 'broken', 'format_travel_date() leaves an unknown value unchanged');

check(
    safe_next_path('book.php?tour=4') === 'book.php?tour=4',
    'safe_next_path() allows the booking page with a tour id'
);
check(safe_next_path('home.php') === 'home.php', 'safe_next_path() allows the dashboard');
check(safe_next_path('https://example.com') === null, 'safe_next_path() rejects absolute URLs');
check(safe_next_path('//example.com') === null, 'safe_next_path() rejects protocol-relative URLs');
check(safe_next_path('admin.php') === null, 'safe_next_path() rejects unknown pages');
check(safe_next_path('book.php?tour=1&next=https://example.com') === null, 'safe_next_path() rejects a nested URL');

echo PHP_EOL . $assertions . ' assertions, ' . $failures . ' failure(s)' . PHP_EOL;

exit($failures === 0 ? 0 : 1);
