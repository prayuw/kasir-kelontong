<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_role(ROLE_ADMIN);

$pageTitle  = 'Data Barang';
$activeMenu = 'produk';

$pdo         = db();
$statusValid = ['', 'aktif', 'nonaktif', 'menipis'];
$perPage     = 10;

/* ---------- Aksi aktif/nonaktif (POST) ---------- */
if (is_post()) {
    if (!csrf_valid()) {
        set_flash('error', 'Sesi formulir tidak valid. Silakan coba lagi.');
        redirect('admin/produk/index.php');
    }

    $id = (int) ($_POST['id'] ?? 0);

    if (($_POST['action'] ?? '') === 'toggle' && $id > 0) {
        $stmt = $pdo->prepare('SELECT nama_barang, is_active FROM products WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $produk = $stmt->fetch();

        if (!$produk) {
            set_flash('error', 'Produk tidak ditemukan.');
        } else {
            $aktifBaru = (int) $produk['is_active'] === 1 ? 0 : 1;

            $upd = $pdo->prepare('UPDATE products SET is_active = ? WHERE id = ?');
            $upd->execute([$aktifBaru, $id]);

            set_flash('success', $aktifBaru === 1
                ? 'Produk "' . $produk['nama_barang'] . '" diaktifkan kembali.'
                : 'Produk "' . $produk['nama_barang'] . '" dinonaktifkan dan tidak tampil di kasir.');
        }
    }

    // Kembali ke daftar dengan filter yang sama seperti sebelumnya
    $kembali = array_filter([
        'q'        => mb_substr(trim((string) ($_POST['q'] ?? '')), 0, 100),
        'kategori' => (string) ($_POST['kategori'] ?? ''),
        'status'   => (string) ($_POST['status'] ?? ''),
        'page'     => (string) ($_POST['page'] ?? ''),
    ], fn ($v) => $v !== '');

    redirect('admin/produk/index.php' . ($kembali ? '?' . http_build_query($kembali) : ''));
}

/* ---------- Filter dari URL (GET) ---------- */
$q          = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 100);
$kategoriId = (int) ($_GET['kategori'] ?? 0);
$status     = (string) ($_GET['status'] ?? '');
if (!in_array($status, $statusValid, true)) {
    $status = '';
}
$page = max(1, (int) ($_GET['page'] ?? 1));

$where  = ['1 = 1'];
$params = [];

if ($q !== '') {
    $where[]  = '(p.nama_barang LIKE ? OR p.kode_barang LIKE ?)';
    $params[] = '%' . $q . '%';
    $params[] = '%' . $q . '%';
}
if ($kategoriId > 0) {
    $where[]  = 'p.category_id = ?';
    $params[] = $kategoriId;
}
if ($status === 'aktif') {
    $where[] = 'p.is_active = 1';
} elseif ($status === 'nonaktif') {
    $where[] = 'p.is_active = 0';
} elseif ($status === 'menipis') {
    $where[] = 'p.is_active = 1 AND p.stok <= p.stok_minimum';
}

$sqlDari = ' FROM products p
             LEFT JOIN categories k ON k.id = p.category_id
            WHERE ' . implode(' AND ', $where);

$stmt = $pdo->prepare('SELECT COUNT(*)' . $sqlDari);
$stmt->execute($params);
$total = (int) $stmt->fetchColumn();

$pg = paginate_info($total, $perPage, $page);

// LIMIT dan OFFSET berasal dari nilai integer hasil paginate_info(), jadi aman dimasukkan langsung
$stmt = $pdo->prepare(
    'SELECT p.*, k.nama_kategori' . $sqlDari
    . ' ORDER BY p.nama_barang ASC LIMIT ' . $pg['per_page'] . ' OFFSET ' . $pg['offset']
);
$stmt->execute($params);
$produkList = $stmt->fetchAll();

$kategoriList = $pdo->query('SELECT id, nama_kategori FROM categories ORDER BY nama_kategori ASC')->fetchAll();

// Filter yang dibawa ke tautan halaman
$queryFilter = array_filter([
    'q'        => $q,
    'kategori' => $kategoriId > 0 ? $kategoriId : '',
    'status'   => $status,
], fn ($v) => $v !== '');

require __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <div>
        <h2>Daftar Barang</h2>
        <p class="muted">Kelola nama, kategori, harga modal, harga jual, dan batas stok minimum.</p>
    </div>
    <a class="btn btn-primary" href="<?= e(url('admin/produk/form.php')) ?>">+ Tambah Produk</a>
</div>

