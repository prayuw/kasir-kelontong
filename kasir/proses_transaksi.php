<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_role(ROLE_KASIR);

const KERANJANG_MAKS_ITEM = 50;

/**
 * Menyimpan isi keranjang lalu kembali ke halaman kasir dengan pesan error.
 * Keranjang diambil ulang dari database agar nama dan harga yang tampil tetap benar.
 */
function kembali_dengan_error(PDO $pdo, string $pesan, array $qtyMap, string $bayarRaw): void
{
    $items = [];

    if (!empty($qtyMap)) {
        $ids = array_keys($qtyMap);
        $ph  = implode(',', array_fill(0, count($ids), '?'));

        $stmt = $pdo->prepare(
            "SELECT id, kode_barang, nama_barang, satuan, harga_jual, stok
               FROM products
              WHERE id IN ($ph)"
        );
        $stmt->execute($ids);

        $rows = [];
        foreach ($stmt->fetchAll() as $r) {
            $rows[(int) $r['id']] = $r;
        }

        foreach ($qtyMap as $pid => $qty) {
            if (!isset($rows[$pid])) {
                continue;
            }
            $r = $rows[$pid];
            $items[] = [
                'id'     => (int) $r['id'],
                'kode'   => $r['kode_barang'],
                'nama'   => $r['nama_barang'],
                'satuan' => $r['satuan'],
                'harga'  => (int) $r['harga_jual'],
                'stok'   => (int) $r['stok'],
                'qty'    => (int) $qty,
            ];
        }
    }

    $_SESSION['old_checkout'] = [
        'items'      => $items,
        'uang_bayar' => is_valid_angka($bayarRaw, 10) ? $bayarRaw : '',
    ];

    set_flash('error', $pesan);
    redirect('kasir/transaksi.php');
}

if (!is_post()) {
    redirect('kasir/transaksi.php');
}

if (!csrf_valid()) {
    set_flash('error', 'Sesi formulir tidak valid. Muat ulang halaman kasir lalu coba lagi.');
    redirect('kasir/transaksi.php');
}

$pdo      = db();
$user     = current_user();
$bayarRaw = trim((string) ($_POST['uang_bayar'] ?? ''));

/* ---------- Validasi bentuk data keranjang ---------- */
$itemsRaw = $_POST['items'] ?? [];
$itemsRaw = is_array($itemsRaw) ? $itemsRaw : [];

$qtyMap       = [];
$adaItemRusak = false;

foreach ($itemsRaw as $row) {
    $pid = is_array($row) ? (string) ($row['product_id'] ?? '') : '';
    $qty = is_array($row) ? (string) ($row['qty'] ?? '') : '';

    if (!is_valid_angka($pid, 10) || (int) $pid <= 0
        || !is_valid_angka($qty, 4) || (int) $qty <= 0) {
        $adaItemRusak = true;
        continue;
    }

    // Barang yang sama dalam dua baris digabung menjadi satu
    $qtyMap[(int) $pid] = ($qtyMap[(int) $pid] ?? 0) + (int) $qty;
}

if ($adaItemRusak) {
    kembali_dengan_error($pdo, 'Data keranjang tidak valid. Silakan periksa kembali belanjaan.', $qtyMap, $bayarRaw);
}
if (empty($qtyMap)) {
    kembali_dengan_error($pdo, 'Keranjang masih kosong.', [], $bayarRaw);
}
if (count($qtyMap) > KERANJANG_MAKS_ITEM) {
    kembali_dengan_error($pdo, 'Jumlah jenis barang dalam satu transaksi maksimal ' . KERANJANG_MAKS_ITEM . '.', $qtyMap, $bayarRaw);
}
if (!is_valid_angka($bayarRaw, 10) || (int) $bayarRaw <= 0) {
    kembali_dengan_error($pdo, 'Masukkan uang pembayaran dalam angka bulat.', $qtyMap, '');
}

ksort($qtyMap);
$ids         = array_keys($qtyMap);
$placeholder = implode(',', array_fill(0, count($ids), '?'));
$uangBayar   = (int) $bayarRaw;

