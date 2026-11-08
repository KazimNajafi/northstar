<?php
declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'app.php';

$tourId = (int) ($_POST['tour_id'] ?? $_GET['tour'] ?? 0);

if (!isset($_SESSION['user_id'], $_SESSION['username'])) {
    $target = 'book.php' . ($tourId > 0 ? '?tour=' . $tourId : '');
    header('Location: login.php?next=' . rawurlencode($target));
    exit;
}

$tour = null;
$error = '';
$storageError = '';
$travelDate = '';
$guests = '2';
$today = new DateTimeImmutable('today');

try {
    $pdo = require __DIR__ . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'db.php';

    if ($tourId > 0) {
        $tourQuery = $pdo->prepare(
            'SELECT id, name, destination, summary, description, duration_days, price_per_person
             FROM tours
             WHERE id = :id
             LIMIT 1'
        );
        $tourQuery->execute(['id' => $tourId]);
        $tour = $tourQuery->fetch();
    }
} catch (PDOException $exception) {
    $storageError = database_error_message($exception);
} catch (RuntimeException $exception) {
    $storageError = database_error_message($exception);
}

if ($tour === false) {
    $tour = null;
    http_response_code(404);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tour !== null && $storageError === '') {
    $travelDate = trim(post_string('travel_date'));
    $guests = trim(post_string('guests'));

    $normalisedDate = parse_travel_date($travelDate, $today);
    $guestCount = parse_guest_count($guests);

    if (!csrf_is_valid()) {
        $error = 'Your session expired. Refresh the page and try again.';
    } elseif ($normalisedDate === null) {
        $error = 'Choose a travel date between today and two years from today.';
    } elseif ($guestCount === null) {
        $error = 'Choose between 1 and 12 guests.';
    } else {
        $totalPrice = round((float) $tour['price_per_person'] * $guestCount, 2);

        try {
            $insertBooking = $pdo->prepare(
                'INSERT INTO bookings (user_id, tour_id, travel_date, guests, total_price, status)
                 VALUES (:user_id, :tour_id, :travel_date, :guests, :total_price, :status)'
            );
            $insertBooking->execute([
                'user_id' => (int) $_SESSION['user_id'],
                'tour_id' => (int) $tour['id'],
                'travel_date' => $normalisedDate,
                'guests' => $guestCount,
                'total_price' => $totalPrice,
                'status' => 'confirmed',
            ]);

            header('Location: home.php?booked=1');
            exit;
        } catch (PDOException $exception) {
            $error = database_error_message($exception);
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && $storageError !== '') {
    $error = $storageError;
}

$price = $tour !== null ? (float) $tour['price_per_person'] : 0.0;
$estimatedGuests = parse_guest_count($guests) ?? 1;
$estimatedTotal = round($price * $estimatedGuests, 2);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f5f4ef">
    <title><?= $tour !== null ? 'Reserve ' . e($tour['name']) . ' | Northstar' : 'Tour not found | Northstar' ?></title>
    <link rel="stylesheet" href="assets/style.css">
    <script src="assets/script.js" defer></script>
</head>
<body class="site-page booking-page">
    <header class="site-header">
        <a class="brand" href="index.php" aria-label="Northstar home">
            <span class="brand-mark" aria-hidden="true">N</span>
            <span>northstar<span class="brand-period">.</span></span>
        </a>
        <nav class="site-nav" aria-label="Account">
            <a class="site-nav-link" href="index.php#tours">All tours</a>
            <a class="button button-quiet" href="home.php">My bookings</a>
        </nav>
    </header>

    <main class="booking-main">
        <?php if ($tour === null): ?>
            <section class="booking-missing">
                <p class="eyebrow">Not available</p>
                <h1>We could not find that tour<span class="welcome-period">.</span></h1>
                <p class="muted"><?= $storageError !== '' ? e($storageError) : 'The tour may have been removed, or the link is incomplete.' ?></p>
                <a class="button button-primary" href="index.php#tours">Back to the catalogue <span aria-hidden="true">↗</span></a>
            </section>
        <?php else: ?>
            <div class="booking-shell">
                <section class="booking-story" aria-labelledby="booking-heading">
                    <a class="back-link" href="index.php#tours"><span aria-hidden="true">←</span> All tours</a>
                    <p class="eyebrow"><?= e($tour['destination']) ?></p>
                    <h1 id="booking-heading"><?= e($tour['name']) ?><span class="welcome-period">.</span></h1>
                    <p class="booking-summary"><?= e($tour['description']) ?></p>
                    <dl class="booking-facts">
                        <div><dt>Duration</dt><dd><?= (int) $tour['duration_days'] ?> days</dd></div>
                        <div><dt>Price</dt><dd><?= e(format_price((string) $tour['price_per_person'])) ?> <small>/ person</small></dd></div>
                        <div><dt>Group size</dt><dd>1 to 12 guests</dd></div>
                        <div><dt>Availability</dt><dd>Daily departures</dd></div>
                    </dl>
                    <div class="booking-assurance">
                        <span class="note-spark" aria-hidden="true">✳</span>
                        <p>Free changes up to 14 days before departure. This is a local demo, so no payment is taken.</p>
                    </div>
                </section>

                <section class="booking-panel" aria-labelledby="form-heading">
                    <div class="booking-panel-inner">
                        <p class="eyebrow">Reserve your place</p>
                        <h2 id="form-heading">Choose your travel dates</h2>
                        <p class="muted">We will hold your place and show the booking on your dashboard.</p>

                        <?php if ($error !== ''): ?>
                            <p class="notice notice-error" role="alert"><?= e($error) ?></p>
                        <?php endif; ?>

                        <form class="form-stack booking-form" method="post" action="book.php" novalidate
                              data-price-per-person="<?= e((string) $price) ?>">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="tour_id" value="<?= (int) $tour['id'] ?>">

                            <label class="field">
                                <span>Travel date</span>
                                <input
                                    type="date"
                                    name="travel_date"
                                    value="<?= e($travelDate) ?>"
                                    min="<?= e($today->format('Y-m-d')) ?>"
                                    max="<?= e($today->modify('+730 days')->format('Y-m-d')) ?>"
                                    required
                                >
                            </label>

                            <label class="field">
                                <span>Number of guests</span>
                                <input
                                    type="number"
                                    name="guests"
                                    value="<?= e($guests) ?>"
                                    min="1"
                                    max="12"
                                    step="1"
                                    inputmode="numeric"
                                    required
                                    data-guest-input
                                >
                            </label>

                            <div class="booking-total" aria-live="polite">
                                <span>Estimated total</span>
                                <strong data-booking-total><?= e(format_price($estimatedTotal)) ?></strong>
                            </div>

                            <button class="button button-primary button-full" type="submit">
                                Confirm booking <span aria-hidden="true">↗</span>
                            </button>
                            <p class="fine-print">No card details are collected. This is a local demonstration booking.</p>
                        </form>
                    </div>
                </section>
            </div>
        <?php endif; ?>
    </main>
</body>
</html>
