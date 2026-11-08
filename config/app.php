<?php
declare(strict_types=1);

$isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

ini_set('session.use_strict_mode', '1');
ini_set('session.use_trans_sid', '0');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function post_string(string $key): string
{
    $value = $_POST[$key] ?? '';

    return is_string($value) ? $value : '';
}

function csrf_token(): string
{
    if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_is_valid(): bool
{
    $savedToken = $_SESSION['csrf_token'] ?? null;
    $submittedToken = $_POST['csrf_token'] ?? null;

    return is_string($savedToken)
        && is_string($submittedToken)
        && hash_equals($savedToken, $submittedToken);
}

function sign_in_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['username'] = (string) $user['username'];
    unset($_SESSION['csrf_token']);
}

function database_error_message(Throwable $exception): string
{
    error_log('Northstar database operation failed: ' . $exception->getMessage());

    return 'Northstar cannot access your account right now. Please try again shortly.';
}

function format_price(float|int|string $amount): string
{
    return 'Rs ' . number_format((float) $amount, 0);
}

/**
 * Accept a travel date only when it is a real calendar date within the next
 * two years. Returns the normalised Y-m-d value, or null when it is invalid.
 */
function parse_travel_date(string $value, ?DateTimeImmutable $today = null): ?string
{
    $value = trim($value);
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

    if ($date === false || $date->format('Y-m-d') !== $value) {
        return null;
    }

    $today = $today ?? new DateTimeImmutable('today');
    $latest = $today->modify('+730 days');

    if ($date < $today || $date > $latest) {
        return null;
    }

    return $date->format('Y-m-d');
}

function parse_guest_count(string $value): ?int
{
    $guests = filter_var(trim($value), FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1, 'max_range' => 12],
    ]);

    return $guests === false ? null : $guests;
}

function format_travel_date(string $value): string
{
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

    if ($date === false) {
        return $value;
    }

    return $date->format('j M Y');
}

/**
 * Allow a post-authentication redirect only to the two local pages that need
 * it. This blocks open redirects and absolute URLs.
 */
function safe_next_path(string $value): ?string
{
    if (!preg_match('#^(?:book\.php|home\.php)(?:\?[A-Za-z0-9._~%&=+-]*)?$#', $value)) {
        return null;
    }

    return $value;
}
