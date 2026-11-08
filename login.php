<?php
declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'app.php';

if (isset($_SESSION['user_id'])) {
    header('Location: home.php');
    exit;
}

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim(post_string('email')));
    $password = post_string('password');

    if (!csrf_is_valid()) {
        $error = 'Your session expired. Refresh the page and try again.';
    } elseif ($email === '' || $password === '') {
        $error = 'Enter your email address and password.';
    } else {
        try {
            $pdo = require __DIR__ . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'db.php';
            $statement = $pdo->prepare(
                'SELECT id, username, password FROM users WHERE email = :email LIMIT 1'
            );
            $statement->execute(['email' => $email]);
            $user = $statement->fetch();

            if ($user !== false && password_verify($password, $user['password'])) {
                sign_in_user($user);
                header('Location: home.php');
                exit;
            }

            $error = 'That email and password combination was not recognized.';
        } catch (RuntimeException $exception) {
            $error = $exception->getMessage();
        } catch (PDOException $exception) {
            error_log('Login failed: ' . $exception->getMessage());
            $error = 'We could not sign you in right now. Please try again shortly.';
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f5f4ef">
    <title>Sign in | Northstar</title>
    <link rel="stylesheet" href="assets/style.css">
    <script src="assets/script.js" defer></script>
</head>
<body class="auth-page">
    <main class="auth-shell">
        <section class="auth-story" aria-label="About Northstar">
            <a class="brand brand-light" href="login.php" aria-label="Northstar home">
                <span class="brand-mark" aria-hidden="true">N</span>
                <span>northstar<span class="brand-period">.</span></span>
            </a>
            <div class="story-copy">
                <p class="eyebrow">Good to have you back</p>
                <h1>Your focus is a powerful thing.</h1>
                <p class="story-description">Pick up where you left off and give your best ideas the attention they deserve.</p>
            </div>
            <div class="story-note">
                <span class="note-spark" aria-hidden="true">✳</span>
                <p>Find your direction.<br>Take the next small step.</p>
            </div>
            <span class="story-orbit story-orbit-one" aria-hidden="true"></span>
            <span class="story-orbit story-orbit-two" aria-hidden="true"></span>
        </section>

        <section class="auth-panel">
            <div class="mobile-brand">
                <a class="brand" href="login.php">
                    <span class="brand-mark" aria-hidden="true">N</span>
                    <span>northstar<span class="brand-period">.</span></span>
                </a>
            </div>
            <div class="auth-card auth-card-login">
                <div class="auth-heading">
                    <p class="eyebrow">Welcome back</p>
                    <h2>Sign in to Northstar</h2>
                    <p class="muted">Your space is right where you left it.</p>
                </div>

                <?php if ($error !== ''): ?>
                    <p class="notice notice-error" role="alert"><?= e($error) ?></p>
                <?php endif; ?>

                <form id="login-form" class="form-stack" method="post" action="login.php" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <label class="field">
                        <span>Email address</span>
                        <input type="email" name="email" autocomplete="email" maxlength="254" value="<?= e($email) ?>" placeholder="you@example.com" required>
                    </label>
                    <label class="field">
                        <span>Password</span>
                        <input type="password" name="password" autocomplete="current-password" required>
                    </label>
                    <button class="button button-primary button-full" type="submit">
                        Sign in <span aria-hidden="true">↗</span>
                    </button>
                </form>

                <p class="auth-switch">New to Northstar? <a href="signin.php">Create an account</a></p>
            </div>
            <p class="panel-footnote">A little more clarity, every day.</p>
        </section>
    </main>
</body>
</html>
