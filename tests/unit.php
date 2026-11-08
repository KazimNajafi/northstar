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
ini_set('session.use_strict_mode', '1');
ini_set('error_log', '/dev/null');
session_id('northstar-unit-' . bin2hex(random_bytes(8)));

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'app.php';

$_SESSION = [];

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

echo PHP_EOL . $assertions . ' assertions, ' . $failures . ' failure(s)' . PHP_EOL;

exit($failures === 0 ? 0 : 1);
