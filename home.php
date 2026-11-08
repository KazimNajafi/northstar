<?php
declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'app.php';

if (!isset($_SESSION['user_id'], $_SESSION['username'])) {
    header('Location: login.php');
    exit;
}

$bookings = null;
$tours = [];
$error = '';
$bookingConfirmed = ($_GET['booked'] ?? '') === '1';
$userId = (int) $_SESSION['user_id'];

try {
    $pdo = require __DIR__ . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'db.php';

    $bookingQuery = $pdo->prepare(
        'SELECT b.id, b.tour_id, b.travel_date, b.guests, b.total_price, b.status,
                t.name AS tour_name, t.destination, t.duration_days
         FROM bookings b
         JOIN tours t ON t.id = b.tour_id
         WHERE b.user_id = :user_id
         ORDER BY b.travel_date ASC, b.id DESC'
    );
    $bookingQuery->execute(['user_id' => $userId]);
    $bookings = $bookingQuery->fetchAll();

    $tourQuery = $pdo->prepare(
        'SELECT id, name, destination, summary, duration_days, price_per_person
         FROM tours
         WHERE id NOT IN (SELECT tour_id FROM bookings WHERE user_id = :user_id)
         ORDER BY id
         LIMIT 3'
    );
    $tourQuery->execute(['user_id' => $userId]);
    $tours = $tourQuery->fetchAll();
} catch (PDOException $exception) {
    $error = database_error_message($exception);
} catch (RuntimeException $exception) {
    $error = database_error_message($exception);
}

$username = (string) $_SESSION['username'];
$firstName = explode(' ', trim($username))[0];
$bookingCount = $bookings === null ? 0 : count($bookings);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f5f4ef">
    <title>My bookings | Northstar</title>
    <link rel="stylesheet" href="assets/style.css">
    <script src="assets/script.js" defer></script>
</head>
<body class="dashboard-page">
    <header class="topbar">
        <a class="brand" href="index.php" aria-label="Northstar home">
            <span class="brand-mark" aria-hidden="true">N</span>
            <span>northstar<span class="brand-period">.</span></span>
        </a>
        <div class="topbar-right">
            <a class="site-nav-link" href="index.php#tours">Browse tours</a>
            <span class="user-greeting">Your space, <?= e($firstName) ?></span>
            <form method="post" action="logout.php">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <button class="button button-quiet" type="submit">Sign out <span aria-hidden="true">↗</span></button>
            </form>
        </div>
    </header>

    <main class="dashboard-main">
        <section class="welcome-block">
            <p class="eyebrow">Your personal dashboard</p>
            <h1>Good to see you, <?= e($firstName) ?><span class="welcome-period">.</span></h1>
            <p class="welcome-copy">Your journeys, your dates, and the next place on your list.</p>
        </section>

        <section class="items-section" aria-labelledby="bookings-heading">
            <div class="section-heading">
                <div>
                    <p class="eyebrow">Your bookings</p>
                    <h2 id="bookings-heading">Trips you have reserved</h2>
                </div>
                <?php if ($bookings !== null): ?>
                    <span class="item-count"><?= $bookingCount ?> <?= $bookingCount === 1 ? 'booking' : 'bookings' ?></span>
                <?php endif; ?>
            </div>

            <?php if ($bookingConfirmed): ?>
                <p class="notice notice-success" role="status">Your booking is confirmed. It is now listed below.</p>
            <?php endif; ?>

            <?php if ($error !== ''): ?>
                <p class="notice notice-error" role="alert"><?= e($error) ?></p>
            <?php elseif ($bookings === []): ?>
                <div class="empty-state">
                    <span class="empty-icon" aria-hidden="true">✳</span>
                    <h3>No trips booked yet.</h3>
                    <p>Pick a route from the collection and reserve your travel dates. Your bookings will appear here.</p>
                    <a class="button button-primary" href="index.php#tours">Explore tours <span aria-hidden="true">↗</span></a>
                </div>
            <?php else: ?>
                <div class="booking-list">
                    <?php foreach ($bookings as $booking): ?>
                        <?php $travelDate = new DateTimeImmutable((string) $booking['travel_date']); ?>
                        <article class="booking-card" data-booking-tour="<?= (int) $booking['tour_id'] ?>">
                            <div class="booking-date" aria-hidden="true">
                                <span class="booking-month"><?= e($travelDate->format('M')) ?></span>
                                <strong class="booking-day"><?= e($travelDate->format('j')) ?></strong>
                                <span class="booking-year"><?= e($travelDate->format('Y')) ?></span>
                            </div>
                            <div class="booking-info">
                                <h3><?= e($booking['tour_name']) ?></h3>
                                <p><?= e($booking['destination']) ?> &middot; <?= (int) $booking['duration_days'] ?> days &middot; <?= (int) $booking['guests'] ?> <?= (int) $booking['guests'] === 1 ? 'guest' : 'guests' ?></p>
                                <span class="booking-date-line">Departing <?= e(format_travel_date((string) $booking['travel_date'])) ?></span>
                            </div>
                            <div class="booking-side">
                                <span class="booking-status"><?= e(ucfirst((string) $booking['status'])) ?></span>
                                <span class="booking-total"><?= e(format_price((string) $booking['total_price'])) ?></span>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <?php if ($error === '' && $tours !== []): ?>
            <section class="items-section feature-section" aria-labelledby="next-heading">
                <div class="section-heading">
                    <div>
                        <p class="eyebrow">Still to explore</p>
                        <h2 id="next-heading">Plan another escape</h2>
                    </div>
                    <a class="site-nav-link" href="index.php#tours">See all tours</a>
                </div>
                <div class="tour-grid tour-grid-compact">
                    <?php foreach ($tours as $index => $tour): ?>
                        <article class="tour-card">
                            <div class="tour-banner tour-tone-<?= ($index % 4) + 1 ?>">
                                <span class="tour-destination"><?= e($tour['destination']) ?></span>
                            </div>
                            <div class="tour-body">
                                <h3><?= e($tour['name']) ?></h3>
                                <p><?= e($tour['summary']) ?></p>
                                <div class="tour-meta">
                                    <span><?= (int) $tour['duration_days'] ?> days</span>
                                    <span><?= e(format_price((string) $tour['price_per_person'])) ?> <small>/ person</small></span>
                                </div>
                                <a class="button button-primary button-full" href="book.php?tour=<?= (int) $tour['id'] ?>">
                                    Reserve this tour <span aria-hidden="true">↗</span>
                                </a>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <div class="dashboard-signoff">
            <span class="signoff-line" aria-hidden="true"></span>
            <p>Pack light. Go far.</p>
            <span class="signoff-line" aria-hidden="true"></span>
        </div>
    </main>
</body>
</html>
