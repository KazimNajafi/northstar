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

function connect_for_tests(): PDO
{
    $host = test_env('DB_HOST', '127.0.0.1');
    $port = test_env('DB_PORT', '3306');
    $name = test_env('DB_NAME', 'web_project_db');
    $user = test_env('TEST_DB_USER', test_env('DB_USER'));
    $password = test_env('TEST_DB_PASSWORD', test_env('DB_PASSWORD'));

    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $name);

    return new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}

try {
    $pdo = connect_for_tests();
} catch (Throwable $exception) {
    fwrite(STDERR, 'SKIP database integration tests: ' . $exception->getMessage() . PHP_EOL);
    exit(0);
}

$suffix = bin2hex(random_bytes(6));
$firstUser = [
    'username' => 'Integration One ' . $suffix,
    'email' => 'integration.one.' . $suffix . '@example.test',
    'password' => 'IntegrationOne123!',
];
$secondUser = [
    'username' => 'Integration Two ' . $suffix,
    'email' => 'integration.two.' . $suffix . '@example.test',
    'password' => 'IntegrationTwo123!',
];

$pdo->beginTransaction();

try {
    check(
        $pdo->getAttribute(PDO::ATTR_EMULATE_PREPARES) == false,
        'PDO connection has emulated prepares disabled'
    );

    $insertUser = $pdo->prepare(
        'INSERT INTO users (username, email, password) VALUES (:username, :email, :password)'
    );
    $insertUser->execute([
        'username' => $firstUser['username'],
        'email' => $firstUser['email'],
        'password' => password_hash($firstUser['password'], PASSWORD_DEFAULT),
    ]);
    $firstUserId = (int) $pdo->lastInsertId();

    $storedUser = $pdo->prepare('SELECT username, email, password FROM users WHERE id = :id');
    $storedUser->execute(['id' => $firstUserId]);
    $stored = $storedUser->fetch();

    check($stored !== false, 'A registered user can be read back from MySQL');
    check(
        $stored !== false && $stored['password'] !== $firstUser['password'],
        'The stored password is not the submitted plain-text password'
    );
    check(
        $stored !== false && password_verify($firstUser['password'], $stored['password']),
        'The stored password verifies with password_verify()'
    );
    check(
        $stored !== false && !password_verify('wrong-password', $stored['password']),
        'A wrong password does not verify'
    );

    try {
        $insertUser->execute([
            'username' => 'Duplicate Email ' . $suffix,
            'email' => $firstUser['email'],
            'password' => password_hash('Duplicate123!', PASSWORD_DEFAULT),
        ]);
        check(false, 'A duplicate email is rejected by the unique index');
    } catch (PDOException $exception) {
        check($exception->getCode() === '23000', 'A duplicate email is rejected by the unique index');
    }

    try {
        $insertUser->execute([
            'username' => $firstUser['username'],
            'email' => 'duplicate.username.' . $suffix . '@example.test',
            'password' => password_hash('Duplicate123!', PASSWORD_DEFAULT),
        ]);
        check(false, 'A duplicate username is rejected by the unique index');
    } catch (PDOException $exception) {
        check($exception->getCode() === '23000', 'A duplicate username is rejected by the unique index');
    }

    $insertUser->execute([
        'username' => $secondUser['username'],
        'email' => $secondUser['email'],
        'password' => password_hash($secondUser['password'], PASSWORD_DEFAULT),
    ]);
    $secondUserId = (int) $pdo->lastInsertId();

    $insertItem = $pdo->prepare(
        'INSERT INTO items (title, description, user_id) VALUES (:title, :description, :user_id)'
    );
    $insertItem->execute([
        'title' => 'First account item one',
        'description' => 'Owned by the first integration user.',
        'user_id' => $firstUserId,
    ]);
    $insertItem->execute([
        'title' => 'First account item two',
        'description' => 'Also owned by the first integration user.',
        'user_id' => $firstUserId,
    ]);
    $insertItem->execute([
        'title' => 'Second account item',
        'description' => 'Owned by the second integration user.',
        'user_id' => $secondUserId,
    ]);

    $dashboardQuery = $pdo->prepare(
        'SELECT title, description FROM items WHERE user_id = :user_id ORDER BY id DESC'
    );
    $dashboardQuery->execute(['user_id' => $firstUserId]);
    $firstItems = $dashboardQuery->fetchAll();

    check(count($firstItems) === 2, 'The dashboard query returns only the first user\'s items');
    check(
        $firstItems[0]['title'] === 'First account item two'
        && $firstItems[1]['title'] === 'First account item one',
        'The dashboard query orders items by descending id'
    );

    $dashboardQuery->execute(['user_id' => $secondUserId]);
    $secondItems = $dashboardQuery->fetchAll();

    check(count($secondItems) === 1, 'The second user receives a different item set');
    check(
        $secondItems[0]['title'] === 'Second account item',
        'The second user does not receive the first user\'s items'
    );

    $loginQuery = $pdo->prepare('SELECT id, username, password FROM users WHERE email = :email LIMIT 1');
    $loginQuery->execute(['email' => $firstUser['email']]);
    $loginUser = $loginQuery->fetch();

    check($loginUser !== false, 'A user can be found by email for sign-in');
    check(
        $loginUser !== false && password_verify($firstUser['password'], $loginUser['password']),
        'The sign-in query can verify the stored password'
    );

    $injectionQuery = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
    $injectionQuery->execute(['email' => "' OR '1'='1"]);
    check($injectionQuery->fetch() === false, 'A prepared statement resists a SQL injection payload');

    $deleteUser = $pdo->prepare('DELETE FROM users WHERE id = :id');
    $deleteUser->execute(['id' => $firstUserId]);

    $countItems = $pdo->prepare('SELECT COUNT(*) AS total FROM items WHERE user_id = :user_id');
    $countItems->execute(['user_id' => $firstUserId]);
    $firstItemCount = (int) $countItems->fetch()['total'];

    $countItems->execute(['user_id' => $secondUserId]);
    $secondItemCount = (int) $countItems->fetch()['total'];

    check($firstItemCount === 0, 'Deleting a user cascades to that user\'s items');
    check($secondItemCount === 1, 'Deleting one user leaves another user\'s items intact');

    $countUser = $pdo->prepare('SELECT COUNT(*) AS total FROM users WHERE id = :id');
    $countUser->execute(['id' => $firstUserId]);
    check((int) $countUser->fetch()['total'] === 0, 'The deleted user no longer exists');
} finally {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
}

echo PHP_EOL . $assertions . ' assertions, ' . $failures . ' failure(s)' . PHP_EOL;

exit($failures === 0 ? 0 : 1);
