<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_role(ROLE_ADMIN);

if (!is_post()) {
    redirect('admin/transaksi/index.php');
}

if (!csrf_valid()) {
    set_flash('error', 'Sesi formulir tidak valid. Silakan coba lagi.');
    redirect('admin/transaksi/index.php');
}

$id     = (int) ($_POST['id'] ?? 0);
$alasan = input_string($_POST, 'alasan', 255);
$urlDetail = 'admin/transaksi/detail.php?id=' . $id;

if ($id <= 0) {
    set_flash('error', 'Transaksi tidak valid.');
    redirect('admin/transaksi/index.php');
}
if (mb_strlen($alasan) < 5) {
    set_flash('error', 'Alasan pembatalan minimal 5 karakter.');
    redirect($urlDetail);
}

$pdo  = db();
$user = current_user();
$waktu = date('Y-m-d H:i:s');

try {
    $pdo->beginTransaction();

    // Kunci baris transaksi agar tidak bisa dibatalkan dua kali secara bersamaan
    $stmt = $pdo->prepare('SELECT id, kode_transaksi, status FROM transactions WHERE id = ? FOR UPDATE');
    $stmt->execute([$id]);
    $trx = $stmt->fetch();

    if (!$trx) {
        throw new RuntimeException('Transaksi tidak ditemukan.');
    }
    if ($trx['status'] !== 'selesai') {
        throw new RuntimeException('Transaksi ini sudah dibatalkan sebelumnya.');
    }

    $stmt = $pdo->prepare(
        'SELECT product_id, nama_barang, qty
           FROM transaction_details
          WHERE transaction_id = ?
          ORDER BY product_id ASC'
    );
    $stmt->execute([$id]);
    $detail = $stmt->fetchAll();

    if (empty($detail)) {
        throw new RuntimeException('Transaksi tidak memiliki rincian barang.');
    }

    // Kunci semua produk terkait, diurutkan berdasarkan id untuk mencegah deadlock
    $ids = array_values(array_unique(array_map(fn ($d) => (int) $d['product_id'], $detail)));
    sort($ids);
    $ph = implode(',', array_fill(0, count($ids), '?'));

    $stmt = $pdo->prepare("SELECT id, stok FROM products WHERE id IN ($ph) ORDER BY id FOR UPDATE");
    $stmt->execute($ids);

    $stokMap = [];
    foreach ($stmt->fetchAll() as $r) {
        $stokMap[(int) $r['id']] = (int) $r['stok'];
    }

    $stmtStok = $pdo->prepare('UPDATE products SET stok = ? WHERE id = ?');
    $stmtMv   = $pdo->prepare(
        "INSERT INTO stock_movements
            (product_id, user_id, jenis, qty, stok_sebelum, stok_sesudah, referensi, keterangan, created_at)
         VALUES (?, ?, 'pembatalan', ?, ?, ?, ?, ?, ?)"
    );

    $keteranganMv = mb_substr('Pembatalan: ' . $alasan, 0, 255);

    foreach ($detail as $d) {
        $pid = (int) $d['product_id'];
        $qty = (int) $d['qty'];

        if (!array_key_exists($pid, $stokMap)) {
            throw new RuntimeException('Data produk ' . $d['nama_barang'] . ' tidak ditemukan.');
        }

        $sebelum = $stokMap[$pid];
        $sesudah = $sebelum + $qty;

        $stmtStok->execute([$sesudah, $pid]);

        // Qty bernilai positif karena stok kembali masuk
        $stmtMv->execute([
            $pid, (int) $user['id'], $qty, $sebelum, $sesudah,
            $trx['kode_transaksi'], $keteranganMv, $waktu,
        ]);
    }

    $stmt = $pdo->prepare(
        "UPDATE transactions
            SET status = 'batal', batal_oleh = ?, batal_at = ?, alasan_batal = ?
          WHERE id = ?"
    );
    $stmt->execute([(int) $user['id'], $waktu, $alasan, $id]);

    $pdo->commit();
    set_flash('success', 'Transaksi ' . $trx['kode_transaksi'] . ' dibatalkan dan stok barang sudah dikembalikan.');
} catch (RuntimeException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    set_flash('error', $e->getMessage());
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[BATAL_TRANSAKSI] ' . $e->getMessage());
    set_flash('error', 'Pembatalan gagal. Stok dan status transaksi tidak berubah, silakan coba lagi.');
}

redirect($urlDetail);