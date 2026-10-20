<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_login();

$user = current_user();
$kode = trim((string) ($_GET['kode'] ?? ''));

if ($kode === '' || mb_strlen($kode) > 30) {
    show_error_page(404, 'Struk Tidak Ditemukan', 'Kode transaksi tidak valid.');
}

$pdo  = db();
$stmt = $pdo->prepare(
    'SELECT t.*, u.nama_lengkap
       FROM transactions t
       JOIN users u ON u.id = t.user_id
      WHERE t.kode_transaksi = ?
      LIMIT 1'
);
$stmt->execute([$kode]);
$trx = $stmt->fetch();

// Kasir hanya boleh melihat transaksinya sendiri. Admin boleh melihat semua.
if (!$trx || ($user['role'] !== ROLE_ADMIN && (int) $trx['user_id'] !== (int) $user['id'])) {
    show_error_page(404, 'Struk Tidak Ditemukan', 'Transaksi tidak ditemukan atau bukan milik Anda.');
}

$detailStmt = $pdo->prepare(
    'SELECT nama_barang, harga_jual, qty, subtotal
       FROM transaction_details
      WHERE transaction_id = ?
      ORDER BY id ASC'
);
$detailStmt->execute([(int) $trx['id']]);
$items = $detailStmt->fetchAll();

$toko = $pdo->query('SELECT nama_toko, alamat, telepon, pesan_struk FROM store_settings WHERE id = 1')->fetch()
    ?: ['nama_toko' => APP_NAME, 'alamat' => '', 'telepon' => '', 'pesan_struk' => ''];

$isAdmin = $user['role'] === ROLE_ADMIN;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Struk <?= e($trx['kode_transaksi']) ?> | <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">
</head>
<body class="struk-body">

<div class="struk-actions no-print">
    <button type="button" class="btn btn-primary" id="btnCetak">Cetak Struk</button>
    <?php if ($isAdmin): ?>
        <a class="btn btn-secondary" href="<?= e(url('admin/dashboard.php')) ?>">Kembali ke Dashboard</a>
    <?php else: ?>
        <a class="btn btn-secondary" href="<?= e(url('kasir/transaksi.php')) ?>">Transaksi Baru</a>
    <?php endif; ?>
</div>

<div class="struk">
    <div class="struk-center">
        <strong><?= e($toko['nama_toko']) ?></strong><br>
        <?php if (!empty($toko['alamat'])): ?><?= e($toko['alamat']) ?><br><?php endif; ?>
        <?php if (!empty($toko['telepon'])): ?>Telp: <?= e($toko['telepon']) ?><?php endif; ?>
    </div>

    <hr>

    <div>No: <?= e($trx['kode_transaksi']) ?></div>
    <div>Tanggal: <?= e(tanggal_indo($trx['created_at'])) ?></div>
    <div>Kasir: <?= e($trx['nama_lengkap']) ?></div>

    <hr>

    <?php foreach ($items as $it): ?>
        <div class="struk-item"><?= e($it['nama_barang']) ?></div>
        <div class="struk-row">
            <span><?= (int) $it['qty'] ?> x <?= e(rupiah($it['harga_jual'])) ?></span>
            <span><?= e(rupiah($it['subtotal'])) ?></span>
        </div>
    <?php endforeach; ?>

    <hr>

    <div class="struk-row"><span>Jumlah item</span><span><?= (int) $trx['total_item'] ?></span></div>
    <div class="struk-row struk-total"><span>TOTAL</span><span><?= e(rupiah($trx['total_belanja'])) ?></span></div>
    <div class="struk-row"><span>Bayar</span><span><?= e(rupiah($trx['uang_bayar'])) ?></span></div>
    <div class="struk-row"><span>Kembalian</span><span><?= e(rupiah($trx['kembalian'])) ?></span></div>

    <hr>

    <?php if (!empty($toko['pesan_struk'])): ?>
        <div class="struk-pesan"><?= e($toko['pesan_struk']) ?></div>
    <?php endif; ?>
</div>

    <?php if ($trx['status'] === 'batal'): ?>
        <div class="struk-batal">
            *** DIBATALKAN ***<br>
            <small>Stok barang sudah dikembalikan</small>
        </div>
    <?php endif; ?>

<script>
    document.getElementById('btnCetak').addEventListener('click', function () {
        window.print();
    });
</script>
</body>
</html>