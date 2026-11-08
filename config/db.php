<?php
declare(strict_types=1);

$requiredVariables = ['DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASSWORD'];
$databaseConfig = [];
$missingVariables = [];

foreach ($requiredVariables as $variable) {
    $value = getenv($variable);

    if ($value === false) {
        $missingVariables[] = $variable;
        continue;
    }

    $databaseConfig[$variable] = $value;
}

if ($missingVariables !== []) {
    throw new RuntimeException(
        'Database configuration is incomplete. Set these environment variables: '
        . implode(', ', $missingVariables)
        . '.'
    );
}

$port = filter_var($databaseConfig['DB_PORT'], FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1, 'max_range' => 65535],
]);

if ($port === false) {
    throw new RuntimeException('DB_PORT must be a valid TCP port number.');
}

$dsn = sprintf(
    'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
    $databaseConfig['DB_HOST'],
    $port,
    $databaseConfig['DB_NAME']
);

try {
    return new PDO($dsn, $databaseConfig['DB_USER'], $databaseConfig['DB_PASSWORD'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $exception) {
    error_log('Database connection failed: ' . $exception->getMessage());
    throw new RuntimeException(
        'Could not connect to the database. Check the database environment variables and confirm that MySQL is running.',
        0,
        $exception
    );
}
