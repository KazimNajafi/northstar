<?php
declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'app.php';

if (!isset($_SESSION['user_id'], $_SESSION['username'])) {
    header('Location: login.php');
    exit;
}

$items = null;
$error = '';

try {
    $pdo = require __DIR__ . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'db.php';
    $statement = $pdo->prepare(
        'SELECT title, description FROM items WHERE user_id = :user_id ORDER BY id DESC'
    );
    $statement->execute(['user_id' => (int) $_SESSION['user_id']]);
    $items = $statement->fetchAll();
} catch (RuntimeException $exception) {
    $error = $exception->getMessage();
} catch (PDOException $exception) {
    error_log('Dashboard items could not be loaded: ' . $exception->getMessage());
    $error = 'Your items could not be loaded right now. Please refresh the page in a moment.';
}

$username = (string) $_SESSION['username'];
$firstName = explode(' ', trim($username))[0];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f5f4ef">
    <title>Your space | Northstar</title>
    <link rel="stylesheet" href="assets/style.css">
    <script src="assets/script.js" defer></script>
</head>
<body class="dashboard-page">
    <header class="topbar">
        <a class="brand" href="home.php" aria-label="Northstar dashboard">
            <span class="brand-mark" aria-hidden="true">N</span>
            <span>northstar<span class="brand-period">.</span></span>
        </a>
        <div class="topbar-right">
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
            <p class="welcome-copy">A little space to keep the things you want to move forward.</p>
        </section>

        <section class="items-section" aria-labelledby="items-heading">
            <div class="section-heading">
                <div>
                    <p class="eyebrow">Your collection</p>
                    <h2 id="items-heading">A few things to keep in view</h2>
                </div>
                <?php if ($items !== null): ?>
                    <span class="item-count"><?= count($items) ?> <?= count($items) === 1 ? 'item' : 'items' ?></span>
                <?php endif; ?>
            </div>

            <?php if ($error !== ''): ?>
                <p class="notice notice-error" role="alert"><?= e($error) ?></p>
            <?php elseif ($items === []): ?>
                <div class="empty-state">
                    <span class="empty-icon" aria-hidden="true">✳</span>
                    <h3>Your space is ready.</h3>
                    <p>There are no items here yet. When you have something to keep in view, it will have a home here.</p>
                </div>
            <?php else: ?>
                <div class="item-grid">
                    <?php foreach ($items as $index => $item): ?>
                        <article class="item-card">
                            <span class="item-number"><?= str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) ?></span>
                            <h3><?= e($item['title']) ?></h3>
                            <p><?= e($item['description']) ?></p>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <div class="dashboard-signoff">
            <span class="signoff-line" aria-hidden="true"></span>
            <p>One thoughtful step at a time.</p>
            <span class="signoff-line" aria-hidden="true"></span>
        </div>
    </main>
</body>
</html>
