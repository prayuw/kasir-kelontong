<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_role(ROLE_ADMIN);

$pageTitle  = 'Pengaturan Toko';
$activeMenu = 'pengaturan';

$pdo    = db();
$errors = [];

$stmt = $pdo->query('SELECT nama_toko, alamat, telepon, pesan_struk FROM store_settings WHERE id = 1 LIMIT 1');
$toko = $stmt->fetch() ?: ['nama_toko' => '', 'alamat' => '', 'telepon' => '', 'pesan_struk' => ''];

if (is_post()) {
    if (!csrf_valid()) {
        $errors[] = 'Sesi formulir tidak valid. Muat ulang halaman lalu coba lagi.';
    } else {
        $form = [
            'nama_toko'   => input_string($_POST, 'nama_toko', 100),
            'alamat'      => input_string($_POST, 'alamat', 255),
            'telepon'     => input_string($_POST, 'telepon', 30),
            'pesan_struk' => input_string($_POST, 'pesan_struk', 255),
        ];

        if ($form['nama_toko'] === '') {
            $errors[] = 'Nama toko wajib diisi.';
        }
        if ($form['telepon'] !== '' && !preg_match('/^[0-9+\-\s()]{6,30}$/', $form['telepon'])) {
            $errors[] = 'Nomor telepon hanya boleh berisi angka, spasi, tanda +, tanda -, atau kurung.';
        }

        if (!$errors) {
            try {
                $stmt = $pdo->prepare(
                    'UPDATE store_settings
                        SET nama_toko = ?, alamat = ?, telepon = ?, pesan_struk = ?
                      WHERE id = 1'
                );
                $stmt->execute([
                    $form['nama_toko'],
                    $form['alamat'] !== '' ? $form['alamat'] : null,
                    $form['telepon'] !== '' ? $form['telepon'] : null,
                    $form['pesan_struk'] !== '' ? $form['pesan_struk'] : null,
                ]);

                set_flash('success', 'Informasi toko berhasil disimpan. Perubahan akan tampil pada struk berikutnya.');
                redirect('admin/pengaturan.php');
            } catch (PDOException $e) {
                error_log('[PENGATURAN] ' . $e->getMessage());
                $errors[] = 'Pengaturan gagal disimpan. Silakan coba lagi.';
            }
        }

        // Jika gagal, isian yang sudah diketik tetap ditampilkan
        $toko = $form;
    }
}

require __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h2>Pengaturan Toko</h2>
        <p class="muted">Informasi ini ditampilkan pada bagian atas dan bawah struk.</p>
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

<form method="post" action="<?= e(url('admin/pengaturan.php')) ?>" class="card" style="max-width:720px;">
    <?= csrf_field() ?>

    <div class="form-group">
        <label for="nama_toko">Nama Toko</label>
        <input type="text" id="nama_toko" name="nama_toko" maxlength="100" required
               value="<?= e($toko['nama_toko']) ?>">
    </div>

    <div class="form-group">
        <label for="alamat">Alamat</label>
        <input type="text" id="alamat" name="alamat" maxlength="255"
               value="<?= e($toko['alamat'] ?? '') ?>">
    </div>

    <div class="form-group">
        <label for="telepon">Telepon</label>
        <input type="text" id="telepon" name="telepon" maxlength="30"
               value="<?= e($toko['telepon'] ?? '') ?>" placeholder="contoh: 0812-0000-0000">
    </div>

    <div class="form-group">
        <label for="pesan_struk">Pesan Kaki Struk</label>
        <textarea id="pesan_struk" name="pesan_struk" maxlength="255"><?= e($toko['pesan_struk'] ?? '') ?></textarea>
        <div class="hint">Contoh: Terima kasih sudah berbelanja. Barang yang sudah dibeli tidak dapat dikembalikan.</div>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary">Simpan Pengaturan</button>
    </div>
</form>

<?php require __DIR__ . '/../includes/footer.php'; ?>