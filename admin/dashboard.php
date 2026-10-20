<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_role(ROLE_ADMIN);

$pageTitle  = 'Dashboard';
$activeMenu = 'dashboard';

$pdo = db();

// Batas waktu dihitung di PHP (zona waktu Asia/Jakarta) agar konsisten dengan tampilan
$sekarang       = new DateTimeImmutable('now');
$awalHari       = $sekarang->format('Y-m-d 00:00:00');
$awalBesok      = $sekarang->modify('+1 day')->format('Y-m-d 00:00:00');
$awalBulan      = $sekarang->format('Y-m-01 00:00:00');
$awalBulanDepan = $sekarang->modify('first day of next month')->format('Y-m-d 00:00:00');

/**
 * Omzet dan jumlah transaksi berhasil (status 'selesai') dalam rentang waktu.
 */
function ringkasan_transaksi(PDO $pdo, string $awal, string $akhir): array
{
    $stmt = $pdo->prepare(
        "SELECT COALESCE(SUM(total_belanja), 0) AS omzet, COUNT(*) AS jumlah
           FROM transactions
          WHERE status = 'selesai'
            AND created_at >= ?
            AND created_at < ?"
    );
    $stmt->execute([$awal, $akhir]);
    return $stmt->fetch();
}

$hari  = ringkasan_transaksi($pdo, $awalHari, $awalBesok);
$bulan = ringkasan_transaksi($pdo, $awalBulan, $awalBulanDepan);

$jumlahProdukAktif = (int) $pdo->query(
    'SELECT COUNT(*) FROM products WHERE is_active = 1'
)->fetchColumn();

$jumlahStokMenipis = (int) $pdo->query(
    'SELECT COUNT(*) FROM products WHERE is_active = 1 AND stok <= stok_minimum'
)->fetchColumn();

$stokMenipis = $pdo->query(
    'SELECT id, kode_barang, nama_barang, stok, stok_minimum
       FROM products
      WHERE is_active = 1 AND stok <= stok_minimum
      ORDER BY stok ASC, nama_barang ASC
      LIMIT 8'
)->fetchAll();

$transaksiTerakhir = $pdo->query(
    'SELECT t.id, t.kode_transaksi, t.total_belanja, t.status, t.created_at, u.nama_lengkap
       FROM transactions t
       JOIN users u ON u.id = t.user_id
      ORDER BY t.created_at DESC, t.id DESC
      LIMIT 5'
)->fetchAll();

require __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h2>Ringkasan Toko</h2>
        <p class="muted">Data per <?= e(tanggal_indo($sekarang->format('Y-m-d H:i:s'))) ?></p>
    </div>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-label">Omzet Hari Ini</div>
        <div class="stat-value"><?= e(rupiah($hari['omzet'])) ?></div>
        <div class="stat-hint">Dari <?= (int) $hari['jumlah'] ?> transaksi berhasil</div>
    </div>

    <div class="stat-card info">
        <div class="stat-label">Transaksi Hari Ini</div>
        <div class="stat-value"><?= (int) $hari['jumlah'] ?></div>
        <div class="stat-hint">Transaksi berstatus selesai</div>
    </div>

    <div class="stat-card info">
        <div class="stat-label">Omzet Bulan Ini</div>
        <div class="stat-value"><?= e(rupiah($bulan['omzet'])) ?></div>
        <div class="stat-hint">Dari <?= (int) $bulan['jumlah'] ?> transaksi berhasil</div>
    </div>

    <div class="stat-card <?= $jumlahStokMenipis > 0 ? 'warn' : '' ?>">
        <div class="stat-label">Stok Hampir Habis</div>
        <div class="stat-value"><?= $jumlahStokMenipis ?> produk</div>
        <div class="stat-hint">Dari <?= $jumlahProdukAktif ?> produk aktif</div>
    </div>
</div>

<div class="grid-2">
    <section class="card">
        <div class="card-header">
            <h2>Stok Hampir Habis</h2>
            <a class="btn btn-secondary btn-sm" href="<?= e(url('admin/produk/index.php?status=menipis')) ?>">Lihat semua</a>
        </div>

        <?php if (empty($stokMenipis)): ?>
            <div class="empty-state">Semua stok produk aktif masih aman.</div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Kode</th>
                            <th>Nama Barang</th>
                            <th class="num">Stok</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($stokMenipis as $p): ?>
                            <tr>
                                <td><code><?= e($p['kode_barang']) ?></code></td>
                                <td><?= e($p['nama_barang']) ?></td>
                                <td class="num">
                                    <?= (int) $p['stok'] ?>
                                    <div class="hint">Min. <?= (int) $p['stok_minimum'] ?></div>
                                </td>
                                <td><?= badge_stok((int) $p['stok'], (int) $p['stok_minimum']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <section class="card">
        <div class="card-header">
            <h2>Transaksi Terakhir</h2>
        </div>

        <?php if (empty($transaksiTerakhir)): ?>
            <div class="empty-state">Belum ada transaksi. Data akan muncul setelah kasir menyelesaikan transaksi.</div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Kode</th>
                            <th>Waktu</th>
                            <th>Kasir</th>
                            <th class="num">Total</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transaksiTerakhir as $t): ?>
                            <tr>
                                <td><code><?= e($t['kode_transaksi']) ?></code></td>
                                <td><?= e(tanggal_indo($t['created_at'])) ?></td>
                                <td><?= e($t['nama_lengkap']) ?></td>
                                <td class="num"><?= e(rupiah($t['total_belanja'])) ?></td>
                                <td><span class="badge badge-<?= e($t['status']) ?>"><?= e(ucfirst($t['status'])) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>