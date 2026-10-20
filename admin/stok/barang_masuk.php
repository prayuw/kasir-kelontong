<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_role(ROLE_ADMIN);

if (!is_post()) {
    redirect('admin/stok/index.php');
}

if (!csrf_valid()) {
    set_flash('error', 'Sesi formulir tidak valid. Silakan coba lagi.');
    redirect('admin/stok/index.php');
}

$idRaw      = trim((string) ($_POST['product_id'] ?? ''));
$qtyRaw     = trim((string) ($_POST['qty'] ?? ''));
$keterangan = mb_substr(trim((string) ($_POST['keterangan'] ?? '')), 0, 255);

$errors = [];
if (!is_valid_angka($idRaw, 10) || (int) $idRaw <= 0) {
    $errors[] = 'Pilih barang yang akan ditambah stoknya.';
}
if (!is_valid_angka($qtyRaw, 6) || (int) $qtyRaw <= 0) {
    $errors[] = 'Jumlah barang masuk harus angka bulat lebih dari 0 (maksimal 6 digit).';
}

if ($errors) {
    set_flash('error', implode(' ', $errors));
    redirect('admin/stok/index.php');
}

$pdo    = db();
$id     = (int) $idRaw;
$qty    = (int) $qtyRaw;
$user   = current_user();
$waktu  = date('Y-m-d H:i:s');

try {
    $pdo->beginTransaction();

    // Kunci baris produk agar dua admin yang mengisi bersamaan tidak saling menimpa stok
    $stmt = $pdo->prepare('SELECT id, nama_barang, stok, is_active FROM products WHERE id = ? FOR UPDATE');
    $stmt->execute([$id]);
    $produk = $stmt->fetch();

    if (!$produk) {
        throw new RuntimeException('Produk tidak ditemukan.');
    }
    if ((int) $produk['is_active'] !== 1) {
        throw new RuntimeException('Produk nonaktif tidak bisa ditambah stoknya. Aktifkan produk terlebih dahulu.');
    }

    $sebelum = (int) $produk['stok'];
    $sesudah = $sebelum + $qty;

    $upd = $pdo->prepare('UPDATE products SET stok = ? WHERE id = ?');
    $upd->execute([$sesudah, $id]);

    $mv = $pdo->prepare(
        "INSERT INTO stock_movements
            (product_id, user_id, jenis, qty, stok_sebelum, stok_sesudah, referensi, keterangan, created_at)
         VALUES (?, ?, 'masuk', ?, ?, ?, NULL, ?, ?)"
    );
    $mv->execute([
        $id,
        (int) $user['id'],
        $qty,
        $sebelum,
        $sesudah,
        $keterangan !== '' ? $keterangan : 'Barang masuk',
        $waktu,
    ]);

    $pdo->commit();
    set_flash('success', 'Stok ' . $produk['nama_barang'] . ' bertambah ' . $qty . '. Stok sekarang ' . $sesudah . '.');
} catch (RuntimeException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    set_flash('error', $e->getMessage());
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[BARANG_MASUK] ' . $e->getMessage());
    set_flash('error', 'Stok gagal diperbarui. Silakan coba lagi.');
}

redirect('admin/stok/index.php');