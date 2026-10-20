<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

// Pengguna yang sudah login tidak perlu melihat halaman login lagi
$sudahLogin = current_user();
if ($sudahLogin) {
    redirect(home_path_for_role($sudahLogin['role']));
}

$error    = '';
$username = '';

if (is_post()) {
    if (!csrf_valid()) {
        $error = 'Sesi formulir sudah tidak berlaku. Muat ulang halaman lalu coba lagi.';
    } else {
        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if ($username === '' || $password === '') {
            $error = 'Username dan password wajib diisi.';
        } else {
            $hasil = attempt_login($username, $password);

            if ($hasil['ok']) {
                login_user($hasil['user']);
                set_flash('success', 'Selamat datang, ' . $hasil['user']['nama_lengkap'] . '!');
                redirect(home_path_for_role($hasil['user']['role']));
            }

            $error = $hasil['message'];
        }
    }
}

$flash = get_flash();
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">
</head>

<body class="auth-body">
    <div class="auth-card">
        <div class="auth-brand">
            <div class="brand-icon">K</div>
            <h1>Login Kasir</h1>
            <p class="muted"><?= e(APP_NAME) ?></p>
        </div>

        <?php if ($flash): ?>
            <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
        <?php endif; ?>

        <?php if ($error !== ''): ?>
            <div class="alert alert-error"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="post" action="<?= e(url('auth/login.php')) ?>" autocomplete="off">
            <?= csrf_field() ?>

            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" maxlength="50"
                    value="<?= e($username) ?>" required autofocus>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <div class="password-field">
                    <input type="password" id="password" name="password" maxlength="100"
                        required autocomplete="current-password">
                    <?= tombol_toggle_password('password') ?>
                </div>
            </div>

            <button type="submit" class="btn btn-primary btn-block">Masuk</button>
        </form>
    </div>
        <script src="<?= e(url('assets/js/app.js')) ?>"></script>
</body>
</body>

</html>