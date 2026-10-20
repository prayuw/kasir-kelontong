<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_role(ROLE_ADMIN);

$pageTitle  = 'Riwayat Transaksi';
$activeMenu = 'transaksi';

$pdo     = db();
$q       = input_string($_GET, 'q', 30);
$tglAwal = parse_tanggal(input_string($_GET, 'tanggal_awal', 10)) ?? '';
$tglAkhir = parse_tanggal(input_string($_GET, 'tanggal_akhir', 10)) ?? '';
$status  = input_string($_GET, 'status', 10);
$status  = in_array($status, ['selesai', 'batal'], true) ? $status : '';
$kasirId = (int) ($_GET['kasir'] ?? 0);
$page    = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 15;

// Jika tanggal terbalik, dibalik agar tetap menghasilkan rentang yang benar
if ($tglAwal !== '' && $tglAkhir !== '' && $tglAwal > $tglAkhir) {
    [$tglAwal, $tglAkhir] = [$tglAkhir, $tglAwal];
}

$where  = ['1 = 1'];
$params = [];

if ($q !== '') {
    $where[]  = 't.kode_transaksi LIKE ?';
    $params[] = '%' . $q . '%';
}
if ($tglAwal !== '') {
    $where[]  = 't.created_at >= ?';
    $params[] = $tglAwal . ' 00:00:00';
}
if ($tglAkhir !== '') {
    $where[]  = 't.created_at < ?';
    $params[] = (new DateTimeImmutable($tglAkhir))->modify('+1 day')->format('Y-m-d 00:00:00');
}
if ($status !== '') {
    $where[]  = 't.status = ?';
    $params[] = $status;
}
if ($kasirId > 0) {
    $where[]  = 't.user_id = ?';
    $params[] = $kasirId;
}

$sqlDari = ' FROM transactions t
             JOIN users u ON u.id = t.user_id
            WHERE ' . implode(' AND ', $where);

$stmt = $pdo->prepare('SELECT COUNT(*)' . $sqlDari);
$stmt->execute($params);
$total = (int) $stmt->fetchColumn();

$pg = paginate_info($total, $perPage, $page);

// Ringkasan untuk seluruh hasil filter (bukan hanya halaman ini)
$stmt = $pdo->prepare(
    "SELECT COALESCE(SUM(CASE WHEN t.status = 'selesai' THEN t.total_belanja END), 0) AS omzet,
            SUM(t.status = 'selesai') AS jumlah_selesai
       " . $sqlDari
);
$stmt->execute($params);
$ringkas = $stmt->fetch();

$stmt = $pdo->prepare(
    'SELECT t.id, t.kode_transaksi, t.total_item, t.total_belanja, t.laba_kotor, t.status,
            t.created_at, u.nama_lengkap'
    . $sqlDari
    . ' ORDER BY t.created_at DESC, t.id DESC LIMIT ' . $pg['per_page'] . ' OFFSET ' . $pg['offset']
);
$stmt->execute($params);
$daftar = $stmt->fetchAll();

$daftarKasir = $pdo->query('SELECT id, nama_lengkap FROM users ORDER BY nama_lengkap ASC')->fetchAll();

$queryFilter = array_filter([
    'q'            => $q,
    'tanggal_awal' => $tglAwal,
    'tanggal_akhir' => $tglAkhir,
    'status'       => $status,
    'kasir'        => $kasirId > 0 ? $kasirId : '',
], fn ($v) => $v !== '');

require __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <div>
        <h2>Riwayat Transaksi</h2>
        <p class="muted">Semua transaksi dari seluruh kasir. Transaksi yang dibatalkan tetap tercatat.</p>
    </div>
</div>

<form method="get" action="<?= e(url('admin/transaksi/index.php')) ?>" class="card filter-bar">
    <div>
        <label for="q">Kode transaksi</label>
        <input type="text" id="q" name="q" maxlength="30" value="<?= e($q) ?>" placeholder="TRX-...">
    </div>
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
    <div>
        <label for="kasir">Kasir</label>
        <select id="kasir" name="kasir">
            <option value="">Semua pengguna</option>
            <?php foreach ($daftarKasir as $k): ?>
                <option value="<?= (int) $k['id'] ?>" <?= $kasirId === (int) $k['id'] ? 'selected' : '' ?>>
                    <?= e($k['nama_lengkap']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <button type="submit" class="btn btn-primary">Terapkan</button>
    <a class="btn btn-secondary" href="<?= e(url('admin/transaksi/index.php')) ?>">Reset</a>
</form>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-label">Transaksi Ditemukan</div>
        <div class="stat-value"><?= $total ?></div>
        <div class="stat-hint"><?= (int) $ringkas['jumlah_selesai'] ?> berstatus selesai</div>
    </div>
    <div class="stat-card info">
        <div class="stat-label">Omzet Sesuai Filter</div>
        <div class="stat-value"><?= e(rupiah($ringkas['omzet'])) ?></div>
        <div class="stat-hint">Tidak termasuk transaksi dibatalkan</div>
    </div>
</div>

<section class="card">
    <?php if (empty($daftar)): ?>
        <div class="empty-state">Tidak ada transaksi yang cocok dengan filter.</div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Kode</th>
                        <th>Waktu</th>
                        <th>Kasir</th>
                        <th class="num">Item</th>
                        <th class="num">Total</th>
                        <th class="num">Laba Kotor</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($daftar as $t): ?>
                        <tr class="<?= $t['status'] === 'batal' ? 'row-batal' : '' ?>">
                            <td><code><?= e($t['kode_transaksi']) ?></code></td>
                            <td><?= e(tanggal_indo($t['created_at'])) ?></td>
                            <td><?= e($t['nama_lengkap']) ?></td>
                            <td class="num"><?= (int) $t['total_item'] ?></td>
                            <td class="num"><?= e(rupiah($t['total_belanja'])) ?></td>
                            <td class="num"><?= $t['status'] === 'selesai' ? e(rupiah($t['laba_kotor'])) : '-' ?></td>
                            <td><?= badge_status_transaksi($t['status']) ?></td>
                            <td class="aksi">
                                <a class="btn btn-secondary btn-sm"
                                   href="<?= e(url('admin/transaksi/detail.php?id=' . (int) $t['id'])) ?>">Detail</a>
                                <a class="btn btn-secondary btn-sm"
                                   href="<?= e(url('kasir/struk.php?kode=' . urlencode($t['kode_transaksi']))) ?>">Struk</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php render_pagination('admin/transaksi/index.php', $queryFilter, $pg); ?>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../../includes/footer.php'; ?>