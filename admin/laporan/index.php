<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_role(ROLE_ADMIN);

$pageTitle  = 'Laporan Penjualan';
$activeMenu = 'laporan';

$pdo = db();

$awal  = parse_tanggal(input_string($_GET, 'tanggal_awal', 10)) ?? date('Y-m-01');
$akhir = parse_tanggal(input_string($_GET, 'tanggal_akhir', 10)) ?? date('Y-m-d');
if ($awal > $akhir) {
    [$awal, $akhir] = [$akhir, $awal];
}

$rentang = [
    $awal . ' 00:00:00',
    (new DateTimeImmutable($akhir))->modify('+1 day')->format('Y-m-d 00:00:00'),
];

/* ---------- Ringkasan transaksi berhasil ---------- */
$stmt = $pdo->prepare(
    "SELECT COUNT(*) AS jumlah,
            COALESCE(SUM(total_item), 0)     AS item,
            COALESCE(SUM(total_belanja), 0)  AS omzet,
            COALESCE(SUM(total_modal), 0)    AS modal,
            COALESCE(SUM(laba_kotor), 0)     AS laba
       FROM transactions
      WHERE status = 'selesai' AND created_at >= ? AND created_at < ?"
);
$stmt->execute($rentang);
$ringkas = $stmt->fetch();

$stmt = $pdo->prepare(
    "SELECT COUNT(*) FROM transactions
      WHERE status = 'batal' AND created_at >= ? AND created_at < ?"
);
$stmt->execute($rentang);
$jumlahBatal = (int) $stmt->fetchColumn();

/* ---------- Produk terlaris (10 teratas) ---------- */
$stmt = $pdo->prepare(
    "SELECT d.product_id,
            MAX(d.nama_barang)  AS nama_barang,
            SUM(d.qty)          AS qty,
            SUM(d.subtotal)     AS omzet,
            SUM(d.laba_kotor)   AS laba
       FROM transaction_details d
       JOIN transactions t ON t.id = d.transaction_id
      WHERE t.status = 'selesai' AND t.created_at >= ? AND t.created_at < ?
      GROUP BY d.product_id
      ORDER BY qty DESC, omzet DESC
      LIMIT 10"
);
$stmt->execute($rentang);
$terlaris = $stmt->fetchAll();

/* ---------- Rekap harian ---------- */
$stmt = $pdo->prepare(
    "SELECT DATE(created_at) AS tanggal,
            COUNT(*)            AS jumlah,
            SUM(total_belanja)  AS omzet,
            SUM(laba_kotor)     AS laba
       FROM transactions
      WHERE status = 'selesai' AND created_at >= ? AND created_at < ?
      GROUP BY DATE(created_at)
      ORDER BY tanggal ASC"
);
$stmt->execute($rentang);
$harian = $stmt->fetchAll();

$namaToko = $pdo->query('SELECT nama_toko FROM store_settings WHERE id = 1')->fetchColumn() ?: APP_NAME;

$jumlah   = (int) $ringkas['jumlah'];
$omzet    = (float) $ringkas['omzet'];
$laba     = (float) $ringkas['laba'];
$rataRata = $jumlah > 0 ? $omzet / $jumlah : 0;
$margin   = $omzet > 0 ? round($laba / $omzet * 100, 1) : 0;

$hariIni = date('Y-m-d');
$presets = [
    'Hari ini'        => [$hariIni, $hariIni],
    '7 hari terakhir' => [date('Y-m-d', strtotime('-6 days')), $hariIni],
    'Bulan ini'       => [date('Y-m-01'), $hariIni],
    'Bulan lalu'      => [
        date('Y-m-01', strtotime('first day of last month')),
        date('Y-m-t', strtotime('first day of last month')),
    ],
];

require __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <div>
        <h2>Laporan Penjualan</h2>
        <p class="muted">Periode <?= e(tanggal_indo_saja($awal)) ?> s.d. <?= e(tanggal_indo_saja($akhir)) ?></p>
    </div>
    <button type="button" class="btn btn-primary no-print" id="btnCetakLaporan">Cetak / Simpan PDF</button>
</div>

