<?php
declare(strict_types=1);

/**
 * Load tests/.env when it exists, without overriding variables supplied by the
 * shell. The file is created by scripts/setup-local.sh and is ignored by Git.
 */
function load_test_env(): void
{
    $envFile = __DIR__ . DIRECTORY_SEPARATOR . '.env';

    if (!is_readable($envFile)) {
        return;
    }

    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);

        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        $separator = strpos($line, '=');

        if ($separator === false) {
            continue;
        }

        $key = trim(substr($line, 0, $separator));
        $value = trim(substr($line, $separator + 1));

        if ($key === '') {
            continue;
        }

        if (strlen($value) >= 2) {
            $first = $value[0];
            $last = $value[strlen($value) - 1];

            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                $value = substr($value, 1, -1);
            }
        }

        if (getenv($key) === false) {
            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
        }
    }
}

function test_env(string $key, ?string $default = null): string
{
    $value = getenv($key);

    if ($value === false || $value === '') {
        if ($default !== null) {
            return $default;
        }

        throw new RuntimeException('Missing test environment variable: ' . $key);
    }

    return $value;
}

load_test_env();
