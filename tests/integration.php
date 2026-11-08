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

    $tourQuery = $pdo->query('SELECT id, name, price_per_person FROM tours ORDER BY id');
    $tours = $tourQuery->fetchAll();

    check(count($tours) >= 6, 'The seeded tour catalogue contains at least six tours');
    check(
        $tours !== [] && (float) $tours[0]['price_per_person'] > 0,
        'Seeded tours have a positive price per person'
    );

    $firstTour = $tours[0];
    $secondTour = $tours[1];

    try {
        $insertTour = $pdo->prepare(
            'INSERT INTO tours (name, destination, summary, description, duration_days, price_per_person)
             VALUES (:name, :destination, :summary, :description, :duration_days, :price_per_person)'
        );
        $insertTour->execute([
            'name' => $firstTour['name'],
            'destination' => 'Duplicate destination',
            'summary' => 'Duplicate tour summary.',
            'description' => 'Duplicate tour description.',
            'duration_days' => 3,
            'price_per_person' => 1000,
        ]);
        check(false, 'A duplicate tour name is rejected by the unique index');
    } catch (PDOException $exception) {
        check($exception->getCode() === '23000', 'A duplicate tour name is rejected by the unique index');
    }

    $insertBooking = $pdo->prepare(
        'INSERT INTO bookings (user_id, tour_id, travel_date, guests, total_price, status)
         VALUES (:user_id, :tour_id, :travel_date, :guests, :total_price, :status)'
    );

    $travelDate = (new DateTimeImmutable('+30 days'))->format('Y-m-d');
    $laterTravelDate = (new DateTimeImmutable('+45 days'))->format('Y-m-d');

    $insertBooking->execute([
        'user_id' => $firstUserId,
        'tour_id' => (int) $firstTour['id'],
        'travel_date' => $travelDate,
        'guests' => 2,
        'total_price' => (float) $firstTour['price_per_person'] * 2,
        'status' => 'confirmed',
    ]);
    $insertBooking->execute([
        'user_id' => $firstUserId,
        'tour_id' => (int) $secondTour['id'],
        'travel_date' => $laterTravelDate,
        'guests' => 3,
        'total_price' => (float) $secondTour['price_per_person'] * 3,
        'status' => 'confirmed',
    ]);
    $insertBooking->execute([
        'user_id' => $secondUserId,
        'tour_id' => (int) $firstTour['id'],
        'travel_date' => $travelDate,
        'guests' => 1,
        'total_price' => (float) $firstTour['price_per_person'],
        'status' => 'confirmed',
    ]);

    $bookingQuery = $pdo->prepare(
        'SELECT b.id, b.travel_date, b.guests, b.total_price, t.name AS tour_name
         FROM bookings b
         JOIN tours t ON t.id = b.tour_id
         WHERE b.user_id = :user_id
         ORDER BY b.travel_date ASC, b.id DESC'
    );
    $bookingQuery->execute(['user_id' => $firstUserId]);
    $firstBookings = $bookingQuery->fetchAll();

    check(count($firstBookings) === 2, 'The dashboard query returns only the first user\'s bookings');
    check(
        count($firstBookings) === 2
        && $firstBookings[0]['tour_name'] === $firstTour['name']
        && $firstBookings[1]['tour_name'] === $secondTour['name'],
        'The dashboard query orders bookings by travel date'
    );
    check(
        count($firstBookings) === 2
        && abs((float) $firstBookings[0]['total_price'] - (float) $firstTour['price_per_person'] * 2) < 0.01,
        'The stored booking total matches the tour price multiplied by guests'
    );

    $bookingQuery->execute(['user_id' => $secondUserId]);
    $secondBookings = $bookingQuery->fetchAll();

    check(count($secondBookings) === 1, 'The second user receives a different booking set');
    check(
        $secondBookings !== [] && $secondBookings[0]['tour_name'] === $firstTour['name'],
        'The second user does not receive the first user\'s bookings'
    );

    try {
        $insertBooking->execute([
            'user_id' => $firstUserId,
            'tour_id' => 999999999,
            'travel_date' => $travelDate,
            'guests' => 1,
            'total_price' => 1000,
            'status' => 'confirmed',
        ]);
        check(false, 'A booking must reference an existing tour');
    } catch (PDOException $exception) {
        check($exception->getCode() === '23000', 'A booking must reference an existing tour');
    }

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

    $countBookings = $pdo->prepare('SELECT COUNT(*) AS total FROM bookings WHERE user_id = :user_id');
    $countBookings->execute(['user_id' => $firstUserId]);
    $firstBookingCount = (int) $countBookings->fetch()['total'];

    $countBookings->execute(['user_id' => $secondUserId]);
    $secondBookingCount = (int) $countBookings->fetch()['total'];

    check($firstBookingCount === 0, 'Deleting a user cascades to that user\'s bookings');
    check($secondBookingCount === 1, 'Deleting one user leaves another user\'s bookings intact');

    $countTours = $pdo->query('SELECT COUNT(*) AS total FROM tours');
    check((int) $countTours->fetch()['total'] >= 6, 'Deleting users does not remove the tour catalogue');

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
