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
