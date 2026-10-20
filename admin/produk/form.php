<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_role(ROLE_ADMIN);

$pdo    = db();
$id     = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$isEdit = $id > 0;

$satuanList = ['pcs', 'bungkus', 'botol', 'sachet', 'batang', 'karung',
               'kaleng', 'kotak', 'pack', 'kg', 'liter', 'lembar'];

$kategoriList = $pdo->query('SELECT id, nama_kategori FROM categories ORDER BY nama_kategori ASC')->fetchAll();
$kategoriIds  = array_map(fn ($k) => (int) $k['id'], $kategoriList);

/**
 * Validasi isian produk di sisi server.
 * Mengembalikan daftar pesan kesalahan (kosong jika valid).
 */
function validasi_produk(array $f, PDO $pdo, int $id, array $kategoriIds, array $satuanList, bool $isEdit): array
{
    $err = [];

    // Kode barang
    if ($f['kode_barang'] === '') {
        $err[] = 'Kode barang wajib diisi.';
    } elseif (strlen($f['kode_barang']) > 30) {
        $err[] = 'Kode barang maksimal 30 karakter.';
    } elseif (!preg_match('/^[A-Z0-9._\-\/]+$/', $f['kode_barang'])) {
        $err[] = 'Kode barang hanya boleh berisi huruf, angka, titik, strip, garis miring, atau garis bawah.';
    } else {
        $cek = $pdo->prepare('SELECT id FROM products WHERE kode_barang = ? AND id <> ? LIMIT 1');
        $cek->execute([$f['kode_barang'], $id]);
        if ($cek->fetch()) {
            $err[] = 'Kode barang sudah digunakan produk lain.';
        }
    }

    // Nama barang
    if ($f['nama_barang'] === '') {
        $err[] = 'Nama barang wajib diisi.';
    } elseif (mb_strlen($f['nama_barang']) > 150) {
        $err[] = 'Nama barang maksimal 150 karakter.';
    }

    // Kategori (boleh kosong)
    if ($f['category_id'] !== ''
        && (!is_valid_angka($f['category_id'], 10) || !in_array((int) $f['category_id'], $kategoriIds, true))) {
        $err[] = 'Kategori yang dipilih tidak valid.';
    }

    // Satuan
    if (!in_array($f['satuan'], $satuanList, true)) {
        $err[] = 'Satuan yang dipilih tidak valid.';
    }

    // Harga
    $modalValid = is_valid_angka($f['harga_modal'], 10);
    $jualValid  = is_valid_angka($f['harga_jual'], 10);

    if (!$modalValid) {
        $err[] = 'Harga modal harus berupa angka bulat tanpa titik atau koma.';
    }
    if (!$jualValid) {
        $err[] = 'Harga jual harus berupa angka bulat tanpa titik atau koma.';
    }
    if ($modalValid && $jualValid && (int) $f['harga_jual'] < (int) $f['harga_modal']) {
        $err[] = 'Harga jual tidak boleh lebih rendah dari harga modal.';
    }

    // Stok minimum
    if (!is_valid_angka($f['stok_minimum'])) {
        $err[] = 'Stok minimum harus berupa angka bulat, minimal 0.';
    }

    // Stok awal hanya diisi saat produk baru dibuat
    if (!$isEdit && !is_valid_angka($f['stok_awal'])) {
        $err[] = 'Stok awal harus berupa angka bulat, minimal 0.';
    }

    return $err;
}

