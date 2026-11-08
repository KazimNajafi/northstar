<?php
declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'app.php';

if (isset($_SESSION['user_id'])) {
    header('Location: home.php');
    exit;
}

$error = '';
$username = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim(post_string('username'));
    $email = strtolower(trim(post_string('email')));
    $password = post_string('password');
    $confirmPassword = post_string('confirm_password');

    if (!csrf_is_valid()) {
        $error = 'Your session expired. Refresh the page and try again.';
    } elseif ($username === '' || strlen($username) > 80) {
        $error = 'Enter a username that is no longer than 80 characters.';
    } elseif (strlen($email) > 254 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        $error = 'Enter a valid email address.';
    } elseif (strlen($password) < 8 || strlen($password) > 72) {
        $error = 'Choose a password between 8 and 72 characters.';
    } elseif ($password !== $confirmPassword) {
        $error = 'Your passwords do not match.';
    } else {
        try {
            $pdo = require __DIR__ . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'db.php';
            $checkUser = $pdo->prepare(
                'SELECT id FROM users WHERE email = :email OR username = :username LIMIT 1'
            );
            $checkUser->execute([
                'email' => $email,
                'username' => $username,
            ]);

            if ($checkUser->fetch() !== false) {
                $error = 'An account with that email or username already exists.';
            } else {
                $insertUser = $pdo->prepare(
                    'INSERT INTO users (username, email, password) VALUES (:username, :email, :password)'
                );
                $insertUser->execute([
                    'username' => $username,
                    'email' => $email,
                    'password' => password_hash($password, PASSWORD_DEFAULT),
                ]);

                sign_in_user([
                    'id' => (int) $pdo->lastInsertId(),
                    'username' => $username,
                ]);

                header('Location: home.php');
                exit;
            }
        } catch (RuntimeException $exception) {
            $error = $exception->getMessage();
        } catch (PDOException $exception) {
            error_log('Registration failed: ' . $exception->getMessage());

            if ($exception->getCode() === '23000') {
                $error = 'An account with that email or username already exists.';
            } else {
                $error = 'We could not create your account. Please try again shortly.';
            }
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
    <title>Create your account | Northstar</title>
    <link rel="stylesheet" href="assets/style.css">
    <script src="assets/script.js" defer></script>
</head>
<body class="auth-page">
    <main class="auth-shell">
        <section class="auth-story" aria-label="About Northstar">
            <a class="brand brand-light" href="signin.php" aria-label="Northstar home">
                <span class="brand-mark" aria-hidden="true">N</span>
                <span>northstar<span class="brand-period">.</span></span>
            </a>
            <div class="story-copy">
                <p class="eyebrow">A calmer way to move forward</p>
                <h1>Make room for what matters.</h1>
                <p class="story-description">A thoughtful home for your ideas, your work, and the next thing you want to make happen.</p>
            </div>
            <div class="story-note">
                <span class="note-spark" aria-hidden="true">✳</span>
                <p>Small steps, considered well,<br>can take you somewhere remarkable.</p>
            </div>
            <span class="story-orbit story-orbit-one" aria-hidden="true"></span>
            <span class="story-orbit story-orbit-two" aria-hidden="true"></span>
        </section>

        <section class="auth-panel">
            <div class="mobile-brand">
                <a class="brand" href="signin.php">
                    <span class="brand-mark" aria-hidden="true">N</span>
                    <span>northstar<span class="brand-period">.</span></span>
                </a>
            </div>
            <div class="auth-card">
                <div class="auth-heading">
                    <p class="eyebrow">Your next chapter</p>
                    <h2>Create your account</h2>
                    <p class="muted">A few details, and you're on your way.</p>
                </div>

                <?php if ($error !== ''): ?>
                    <p class="notice notice-error" role="alert"><?= e($error) ?></p>
                <?php endif; ?>

                <form id="register-form" class="form-stack" method="post" action="signin.php" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <label class="field">
                        <span>Your name</span>
                        <input type="text" name="username" autocomplete="username" maxlength="80" value="<?= e($username) ?>" placeholder="e.g. Alex Morgan" required>
                    </label>
                    <label class="field">
                        <span>Email address</span>
                        <input type="email" name="email" autocomplete="email" maxlength="254" value="<?= e($email) ?>" placeholder="you@example.com" required>
                    </label>
                    <div class="field-row">
                        <label class="field">
                            <span>Password</span>
                            <input type="password" name="password" autocomplete="new-password" minlength="8" maxlength="72" placeholder="At least 8 characters" required>
                        </label>
                        <label class="field">
                            <span>Confirm password</span>
                            <input type="password" name="confirm_password" autocomplete="new-password" minlength="8" maxlength="72" placeholder="Type it again" required>
                        </label>
                    </div>
                    <button class="button button-primary button-full" type="submit">
                        Create account <span aria-hidden="true">↗</span>
                    </button>
                </form>

                <p class="auth-switch">Already have an account? <a href="login.php">Sign in</a></p>
                <p class="fine-print">By continuing, you agree to keep your account details secure.</p>
            </div>
            <p class="panel-footnote">A little more clarity, every day.</p>
        </section>
    </main>
</body>
</html>