/* ---------- Proses transaksi di dalam database transaction ---------- */
try {
    $pdo->beginTransaction();

    // Kunci semua produk yang dibeli. ORDER BY id menjaga urutan kunci agar tidak deadlock.
    $stmt = $pdo->prepare(
        "SELECT id, kode_barang, nama_barang, satuan, harga_modal, harga_jual, stok, is_active
           FROM products
          WHERE id IN ($placeholder)
          ORDER BY id
            FOR UPDATE"
    );
    $stmt->execute($ids);

    $produk = [];
    foreach ($stmt->fetchAll() as $row) {
        $produk[(int) $row['id']] = $row;
    }

    $totalItem     = 0;
    $totalBelanja  = 0;
    $totalModal    = 0;
    $baris         = [];

    foreach ($qtyMap as $pid => $qty) {
        if (!isset($produk[$pid]) || (int) $produk[$pid]['is_active'] !== 1) {
            throw new RuntimeException('Ada barang yang sudah tidak tersedia. Hapus barang tersebut dari keranjang lalu coba lagi.');
        }

        $p            = $produk[$pid];
        $stokSekarang = (int) $p['stok'];

        if ($qty > $stokSekarang) {
            throw new RuntimeException(
                'Stok ' . $p['nama_barang'] . ' tidak mencukupi. Stok tersedia: ' . $stokSekarang . '.'
            );
        }

        $hargaJual  = (int) $p['harga_jual'];
        $hargaModal = (int) $p['harga_modal'];
        $subtotal   = $qty * $hargaJual;
        $modal      = $qty * $hargaModal;

        $baris[] = [
            'p'            => $p,
            'qty'          => $qty,
            'subtotal'     => $subtotal,
            'laba'         => $subtotal - $modal,
            'stok_sebelum' => $stokSekarang,
        ];

        $totalItem    += $qty;
        $totalBelanja += $subtotal;
        $totalModal   += $modal;
    }

    if ($uangBayar < $totalBelanja) {
        throw new RuntimeException(
            'Uang bayar kurang. Total belanja ' . rupiah($totalBelanja)
            . ', uang diterima ' . rupiah($uangBayar) . '.'
        );
    }

    $kembalian = $uangBayar - $totalBelanja;
    $labaKotor = $totalBelanja - $totalModal;
    $kode      = 'TRX-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
    $waktu     = date('Y-m-d H:i:s');

    $stmtTrx = $pdo->prepare(
        'INSERT INTO transactions
            (kode_transaksi, user_id, total_item, total_belanja, uang_bayar, kembalian, total_modal, laba_kotor, status, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmtTrx->execute([
        $kode, (int) $user['id'], $totalItem, $totalBelanja, $uangBayar,
        $kembalian, $totalModal, $labaKotor, 'selesai', $waktu,
    ]);
    $idTransaksi = (int) $pdo->lastInsertId();

    $stmtDetail = $pdo->prepare(
        'INSERT INTO transaction_details
            (transaction_id, product_id, nama_barang, harga_modal, harga_jual, qty, subtotal, laba_kotor)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmtStok = $pdo->prepare('UPDATE products SET stok = stok - ? WHERE id = ?');
    $stmtMv   = $pdo->prepare(
        "INSERT INTO stock_movements
            (product_id, user_id, jenis, qty, stok_sebelum, stok_sesudah, referensi, keterangan, created_at)
         VALUES (?, ?, 'penjualan', ?, ?, ?, ?, NULL, ?)"
    );

    foreach ($baris as $b) {
        $p   = $b['p'];
        $qty = $b['qty'];
        $pid = (int) $p['id'];

        // Harga disalin ke detail agar perubahan harga di masa depan tidak mengubah riwayat
        $stmtDetail->execute([
            $idTransaksi, $pid, $p['nama_barang'],
            (int) $p['harga_modal'], (int) $p['harga_jual'],
            $qty, $b['subtotal'], $b['laba'],
        ]);

        $stmtStok->execute([$qty, $pid]);

        $stmtMv->execute([
            $pid, (int) $user['id'], -$qty,
            $b['stok_sebelum'], $b['stok_sebelum'] - $qty,
            $kode, $waktu,
        ]);
    }

    $pdo->commit();

    redirect('kasir/struk.php?kode=' . urlencode($kode));
} catch (RuntimeException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    kembali_dengan_error($pdo, $e->getMessage(), $qtyMap, $bayarRaw);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[TRANSAKSI] ' . $e->getMessage());
    kembali_dengan_error(
        $pdo,
        'Transaksi gagal disimpan. Data stok tidak berubah, silakan coba lagi.',
        $qtyMap,
        $bayarRaw
    );
}