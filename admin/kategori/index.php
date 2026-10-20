<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_role(ROLE_ADMIN);

$pageTitle  = 'Kategori Barang';
$activeMenu = 'kategori';

$pdo    = db();
$errors = [];
$form   = ['id' => 0, 'nama_kategori' => ''];

/* ---------- Proses aksi POST ---------- */
if (is_post()) {
    if (!csrf_valid()) {
        set_flash('error', 'Sesi formulir tidak valid. Silakan coba lagi.');
        redirect('admin/kategori/index.php');
    }

    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'simpan') {
        $id   = (int) ($_POST['id'] ?? 0);
        $nama = trim((string) ($_POST['nama_kategori'] ?? ''));
        $form = ['id' => $id, 'nama_kategori' => $nama];

        if ($nama === '') {
            $errors[] = 'Nama kategori wajib diisi.';
        } elseif (mb_strlen($nama) > 100) {
            $errors[] = 'Nama kategori maksimal 100 karakter.';
        } else {
            $cek = $pdo->prepare('SELECT id FROM categories WHERE nama_kategori = ? AND id <> ? LIMIT 1');
            $cek->execute([$nama, $id]);
            if ($cek->fetch()) {
                $errors[] = 'Nama kategori sudah digunakan.';
            }
        }

        if (!$errors) {
            if ($id > 0) {
                $stmt = $pdo->prepare('UPDATE categories SET nama_kategori = ? WHERE id = ?');
                $stmt->execute([$nama, $id]);
                set_flash('success', 'Kategori berhasil diperbarui.');
            } else {
                $stmt = $pdo->prepare('INSERT INTO categories (nama_kategori) VALUES (?)');
                $stmt->execute([$nama]);
                set_flash('success', 'Kategori berhasil ditambahkan.');
            }
            redirect('admin/kategori/index.php');
        }
    } elseif ($action === 'hapus') {
        $id = (int) ($_POST['id'] ?? 0);

        $stmt = $pdo->prepare('SELECT nama_kategori FROM categories WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $kat = $stmt->fetch();

        if (!$kat) {
            set_flash('error', 'Kategori tidak ditemukan.');
        } else {
            $hitung = $pdo->prepare('SELECT COUNT(*) FROM products WHERE category_id = ?');
            $hitung->execute([$id]);
            $jumlahProduk = (int) $hitung->fetchColumn();

            // Relasi ON DELETE SET NULL: produk tetap ada, hanya kehilangan kategori
            $del = $pdo->prepare('DELETE FROM categories WHERE id = ?');
            $del->execute([$id]);

            $pesan = 'Kategori "' . $kat['nama_kategori'] . '" dihapus.';
            if ($jumlahProduk > 0) {
                $pesan .= ' ' . $jumlahProduk . ' produk kini tanpa kategori.';
            }
            set_flash('success', $pesan);
        }
        redirect('admin/kategori/index.php');
    } else {
        set_flash('error', 'Aksi tidak dikenali.');
        redirect('admin/kategori/index.php');
    }
}

/* ---------- Mode edit (GET ?edit=id) ---------- */
if (!is_post() && isset($_GET['edit'])) {
    $idEdit = (int) $_GET['edit'];
    $stmt   = $pdo->prepare('SELECT id, nama_kategori FROM categories WHERE id = ? LIMIT 1');
    $stmt->execute([$idEdit]);
    $row = $stmt->fetch();

    if ($row) {
        $form = ['id' => (int) $row['id'], 'nama_kategori' => $row['nama_kategori']];
    } else {
        set_flash('error', 'Kategori yang akan diubah tidak ditemukan.');
        redirect('admin/kategori/index.php');
    }
}

/* ---------- Daftar kategori beserta jumlah produk ---------- */
$daftar = $pdo->query(
    'SELECT k.id, k.nama_kategori, COUNT(p.id) AS jumlah_produk
       FROM categories k
       LEFT JOIN products p ON p.category_id = k.id
      GROUP BY k.id, k.nama_kategori
      ORDER BY k.nama_kategori ASC'
)->fetchAll();

$sedangEdit = (int) $form['id'] > 0;

require __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <div>
        <h2>Kelola Kategori</h2>
        <p class="muted">Kategori membantu mengelompokkan barang di toko.</p>
    </div>
</div>

<div class="grid-split">
    <section class="card">
        <h2><?= $sedangEdit ? 'Ubah Kategori' : 'Tambah Kategori' ?></h2>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <ul>
                    <?php foreach ($errors as $err): ?>
                        <li><?= e($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="post" action="<?= e(url('admin/kategori/index.php')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="simpan">
            <input type="hidden" name="id" value="<?= (int) $form['id'] ?>">

            <div class="form-group">
                <label for="nama_kategori">Nama Kategori</label>
                <input type="text" id="nama_kategori" name="nama_kategori"
                       maxlength="100" required value="<?= e($form['nama_kategori']) ?>"
                       placeholder="contoh: Sembako">
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><?= $sedangEdit ? 'Simpan Perubahan' : 'Tambah Kategori' ?></button>
                <?php if ($sedangEdit): ?>
                    <a class="btn btn-secondary" href="<?= e(url('admin/kategori/index.php')) ?>">Batal</a>
                <?php endif; ?>
            </div>
        </form>
    </section>

    <section class="card">
        <div class="card-header">
            <h2>Daftar Kategori</h2>
            <span class="muted"><?= count($daftar) ?> kategori</span>
        </div>

        <?php if (empty($daftar)): ?>
            <div class="empty-state">Belum ada kategori. Tambahkan kategori pertama lewat formulir di samping.</div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Nama Kategori</th>
                            <th class="num">Jumlah Produk</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $no = 1; foreach ($daftar as $k): ?>
                            <tr>
                                <td><?= $no++ ?></td>
                                <td><strong><?= e($k['nama_kategori']) ?></strong></td>
                                <td class="num"><?= (int) $k['jumlah_produk'] ?></td>
                                <td class="aksi">
                                    <a class="btn btn-secondary btn-sm"
                                       href="<?= e(url('admin/kategori/index.php?edit=' . (int) $k['id'])) ?>">Ubah</a>

                                    <form method="post" action="<?= e(url('admin/kategori/index.php')) ?>">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="hapus">
                                        <input type="hidden" name="id" value="<?= (int) $k['id'] ?>">
                                        <button type="submit" class="btn btn-danger btn-sm"
                                                data-confirm="<?= e('Hapus kategori "' . $k['nama_kategori'] . '"? Produk di dalamnya tidak ikut terhapus.') ?>">Hapus</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>