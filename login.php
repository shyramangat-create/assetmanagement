<?php
require_once __DIR__ . '/config/config.php';
if (!empty($_SESSION['admin_id']))
    redirect('dashboard.php');
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $st = $pdo->prepare('SELECT id,username,password FROM admin WHERE username=? LIMIT 1');
    $st->execute([$username]);
    $admin = $st->fetch();
    if ($admin && password_verify($password, $admin['password'])) {
        session_regenerate_id(true);
        $_SESSION['admin_id'] = (int) $admin['id'];
        $_SESSION['admin_username'] = $admin['username'];
        unset($_SESSION['csrf_token']);
        redirect('dashboard.php');
    } else $error = 'Invalid username or password.';
}
?>
<!doctype html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width,initial-scale=1">
        <title>Admin Login | Asset Management</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css?v=<?= filemtime(__DIR__ . '/assets/css/style.css') ?>">
        <script src="<?= BASE_URL ?>assets/js/login.js?v=<?= filemtime(__DIR__ . '/assets/js/login.js') ?>" defer></script>
    </head>
    <body class="login-page">
        <main class="login-shell">
            <section class="login-card">
                <div class="brand-mark login-mark">AM</div>
                <h1 class="h3 fw-bold text-center mt-3">Asset Management</h1>
                <p class="text-secondary text-center mb-4">Sign in to your administrator account</p>

                <?php if ($error): ?>
                    <div class="alert alert-danger py-2" role="alert"><?= e($error) ?></div>

                <?php endif; ?>
                <form method="post">
                    <?= csrfField() ?> <div class="mb-3">
                        <label class="form-label" for="username">Username</label>
                        <div class="login-input-wrap">
                            <img src="<?= BASE_URL ?>assets/images/user-round.png" class="login-input-icon" alt="">
                            <input id="username" class="form-control form-control-lg" name="username" autocomplete="username" required autofocus>
                        </div>
                    </div>
                    <div class="mb-4">
                        <label class="form-label" for="password">Password</label>
                        <div class="login-input-wrap">
                            <img src="<?= BASE_URL ?>assets/images/lock.png" class="login-input-icon" alt="">
                            <input id="password" class="form-control form-control-lg login-password-input" type="password" name="password" autocomplete="current-password" required>
                            <button id="togglePassword" class="login-password-toggle" type="button" aria-controls="password" aria-label="Show password" title="Show password" hidden>
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                                    <g class="password-eye-closed">
                                        <path d="M2 10s3.5 6 10 6 10-6 10-6M4.9 13.3l-1.4 1.4M9 15l-.5 2M15 15l.5 2M19.1 13.3l1.4 1.4" />
                                    </g>
                                    <g class="password-eye-open">
                                        <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z" />
                                        <circle cx="12" cy="12" r="3" />
                                    </g>
                                </svg>
                            </button>
                        </div>
                    </div>
                    <button class="btn btn-primary btn-lg w-100" type="submit">Sign in</button>
                </form>
                <div class="small text-secondary text-center mt-4">Administrator access only</div>
            </section>
        </main>
    </body>
</html>
