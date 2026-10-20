<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_role(ROLE_ADMIN);

$pageTitle  = 'Stok Barang';
$activeMenu = 'stok';

$pdo     = db();
$q       = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 100);
$filter  = ($_GET['status'] ?? '') === 'menipis' ? 'menipis' : 'semua';
$page    = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 15;

$where  = ['p.is_active = 1'];
$params = [];

if ($q !== '') {
    $where[]  = '(p.nama_barang LIKE ? OR p.kode_barang LIKE ?)';
    $params[] = '%' . $q . '%';
    $params[] = '%' . $q . '%';
}
if ($filter === 'menipis') {
    $where[] = 'p.stok <= p.stok_minimum';
}

$sqlDari = ' FROM products p
             LEFT JOIN categories k ON k.id = p.category_id
            WHERE ' . implode(' AND ', $where);

$stmt = $pdo->prepare('SELECT COUNT(*)' . $sqlDari);
$stmt->execute($params);
$total = (int) $stmt->fetchColumn();

$pg = paginate_info($total, $perPage, $page);

$stmt = $pdo->prepare(
    'SELECT p.id, p.kode_barang, p.nama_barang, p.satuan, p.stok, p.stok_minimum, k.nama_kategori'
    . $sqlDari
    . ' ORDER BY p.stok ASC, p.nama_barang ASC LIMIT ' . $pg['per_page'] . ' OFFSET ' . $pg['offset']
);
$stmt->execute($params);
$daftarStok = $stmt->fetchAll();

$jumlahMenipis = (int) $pdo->query(
    'SELECT COUNT(*) FROM products WHERE is_active = 1 AND stok <= stok_minimum'
)->fetchColumn();

$produkAktif = $pdo->query(
    'SELECT id, kode_barang, nama_barang, stok FROM products WHERE is_active = 1 ORDER BY nama_barang ASC'
)->fetchAll();

$riwayat = $pdo->query(
    'SELECT m.jenis, m.qty, m.stok_sebelum, m.stok_sesudah, m.referensi, m.keterangan, m.created_at,
            p.nama_barang, u.nama_lengkap
       FROM stock_movements m
       JOIN products p ON p.id = m.product_id
       JOIN users u ON u.id = m.user_id
      ORDER BY m.created_at DESC, m.id DESC
      LIMIT 25'
)->fetchAll();

$jenisLabel = [
    'masuk'      => 'Barang masuk',
    'penjualan'  => 'Penjualan',
    'koreksi'    => 'Koreksi',
    'pembatalan' => 'Pembatalan',
];

$queryFilter = array_filter([
    'q'      => $q,
    'status' => $filter === 'menipis' ? 'menipis' : '',
], fn ($v) => $v !== '');

require __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <div>
        <h2>Stok Barang</h2>
        <p class="muted">Catat barang masuk dan pantau stok yang hampir habis.</p>
    </div>
</div>

<?php if ($jumlahMenipis > 0): ?>
    <div class="alert alert-warning">
        Ada <strong><?= $jumlahMenipis ?> produk</strong> dengan stok sama dengan atau di bawah batas minimum.
        Segera lakukan pembelian barang.
    </div>
<?php endif; ?>

