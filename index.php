<?php
declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'app.php';

$tours = [];
$error = '';

try {
    $pdo = require __DIR__ . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'db.php';
    $statement = $pdo->query(
        'SELECT id, name, destination, summary, duration_days, price_per_person
         FROM tours
         ORDER BY id'
    );
    $tours = $statement->fetchAll();
} catch (PDOException $exception) {
    $error = database_error_message($exception);
} catch (RuntimeException $exception) {
    $error = database_error_message($exception);
}

$signedIn = isset($_SESSION['user_id'], $_SESSION['username']);
$firstName = $signedIn ? explode(' ', trim((string) $_SESSION['username']))[0] : '';
$bookHref = static function (int $tourId) use ($signedIn): string {
    if ($signedIn) {
        return 'book.php?tour=' . $tourId;
    }

    return 'login.php?next=' . rawurlencode('book.php?tour=' . $tourId);
};
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f5f4ef">
    <meta name="description" content="Northstar plans small-group journeys across northern Pakistan. Browse tours and reserve your dates.">
    <title>Northstar | Small-group journeys in the north</title>
    <link rel="stylesheet" href="assets/style.css">
    <script src="assets/script.js" defer></script>
</head>
<body class="site-page">
    <header class="site-header">
        <a class="brand" href="index.php" aria-label="Northstar home">
            <span class="brand-mark" aria-hidden="true">N</span>
            <span>northstar<span class="brand-period">.</span></span>
        </a>
        <nav class="site-nav" aria-label="Account">
            <?php if ($signedIn): ?>
                <span class="site-nav-greeting">Hello, <?= e($firstName) ?></span>
                <a class="button button-quiet" href="home.php">My bookings</a>
            <?php else: ?>
                <a class="site-nav-link" href="login.php">Sign in</a>
                <a class="button button-primary" href="signin.php">Create account <span aria-hidden="true">↗</span></a>
            <?php endif; ?>
        </nav>
    </header>

    <main>
        <section class="hero" aria-labelledby="hero-heading">
            <div class="hero-copy-block">
                <p class="eyebrow">Small-group journeys in the north</p>
                <h1 id="hero-heading">Go further.<br>Come back lighter<span class="hero-period">.</span></h1>
                <p class="hero-copy">Handpicked routes through Hunza, Skardu, Swat and the Kashmir valley. Choose a journey, pick your dates, and leave the planning to us.</p>
                <div class="hero-actions">
                    <a class="button button-primary" href="#tours">Explore tours <span aria-hidden="true">↗</span></a>
                    <?php if (!$signedIn): ?>
                        <a class="button button-outline" href="signin.php">Create an account</a>
                    <?php endif; ?>
                </div>
                <dl class="hero-stats">
                    <div><dt>Routes</dt><dd><?= count($tours) ?></dd></div>
                    <div><dt>Duration</dt><dd>3–6 days</dd></div>
                    <div><dt>Group size</dt><dd>Up to 12</dd></div>
                </dl>
            </div>
            <div class="hero-art" aria-hidden="true">
                <span class="hero-sun"></span>
                <span class="hero-ridge hero-ridge-back"></span>
                <span class="hero-ridge hero-ridge-front"></span>
                <span class="hero-star hero-star-one"></span>
                <span class="hero-star hero-star-two"></span>
                <span class="hero-star hero-star-three"></span>
            </div>
        </section>

        <section class="tours-section" id="tours" aria-labelledby="tours-heading">
            <div class="section-heading">
                <div>
                    <p class="eyebrow">The collection</p>
                    <h2 id="tours-heading">Choose your next journey</h2>
                </div>
                <?php if ($tours !== []): ?>
                    <span class="item-count"><?= count($tours) ?> routes</span>
                <?php endif; ?>
            </div>

            <?php if ($error !== ''): ?>
                <p class="notice notice-error" role="alert"><?= e($error) ?></p>
            <?php elseif ($tours === []): ?>
                <div class="empty-state">
                    <span class="empty-icon" aria-hidden="true">✳</span>
                    <h3>No tours are open right now.</h3>
                    <p>New dates are added throughout the season. Please check back shortly.</p>
                </div>
            <?php else: ?>
                <div class="tour-grid">
                    <?php foreach ($tours as $index => $tour): ?>
                        <article class="tour-card">
                            <div class="tour-banner tour-tone-<?= ($index % 4) + 1 ?>">
                                <span class="tour-number"><?= str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) ?></span>
                                <span class="tour-destination"><?= e($tour['destination']) ?></span>
                            </div>
                            <div class="tour-body">
                                <h3><?= e($tour['name']) ?></h3>
                                <p><?= e($tour['summary']) ?></p>
                                <div class="tour-meta">
                                    <span><?= (int) $tour['duration_days'] ?> days</span>
                                    <span><?= e(format_price((string) $tour['price_per_person'])) ?> <small>/ person</small></span>
                                </div>
                                <a class="button button-primary button-full" href="<?= e($bookHref((int) $tour['id'])) ?>">
                                    Reserve this tour <span aria-hidden="true">↗</span>
                                </a>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <section class="site-note" aria-label="How it works">
            <div>
                <p class="eyebrow">How it works</p>
                <h2>Pick a route. Choose a date. Go.</h2>
            </div>
            <ol class="steps">
                <li><span>01</span> Create an account so we can keep your bookings together.</li>
                <li><span>02</span> Reserve a tour with your travel date and group size.</li>
                <li><span>03</span> See every confirmed booking on your personal dashboard.</li>
            </ol>
        </section>
    </main>

    <footer class="site-footer">
        <span class="brand-mark" aria-hidden="true">N</span>
        <p>Northstar is a student web project. Demo tours and prices are for local development only.</p>
    </footer>
</body>
</html>
