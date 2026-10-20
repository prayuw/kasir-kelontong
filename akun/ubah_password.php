<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_login();

$pageTitle  = 'Ganti Password';
$activeMenu = 'password';

$pdo    = db();
$user   = current_user();
$errors = [];

if (is_post()) {
    if (!csrf_valid()) {
        $errors[] = 'Sesi formulir tidak valid. Muat ulang halaman lalu coba lagi.';
    } else {
        // Password tidak di-trim agar spasi di awal atau akhir tetap terbaca apa adanya
        $lama = is_string($_POST['password_lama'] ?? null) ? $_POST['password_lama'] : '';
        $baru = is_string($_POST['password_baru'] ?? null) ? $_POST['password_baru'] : '';
        $konf = is_string($_POST['konfirmasi'] ?? null) ? $_POST['konfirmasi'] : '';

        $stmt = $pdo->prepare('SELECT password FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([(int) $user['id']]);
        $hash = (string) $stmt->fetchColumn();

        if ($lama === '' || !password_verify($lama, $hash)) {
            $errors[] = 'Password lama tidak sesuai.';
        }

        $panjang = mb_strlen($baru);
        if ($panjang < 8) {
            $errors[] = 'Password baru minimal 8 karakter.';
        } elseif ($panjang > 100) {
            $errors[] = 'Password baru maksimal 100 karakter.';
        } elseif ($baru !== $konf) {
            $errors[] = 'Konfirmasi password tidak sama dengan password baru.';
        } elseif ($hash !== '' && password_verify($baru, $hash)) {
            $errors[] = 'Password baru tidak boleh sama dengan password lama.';
        }

        if (!$errors) {
            try {
                $upd = $pdo->prepare('UPDATE users SET password = ? WHERE id = ?');
                $upd->execute([password_hash($baru, PASSWORD_DEFAULT), (int) $user['id']]);

                // Sesi diperbarui setelah perubahan kredensial
                session_regenerate_id(true);

                set_flash('success', 'Password berhasil diganti. Gunakan password baru saat login berikutnya.');
                redirect(home_path_for_role($user['role']));
            } catch (PDOException $e) {
                error_log('[GANTI_PASSWORD] ' . $e->getMessage());
                $errors[] = 'Password gagal diganti. Silakan coba lagi.';
            }
        }
    }
}

require __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h2>Ganti Password</h2>
        <p class="muted">Untuk akun <strong><?= e($user['username']) ?></strong>. Gunakan password yang belum pernah dipakai di tempat lain.</p>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-error">
        <ul>
            <?php foreach ($errors as $err): ?>
                <li><?= e($err) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form method="post" action="<?= e(url('akun/ubah_password.php')) ?>" class="card" style="max-width:520px;" autocomplete="off">
    <?= csrf_field() ?>

    <div class="form-group">
        <label for="password_lama">Password Lama</label>
        <div class="password-field">
            <input type="password" id="password_lama" name="password_lama" maxlength="100"
                   autocomplete="current-password" required>
            <?= tombol_toggle_password('password_lama') ?>
        </div>
    </div>

    <div class="form-group">
        <label for="password_baru">Password Baru</label>
        <div class="password-field">
            <input type="password" id="password_baru" name="password_baru" maxlength="100"
                   autocomplete="new-password" required>
            <?= tombol_toggle_password('password_baru') ?>
        </div>
        <div class="hint">Minimal 8 karakter.</div>
    </div>

    <div class="form-group">
        <label for="konfirmasi">Ulangi Password Baru</label>
        <div class="password-field">
            <input type="password" id="konfirmasi" name="konfirmasi" maxlength="100"
                   autocomplete="new-password" required>
            <?= tombol_toggle_password('konfirmasi') ?>
        </div>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary">Simpan Password Baru</button>
        <a class="btn btn-secondary" href="<?= e(url(home_path_for_role($user['role']))) ?>">Batal</a>
    </div>
</form>

<?php require __DIR__ . '/../includes/footer.php'; ?>