<form method="get" action="<?= e(url('admin/produk/index.php')) ?>" class="card filter-bar">
    <div>
        <label for="q">Cari</label>
        <input type="text" id="q" name="q" value="<?= e($q) ?>" maxlength="100"
               placeholder="Nama atau kode barang">
    </div>

    <div>
        <label for="kategori">Kategori</label>
        <select id="kategori" name="kategori">
            <option value="">Semua kategori</option>
            <?php foreach ($kategoriList as $k): ?>
                <option value="<?= (int) $k['id'] ?>" <?= $kategoriId === (int) $k['id'] ? 'selected' : '' ?>>
                    <?= e($k['nama_kategori']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div>
        <label for="status">Status</label>
        <select id="status" name="status">
            <option value="" <?= $status === '' ? 'selected' : '' ?>>Semua status</option>
            <option value="aktif" <?= $status === 'aktif' ? 'selected' : '' ?>>Aktif</option>
            <option value="nonaktif" <?= $status === 'nonaktif' ? 'selected' : '' ?>>Nonaktif</option>
            <option value="menipis" <?= $status === 'menipis' ? 'selected' : '' ?>>Stok menipis / habis</option>
        </select>
    </div>

    <button type="submit" class="btn btn-primary">Terapkan</button>
    <a class="btn btn-secondary" href="<?= e(url('admin/produk/index.php')) ?>">Reset</a>
</form>

<section class="card">
    <div class="card-header">
        <h2>Produk</h2>
        <span class="muted">Menampilkan <?= count($produkList) ?> dari <?= $total ?> produk</span>
    </div>

    <?php if (empty($produkList)): ?>
        <div class="empty-state">
            Tidak ada produk yang cocok dengan filter.
            <?php if ($q !== '' || $kategoriId > 0 || $status !== ''): ?>
                <br><a href="<?= e(url('admin/produk/index.php')) ?>">Hapus semua filter</a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Kode</th>
                        <th>Nama Barang</th>
                        <th>Kategori</th>
                        <th class="num">Harga Modal</th>
                        <th class="num">Harga Jual</th>
                        <th class="num">Laba/Item</th>
                        <th class="num">Stok</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($produkList as $p):
                        $laba = (int) $p['harga_jual'] - (int) $p['harga_modal'];
                    ?>
                        <tr>
                            <td><code><?= e($p['kode_barang']) ?></code></td>
                            <td>
                                <strong><?= e($p['nama_barang']) ?></strong>
                                <div class="hint"><?= e($p['satuan']) ?></div>
                            </td>
                            <td><?= e($p['nama_kategori'] ?? '-') ?></td>
                            <td class="num"><?= e(rupiah($p['harga_modal'])) ?></td>
                            <td class="num"><?= e(rupiah($p['harga_jual'])) ?></td>
                            <td class="num"><?= e(rupiah($laba)) ?></td>
                            <td class="num">
                                <span class="stok-angka"><?= (int) $p['stok'] ?></span>
                                <?= badge_stok((int) $p['stok'], (int) $p['stok_minimum']) ?>
                                <div class="hint">Min. <?= (int) $p['stok_minimum'] ?></div>
                            </td>
                            <td>
                                <span class="badge badge-<?= (int) $p['is_active'] === 1 ? 'aktif' : 'nonaktif' ?>">
                                    <?= (int) $p['is_active'] === 1 ? 'Aktif' : 'Nonaktif' ?>
                                </span>
                            </td>
                            <td class="aksi">
                                <a class="btn btn-secondary btn-sm"
                                   href="<?= e(url('admin/produk/form.php?id=' . (int) $p['id'])) ?>">Ubah</a>

                                <form method="post" action="<?= e(url('admin/produk/index.php')) ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="toggle">
                                    <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                                    <input type="hidden" name="q" value="<?= e($q) ?>">
                                    <input type="hidden" name="kategori" value="<?= e($kategoriId > 0 ? $kategoriId : '') ?>">
                                    <input type="hidden" name="status" value="<?= e($status) ?>">
                                    <input type="hidden" name="page" value="<?= (int) $pg['page'] ?>">
                                    <button type="submit" class="btn btn-secondary btn-sm"
                                            data-confirm="<?= e((int) $p['is_active'] === 1
                                                ? 'Nonaktifkan produk ini? Produk tidak akan tampil di kasir.'
                                                : 'Aktifkan kembali produk ini?') ?>">
                                        <?= (int) $p['is_active'] === 1 ? 'Nonaktifkan' : 'Aktifkan' ?>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php render_pagination('admin/produk/index.php', $queryFilter, $pg); ?>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../../includes/footer.php'; ?>