<div class="no-print">
    <div class="preset-links">
        <?php foreach ($presets as $label => [$a, $b]): ?>
            <a href="<?= e(url('admin/laporan/index.php?tanggal_awal=' . $a . '&tanggal_akhir=' . $b)) ?>"><?= e($label) ?></a>
        <?php endforeach; ?>
    </div>

    <form method="get" action="<?= e(url('admin/laporan/index.php')) ?>" class="card filter-bar">
        <div>
            <label for="tanggal_awal">Dari tanggal</label>
            <input type="date" id="tanggal_awal" name="tanggal_awal" value="<?= e($awal) ?>" required>
        </div>
        <div>
            <label for="tanggal_akhir">Sampai tanggal</label>
            <input type="date" id="tanggal_akhir" name="tanggal_akhir" value="<?= e($akhir) ?>" required>
        </div>
        <button type="submit" class="btn btn-primary">Tampilkan</button>
    </form>
</div>

<!-- Judul ini hanya tampil saat dicetak -->
<div class="print-only report-head">
    <h2><?= e($namaToko) ?></h2>
    <p><strong>Laporan Penjualan</strong></p>
    <p>Periode <?= e(tanggal_indo_saja($awal)) ?> s.d. <?= e(tanggal_indo_saja($akhir)) ?></p>
    <p>Dicetak <?= e(tanggal_indo(date('Y-m-d H:i:s'))) ?></p>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-label">Omzet</div>
        <div class="stat-value"><?= e(rupiah($omzet)) ?></div>
        <div class="stat-hint"><?= $jumlah ?> transaksi berhasil</div>
    </div>
    <div class="stat-card info">
        <div class="stat-label">Perkiraan Laba Kotor</div>
        <div class="stat-value"><?= e(rupiah($laba)) ?></div>
        <div class="stat-hint">Margin <?= e((string) $margin) ?>%</div>
    </div>
    <div class="stat-card info">
        <div class="stat-label">Barang Terjual</div>
        <div class="stat-value"><?= (int) $ringkas['item'] ?></div>
        <div class="stat-hint">Rata-rata <?= e(rupiah($rataRata)) ?> per transaksi</div>
    </div>
    <div class="stat-card <?= $jumlahBatal > 0 ? 'warn' : '' ?>">
        <div class="stat-label">Transaksi Dibatalkan</div>
        <div class="stat-value"><?= $jumlahBatal ?></div>
        <div class="stat-hint">Tidak dihitung dalam omzet</div>
    </div>
</div>

<div class="grid-2">
    <section class="card">
        <div class="card-header">
            <h2>Produk Terlaris</h2>
            <span class="muted">10 teratas</span>
        </div>

        <?php if (empty($terlaris)): ?>
            <div class="empty-state">Belum ada penjualan pada periode ini.</div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Nama Barang</th>
                            <th class="num">Qty</th>
                            <th class="num">Omzet</th>
                            <th class="num">Laba Kotor</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $no = 1; foreach ($terlaris as $p): ?>
                            <tr>
                                <td><?= $no++ ?></td>
                                <td><?= e($p['nama_barang']) ?></td>
                                <td class="num"><?= (int) $p['qty'] ?></td>
                                <td class="num"><?= e(rupiah($p['omzet'])) ?></td>
                                <td class="num"><?= e(rupiah($p['laba'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <section class="card">
        <div class="card-header">
            <h2>Rekap Harian</h2>
            <span class="muted"><?= count($harian) ?> hari</span>
        </div>

        <?php if (empty($harian)): ?>
            <div class="empty-state">Belum ada penjualan pada periode ini.</div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th class="num">Transaksi</th>
                            <th class="num">Omzet</th>
                            <th class="num">Laba Kotor</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($harian as $h): ?>
                            <tr>
                                <td><?= e(tanggal_indo_saja($h['tanggal'])) ?></td>
                                <td class="num"><?= (int) $h['jumlah'] ?></td>
                                <td class="num"><?= e(rupiah($h['omzet'])) ?></td>
                                <td class="num"><?= e(rupiah($h['laba'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <th>Total</th>
                            <th class="num"><?= $jumlah ?></th>
                            <th class="num"><?= e(rupiah($omzet)) ?></th>
                            <th class="num"><?= e(rupiah($laba)) ?></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        <?php endif; ?>
    </section>
</div>

<p class="hint no-print" style="margin-top:12px;">
    Untuk menyimpan sebagai PDF, klik "Cetak / Simpan PDF" lalu pilih "Save as PDF" pada tujuan cetak.
    Pada opsi lanjutan, matikan "Headers and footers" agar tanggal dan judul browser tidak ikut tercetak.
</p>

<script>
    document.getElementById('btnCetakLaporan').addEventListener('click', function () {
        window.print();
    });
</script>

<?php require __DIR__ . '/../../includes/footer.php'; ?>