/* ---------- Muat data produk untuk mode edit ---------- */
$produkAsli = null;
if ($isEdit) {
    $stmt = $pdo->prepare('SELECT * FROM products WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $produkAsli = $stmt->fetch();

    if (!$produkAsli) {
        set_flash('error', 'Produk tidak ditemukan.');
        redirect('admin/produk/index.php');
    }
}

/* ---------- Nilai awal formulir ---------- */
if ($isEdit) {
    $form = [
        'kode_barang' => $produkAsli['kode_barang'],
        'nama_barang' => $produkAsli['nama_barang'],
        'category_id' => (string) ($produkAsli['category_id'] ?? ''),
        'satuan'      => $produkAsli['satuan'],
        'harga_modal' => (string) (int) $produkAsli['harga_modal'],
        'harga_jual'  => (string) (int) $produkAsli['harga_jual'],
        'stok_minimum'=> (string) (int) $produkAsli['stok_minimum'],
        'stok_awal'   => '0',
        'is_active'   => (int) $produkAsli['is_active'] === 1,
    ];
} else {
    $form = [
        'kode_barang' => '',
        'nama_barang' => '',
        'category_id' => '',
        'satuan'      => 'pcs',
        'harga_modal' => '0',
        'harga_jual'  => '0',
        'stok_minimum'=> '5',
        'stok_awal'   => '0',
        'is_active'   => true,
    ];
}

$errors = [];

/* ---------- Proses simpan (POST) ---------- */
if (is_post()) {
    if (!csrf_valid()) {
        $errors[] = 'Sesi formulir tidak valid. Muat ulang halaman lalu coba lagi.';
    } else {
        $form = [
            'kode_barang' => strtoupper(trim((string) ($_POST['kode_barang'] ?? ''))),
            'nama_barang' => trim((string) ($_POST['nama_barang'] ?? '')),
            'category_id' => trim((string) ($_POST['category_id'] ?? '')),
            'satuan'      => (string) ($_POST['satuan'] ?? ''),
            'harga_modal' => trim((string) ($_POST['harga_modal'] ?? '')),
            'harga_jual'  => trim((string) ($_POST['harga_jual'] ?? '')),
            'stok_minimum'=> trim((string) ($_POST['stok_minimum'] ?? '')),
            'stok_awal'   => trim((string) ($_POST['stok_awal'] ?? '0')),
            'is_active'   => isset($_POST['is_active']),
        ];

        $errors = validasi_produk($form, $pdo, $id, $kategoriIds, $satuanList, $isEdit);
    }

    if (!$errors) {
        $katId   = $form['category_id'] === '' ? null : (int) $form['category_id'];
        $hModal  = (int) $form['harga_modal'];
        $hJual   = (int) $form['harga_jual'];
        $stokMin = (int) $form['stok_minimum'];
        $aktif   = $form['is_active'] ? 1 : 0;

        try {
            $pdo->beginTransaction();

            if ($isEdit) {
                // Stok sengaja tidak diubah di sini. Perubahan stok dilakukan lewat menu Stok Barang.
                $stmt = $pdo->prepare(
                    'UPDATE products
                        SET kode_barang = ?, nama_barang = ?, category_id = ?, satuan = ?,
                            harga_modal = ?, harga_jual = ?, stok_minimum = ?, is_active = ?
                      WHERE id = ?'
                );
                $stmt->execute([
                    $form['kode_barang'], $form['nama_barang'], $katId, $form['satuan'],
                    $hModal, $hJual, $stokMin, $aktif, $id,
                ]);

                $pdo->commit();
                set_flash('success', 'Data produk berhasil diperbarui.');
            } else {
                $stokAwal = (int) $form['stok_awal'];

                $stmt = $pdo->prepare(
                    'INSERT INTO products
                        (kode_barang, nama_barang, category_id, satuan, harga_modal, harga_jual, stok, stok_minimum, is_active)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
                );
                $stmt->execute([
                    $form['kode_barang'], $form['nama_barang'], $katId, $form['satuan'],
                    $hModal, $hJual, $stokAwal, $stokMin, $aktif,
                ]);
                $idBaru = (int) $pdo->lastInsertId();

                // Stok awal dicatat sebagai barang masuk agar riwayat stok lengkap
                if ($stokAwal > 0) {
                    $mv = $pdo->prepare(
                        "INSERT INTO stock_movements
                            (product_id, user_id, jenis, qty, stok_sebelum, stok_sesudah, referensi, keterangan)
                         VALUES (?, ?, 'masuk', ?, 0, ?, NULL, ?)"
                    );
                    $mv->execute([
                        $idBaru,
                        (int) current_user()['id'],
                        $stokAwal,
                        $stokAwal,
                        'Stok awal saat produk ditambahkan',
                    ]);
                }

                $pdo->commit();
                set_flash('success', 'Produk baru berhasil ditambahkan.');
            }

            redirect('admin/produk/index.php');
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[PRODUK] ' . $e->getMessage());
            $errors[] = 'Data gagal disimpan. Periksa kembali isian Anda lalu coba lagi.';
        }
    }
}

$pageTitle  = $isEdit ? 'Ubah Produk' : 'Tambah Produk';
$activeMenu = 'produk';

require __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <div>
        <h2><?= $isEdit ? 'Ubah Data Barang' : 'Tambah Barang Baru' ?></h2>
        <p class="muted">Perubahan harga tidak mengubah riwayat transaksi yang sudah tercatat.</p>
    </div>
    <a class="btn btn-secondary" href="<?= e(url('admin/produk/index.php')) ?>">← Kembali ke daftar</a>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-error">
        <ul>
            <?php foreach ($errors as $err): ?>
                <li><?= e($err) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form method="post" action="<?= e(url('admin/produk/form.php')) ?>" class="card">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int) $id ?>">

    <div class="form-grid">
        <div class="form-group">
            <label for="kode_barang">Kode Barang</label>
            <input type="text" id="kode_barang" name="kode_barang" maxlength="30" required
                   value="<?= e($form['kode_barang']) ?>" placeholder="contoh: SMB-013">
            <div class="hint">Huruf, angka, titik, strip, atau garis bawah. Harus unik.</div>
        </div>

        <div class="form-group">
            <label for="category_id">Kategori</label>
            <select id="category_id" name="category_id">
                <option value="">Tanpa kategori</option>
                <?php foreach ($kategoriList as $k): ?>
                    <option value="<?= (int) $k['id'] ?>"
                        <?= $form['category_id'] === (string) $k['id'] ? 'selected' : '' ?>>
                        <?= e($k['nama_kategori']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group full">
            <label for="nama_barang">Nama Barang</label>
            <input type="text" id="nama_barang" name="nama_barang" maxlength="150" required
                   value="<?= e($form['nama_barang']) ?>" placeholder="contoh: Beras Premium 5 kg">
        </div>

        <div class="form-group">
            <label for="satuan">Satuan</label>
            <select id="satuan" name="satuan" required>
                <?php foreach ($satuanList as $s): ?>
                    <option value="<?= e($s) ?>" <?= $form['satuan'] === $s ? 'selected' : '' ?>>
                        <?= e($s) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="stok_minimum">Batas Stok Minimum</label>
            <input type="number" id="stok_minimum" name="stok_minimum" min="0" max="999999999" step="1"
                   required value="<?= e($form['stok_minimum']) ?>">
            <div class="hint">Peringatan muncul jika stok sama dengan atau di bawah angka ini.</div>
        </div>

        <div class="form-group">
            <label for="harga_modal">Harga Modal (Rp)</label>
            <input type="number" id="harga_modal" name="harga_modal" min="0" max="9999999999" step="1"
                   required value="<?= e($form['harga_modal']) ?>">
            <div class="hint">Tanpa titik. Contoh: 62000</div>
        </div>

        <div class="form-group">
            <label for="harga_jual">Harga Jual (Rp)</label>
            <input type="number" id="harga_jual" name="harga_jual" min="0" max="9999999999" step="1"
                   required value="<?= e($form['harga_jual']) ?>">
            <div class="hint">Tanpa titik. Contoh: 68000</div>
        </div>

        <div class="full">
            <div class="laba-preview" id="laba_preview"></div>
        </div>

        <?php if ($isEdit): ?>
            <div class="form-group full">
                <div class="info-box">
                    Stok saat ini: <strong><?= (int) $produkAsli['stok'] ?></strong>
                    <?= badge_stok((int) $produkAsli['stok'], (int) $produkAsli['stok_minimum']) ?><br>
                    <span class="muted">Stok diubah lewat menu Stok Barang (barang masuk), bukan dari formulir ini.</span>
                </div>
            </div>
        <?php else: ?>
            <div class="form-group">
                <label for="stok_awal">Stok Awal</label>
                <input type="number" id="stok_awal" name="stok_awal" min="0" max="999999999" step="1"
                       required value="<?= e($form['stok_awal']) ?>">
                <div class="hint">Jumlah barang yang sudah ada saat produk ini dimasukkan.</div>
            </div>
        <?php endif; ?>

        <div class="full">
            <label class="checkbox-row">
                <input type="checkbox" name="is_active" value="1" <?= $form['is_active'] ? 'checked' : '' ?>>
                Produk aktif (tampil dan bisa dijual di kasir)
            </label>
        </div>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Simpan Perubahan' : 'Simpan Produk' ?></button>
        <a class="btn btn-secondary" href="<?= e(url('admin/produk/index.php')) ?>">Batal</a>
    </div>
</form>

<script>
(function () {
    var modal = document.getElementById('harga_modal');
    var jual  = document.getElementById('harga_jual');
    var out   = document.getElementById('laba_preview');
    if (!modal || !jual || !out) return;

    function hitung() {
        var m = parseInt(modal.value, 10) || 0;
        var j = parseInt(jual.value, 10) || 0;
        var laba = j - m;
        out.classList.toggle('minus', laba < 0);
        out.textContent = laba < 0
            ? 'Harga jual lebih rendah dari harga modal.'
            : 'Keuntungan kotor per item: Rp ' + laba.toLocaleString('id-ID');
    }

    modal.addEventListener('input', hitung);
    jual.addEventListener('input', hitung);
    hitung();
})();
</script>

<?php require __DIR__ . '/../../includes/footer.php'; ?>