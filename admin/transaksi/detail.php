<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_role(ROLE_ADMIN);

$pdo = db();
$id  = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare(
    'SELECT t.*, u.nama_lengkap AS nama_kasir, b.nama_lengkap AS nama_pembatal
       FROM transactions t
       JOIN users u ON u.id = t.user_id
       LEFT JOIN users b ON b.id = t.batal_oleh
      WHERE t.id = ?
      LIMIT 1'
);
$stmt->execute([$id]);
$trx = $stmt->fetch();

if (!$trx) {
    set_flash('error', 'Transaksi tidak ditemukan.');
    redirect('admin/transaksi/index.php');
}

$stmt = $pdo->prepare(
    'SELECT nama_barang, harga_modal, harga_jual, qty, subtotal, laba_kotor
       FROM transaction_details
      WHERE transaction_id = ?
      ORDER BY id ASC'
);
$stmt->execute([$id]);
$items = $stmt->fetchAll();

$pageTitle  = 'Detail Transaksi';
$activeMenu = 'transaksi';

require __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <div>
        <h2>Transaksi <?= e($trx['kode_transaksi']) ?></h2>
        <p class="muted"><?= e(tanggal_indo($trx['created_at'])) ?></p>
    </div>
    <div class="form-actions">
        <a class="btn btn-secondary" href="<?= e(url('kasir/struk.php?kode=' . urlencode($trx['kode_transaksi']))) ?>">Lihat Struk</a>
        <a class="btn btn-secondary" href="<?= e(url('admin/transaksi/index.php')) ?>">← Kembali ke riwayat</a>
    </div>
</div>

<div class="grid-2">
    <section class="card">
        <h2>Informasi Transaksi</h2>
        <dl class="info-list">
            <dt>Status</dt>
            <dd><?= badge_status_transaksi($trx['status']) ?></dd>
            <dt>Kasir</dt>
            <dd><?= e($trx['nama_kasir']) ?></dd>
            <dt>Jumlah item</dt>
            <dd><?= (int) $trx['total_item'] ?></dd>
            <dt>Total belanja</dt>
            <dd><?= e(rupiah($trx['total_belanja'])) ?></dd>
            <dt>Uang diterima</dt>
            <dd><?= e(rupiah($trx['uang_bayar'])) ?></dd>
            <dt>Kembalian</dt>
            <dd><?= e(rupiah($trx['kembalian'])) ?></dd>
            <dt>Total modal</dt>
            <dd><?= e(rupiah($trx['total_modal'])) ?></dd>
            <dt>Laba kotor</dt>
            <dd><?= e(rupiah($trx['laba_kotor'])) ?></dd>
        </dl>
    </section>

    <section class="card">
        <h2>Pembatalan</h2>
        <?php if ($trx['status'] === 'batal'): ?>
            <dl class="info-list">
                <dt>Dibatalkan oleh</dt>
                <dd><?= e($trx['nama_pembatal'] ?? '-') ?></dd>
                <dt>Waktu</dt>
                <dd><?= e($trx['batal_at'] ? tanggal_indo($trx['batal_at']) : '-') ?></dd>
                <dt>Alasan</dt>
                <dd><?= e($trx['alasan_batal'] ?? '-') ?></dd>
            </dl>
            <p class="hint" style="margin-top:12px;">Stok barang sudah dikembalikan saat transaksi dibatalkan.</p>
        <?php else: ?>
            <p class="muted">Transaksi ini belum dibatalkan.</p>
            <form method="post" action="<?= e(url('admin/transaksi/batal.php')) ?>" style="margin-top:12px;">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int) $trx['id'] ?>">

                <div class="form-group">
                    <label for="alasan">Alasan pembatalan</label>
                    <textarea id="alasan" name="alasan" required minlength="5" maxlength="255"
                              placeholder="contoh: Salah input barang oleh kasir"></textarea>
                    <div class="hint">Minimal 5 karakter. Stok setiap barang akan dikembalikan.</div>
                </div>

                <button type="submit" class="btn btn-danger"
                        data-confirm="Yakin membatalkan transaksi ini? Stok barang akan dikembalikan.">
                    Batalkan Transaksi
                </button>
            </form>
        <?php endif; ?>
    </section>
</div>

<section class="card">
    <div class="card-header">
        <h2>Rincian Barang</h2>
        <span class="muted"><?= count($items) ?> jenis barang</span>
    </div>

    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>Nama Barang</th>
                    <th class="num">Qty</th>
                    <th class="num">Harga Jual</th>
                    <th class="num">Harga Modal</th>
                    <th class="num">Subtotal</th>
                    <th class="num">Laba Kotor</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $it): ?>
                    <tr>
                        <td><?= e($it['nama_barang']) ?></td>
                        <td class="num"><?= (int) $it['qty'] ?></td>
                        <td class="num"><?= e(rupiah($it['harga_jual'])) ?></td>
                        <td class="num"><?= e(rupiah($it['harga_modal'])) ?></td>
                        <td class="num"><?= e(rupiah($it['subtotal'])) ?></td>
                        <td class="num"><?= e(rupiah($it['laba_kotor'])) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <p class="hint" style="margin-top:10px;">Harga di atas adalah salinan saat transaksi terjadi, sehingga tidak berubah meskipun harga produk diubah kemudian.</p>
</section>

<?php require __DIR__ . '/../../includes/footer.php'; ?>