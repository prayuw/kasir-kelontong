<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_role(ROLE_KASIR);

$pageTitle  = 'Riwayat Saya';
$activeMenu = 'riwayat';

$pdo     = db();
$user    = current_user();
$hariIni = date('Y-m-d');

$tglAwal  = parse_tanggal(input_string($_GET, 'tanggal_awal', 10)) ?? $hariIni;
$tglAkhir = parse_tanggal(input_string($_GET, 'tanggal_akhir', 10)) ?? $hariIni;
if ($tglAwal > $tglAkhir) {
    [$tglAwal, $tglAkhir] = [$tglAkhir, $tglAwal];
}

$status  = input_string($_GET, 'status', 10);
$status  = in_array($status, ['selesai', 'batal'], true) ? $status : '';
$page    = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 15;

$where  = ['t.user_id = ?', 't.created_at >= ?', 't.created_at < ?'];
$params = [
    (int) $user['id'],
    $tglAwal . ' 00:00:00',
    (new DateTimeImmutable($tglAkhir))->modify('+1 day')->format('Y-m-d 00:00:00'),
];
if ($status !== '') {
    $where[]  = 't.status = ?';
    $params[] = $status;
}

$sqlDari = ' FROM transactions t WHERE ' . implode(' AND ', $where);

$stmt = $pdo->prepare('SELECT COUNT(*)' . $sqlDari);
$stmt->execute($params);
$total = (int) $stmt->fetchColumn();

$pg = paginate_info($total, $perPage, $page);

$stmt = $pdo->prepare(
    'SELECT t.id, t.kode_transaksi, t.total_item, t.total_belanja, t.status, t.created_at'
    . $sqlDari
    . ' ORDER BY t.created_at DESC, t.id DESC LIMIT ' . $pg['per_page'] . ' OFFSET ' . $pg['offset']
);
$stmt->execute($params);
$daftar = $stmt->fetchAll();

$stmt = $pdo->prepare(
    "SELECT COUNT(*) AS jumlah, COALESCE(SUM(t.total_belanja), 0) AS omzet"
    . $sqlDari . " AND t.status = 'selesai'"
);
$stmt->execute($params);
$ringkas = $stmt->fetch();

$queryFilter = array_filter([
    'tanggal_awal'  => $tglAwal,
    'tanggal_akhir' => $tglAkhir,
    'status'        => $status,
], fn ($v) => $v !== '');

/**
 * Mengambil dan membersihkan string dari input $_GET / $_POST.
 */
// function input_string(array $source, string $key, int $maxLength = 255): string {
//     $value = $source[$key] ?? '';
//     if (!is_string($value)) {
//         return '';
//     }
//     return mb_substr(trim($value), 0, $maxLength);
// }

/**
 * Memvalidasi format tanggal YYYY-MM-DD.
 */
// function parse_tanggal(string $tanggal): ?string {
//     $d = DateTimeImmutable::createFromFormat('Y-m-d', $tanggal);
//     return ($d && $d->format('Y-m-d') === $tanggal) ? $tanggal : null;
// }

require __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h2>Riwayat Transaksi Saya</h2>
        <p class="muted">Hanya menampilkan transaksi yang Anda layani.</p>
    </div>
    <a class="btn btn-primary" href="<?= e(url('kasir/transaksi.php')) ?>">+ Transaksi Baru</a>
</div>

<form method="get" action="<?= e(url('kasir/riwayat.php')) ?>" class="card filter-bar">
    <div>
        <label for="tanggal_awal">Dari tanggal</label>
        <input type="date" id="tanggal_awal" name="tanggal_awal" value="<?= e($tglAwal) ?>">
    </div>
    <div>
        <label for="tanggal_akhir">Sampai tanggal</label>
        <input type="date" id="tanggal_akhir" name="tanggal_akhir" value="<?= e($tglAkhir) ?>">
    </div>
    <div>
        <label for="status">Status</label>
        <select id="status" name="status">
            <option value="">Semua status</option>
            <option value="selesai" <?= $status === 'selesai' ? 'selected' : '' ?>>Selesai</option>
            <option value="batal" <?= $status === 'batal' ? 'selected' : '' ?>>Dibatalkan</option>
        </select>
    </div>
    <button type="submit" class="btn btn-primary">Terapkan</button>
</form>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-label">Transaksi Selesai</div>
        <div class="stat-value"><?= (int) $ringkas['jumlah'] ?></div>
        <div class="stat-hint">Pada periode yang dipilih</div>
    </div>
    <div class="stat-card info">
        <div class="stat-label">Total Penjualan Saya</div>
        <div class="stat-value"><?= e(rupiah($ringkas['omzet'])) ?></div>
        <div class="stat-hint">Tidak termasuk transaksi dibatalkan</div>
    </div>
</div>

<section class="card">
    <?php if (empty($daftar)): ?>
        <div class="empty-state">Belum ada transaksi pada periode ini.</div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Kode</th>
                        <th>Waktu</th>
                        <th class="num">Item</th>
                        <th class="num">Total</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($daftar as $t): ?>
                        <tr class="<?= $t['status'] === 'batal' ? 'row-batal' : '' ?>">
                            <td><code><?= e($t['kode_transaksi']) ?></code></td>
                            <td><?= e(tanggal_indo($t['created_at'])) ?></td>
                            <td class="num"><?= (int) $t['total_item'] ?></td>
                            <td class="num"><?= e(rupiah($t['total_belanja'])) ?></td>
                            <td><?= badge_status_transaksi($t['status']) ?></td>
                            <td>
                                <a class="btn btn-secondary btn-sm"
                                   href="<?= e(url('kasir/struk.php?kode=' . urlencode($t['kode_transaksi']))) ?>">Struk</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php render_pagination('kasir/riwayat.php', $queryFilter, $pg); ?>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>