<div class="grid-split">
    <section class="card">
        <h2>Catat Barang Masuk</h2>
        <p class="muted">Stok bertambah setelah formulir ini disimpan dan tercatat di riwayat.</p>

        <?php if (empty($produkAktif)): ?>
            <div class="empty-state">Belum ada produk aktif. Tambahkan produk di menu Data Barang terlebih dahulu.</div>
        <?php else: ?>
            <form method="post" action="<?= e(url('admin/stok/barang_masuk.php')) ?>" style="margin-top:14px;">
                <?= csrf_field() ?>

                <div class="form-group">
                    <label for="product_id">Barang</label>
                    <select id="product_id" name="product_id" required>
                        <option value="" disabled selected>-- Pilih barang --</option>
                        <?php foreach ($produkAktif as $p): ?>
                            <option value="<?= (int) $p['id'] ?>">
                                <?= e($p['kode_barang'] . ' - ' . $p['nama_barang'] . ' (stok ' . (int) $p['stok'] . ')') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="qty">Jumlah Masuk</label>
                    <input type="number" id="qty" name="qty" min="1" max="999999" step="1" required placeholder="contoh: 10">
                </div>

                <div class="form-group">
                    <label for="keterangan">Keterangan (opsional)</label>
                    <input type="text" id="keterangan" name="keterangan" maxlength="255"
                           placeholder="contoh: Faktur supplier 0123">
                </div>

                <button type="submit" class="btn btn-primary btn-block">Simpan Barang Masuk</button>
            </form>
        <?php endif; ?>
    </section>

    <section class="card">
        <div class="card-header">
            <h2>Ringkasan Stok</h2>
            <span class="muted"><?= $total ?> produk</span>
        </div>

        <form method="get" action="<?= e(url('admin/stok/index.php')) ?>" class="filter-bar" style="margin-bottom:14px;">
            <div>
                <label for="q">Cari</label>
                <input type="text" id="q" name="q" maxlength="100" value="<?= e($q) ?>"
                       placeholder="Nama atau kode barang">
            </div>
            <div>
                <label for="status">Tampilkan</label>
                <select id="status" name="status">
                    <option value="semua" <?= $filter === 'semua' ? 'selected' : '' ?>>Semua produk</option>
                    <option value="menipis" <?= $filter === 'menipis' ? 'selected' : '' ?>>Stok menipis / habis</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">Terapkan</button>
        </form>

        <?php if (empty($daftarStok)): ?>
            <div class="empty-state">Tidak ada produk yang cocok dengan filter.</div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Kode</th>
                            <th>Nama Barang</th>
                            <th class="num">Stok</th>
                            <th class="num">Min.</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($daftarStok as $s): ?>
                            <tr>
                                <td><code><?= e($s['kode_barang']) ?></code></td>
                                <td>
                                    <strong><?= e($s['nama_barang']) ?></strong>
                                    <div class="hint"><?= e($s['nama_kategori'] ?? 'Tanpa kategori') ?> · <?= e($s['satuan']) ?></div>
                                </td>
                                <td class="num"><span class="stok-angka"><?= (int) $s['stok'] ?></span></td>
                                <td class="num"><?= (int) $s['stok_minimum'] ?></td>
                                <td><?= badge_stok((int) $s['stok'], (int) $s['stok_minimum']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php render_pagination('admin/stok/index.php', $queryFilter, $pg); ?>
        <?php endif; ?>
    </section>
</div>

<section class="card">
    <div class="card-header">
        <h2>Riwayat Mutasi Stok</h2>
        <span class="muted">25 mutasi terakhir</span>
    </div>

    <?php if (empty($riwayat)): ?>
        <div class="empty-state">Belum ada riwayat stok.</div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Waktu</th>
                        <th>Barang</th>
                        <th>Jenis</th>
                        <th class="num">Qty</th>
                        <th class="num">Stok</th>
                        <th>Referensi / Keterangan</th>
                        <th>Oleh</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($riwayat as $m): ?>
                        <tr>
                            <td><?= e(tanggal_indo($m['created_at'])) ?></td>
                            <td><?= e($m['nama_barang']) ?></td>
                            <td><?= e($jenisLabel[$m['jenis']] ?? $m['jenis']) ?></td>
                            <td class="num"><?= ((int) $m['qty'] > 0 ? '+' : '') . (int) $m['qty'] ?></td>
                            <td class="num"><?= (int) $m['stok_sebelum'] ?> → <?= (int) $m['stok_sesudah'] ?></td>
                            <td>
                                <?php if (!empty($m['referensi'])): ?>
                                    <code><?= e($m['referensi']) ?></code>
                                <?php endif; ?>
                                <?= e($m['keterangan'] ?? '') ?>
                            </td>
                            <td><?= e($m['nama_lengkap']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../../includes/footer.php'; ?>