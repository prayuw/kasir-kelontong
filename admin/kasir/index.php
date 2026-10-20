<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_role(ROLE_ADMIN);

$pageTitle  = 'Akun Kasir';
$activeMenu = 'akun_kasir';

$pdo    = db();
$errors = [];
$form   = ['id' => 0, 'username' => '', 'nama_lengkap' => '', 'is_active' => true];

/**
 * Mengambil satu akun kasir. Akun admin sengaja tidak ikut diambil.
 */
function ambil_akun_kasir(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare(
        "SELECT id, username, nama_lengkap, is_active
           FROM users
          WHERE id = ? AND role = 'kasir'
          LIMIT 1"
    );
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Jumlah catatan yang mengacu pada akun ini (transaksi, pembatalan, mutasi stok).
 * Jika lebih dari 0, akun tidak boleh dihapus karena riwayat harus tetap utuh.
 */
function jumlah_riwayat_akun(PDO $pdo, int $id): int
{
    $stmt = $pdo->prepare(
        'SELECT (SELECT COUNT(*) FROM transactions WHERE user_id = ?)
              + (SELECT COUNT(*) FROM transactions WHERE batal_oleh = ?)
              + (SELECT COUNT(*) FROM stock_movements WHERE user_id = ?)'
    );
    $stmt->execute([$id, $id, $id]);
    return (int) $stmt->fetchColumn();
}

/* ---------- Proses aksi POST ---------- */
if (is_post()) {
    if (!csrf_valid()) {
        set_flash('error', 'Sesi formulir tidak valid. Silakan coba lagi.');
        redirect('admin/kasir/index.php');
    }

    $action = input_string($_POST, 'action', 20);
    $id     = (int) ($_POST['id'] ?? 0);

    /* ----- Tambah atau ubah akun ----- */
    if ($action === 'simpan') {
        $username     = input_string($_POST, 'username', 50);
        $namaLengkap  = input_string($_POST, 'nama_lengkap', 100);
        $isActive     = isset($_POST['is_active']);

        // Password tidak di-trim agar spasi di awal atau akhir tetap terbaca apa adanya
        $password   = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
        $konfirmasi = is_string($_POST['konfirmasi'] ?? null) ? $_POST['konfirmasi'] : '';

        $form = [
            'id'           => $id,
            'username'     => $username,
            'nama_lengkap' => $namaLengkap,
            'is_active'    => $isActive,
        ];

        $sedangUbah = $id > 0;

        if ($sedangUbah && ambil_akun_kasir($pdo, $id) === null) {
            $errors[] = 'Akun kasir yang akan diubah tidak ditemukan.';
        }

        if ($username === '') {
            $errors[] = 'Username wajib diisi.';
        } elseif (!preg_match('/^[a-zA-Z0-9_.]{3,50}$/', $username)) {
            $errors[] = 'Username hanya boleh huruf, angka, titik, atau garis bawah (3 sampai 50 karakter).';
        } else {
            $cek = $pdo->prepare('SELECT id FROM users WHERE username = ? AND id <> ? LIMIT 1');
            $cek->execute([$username, $id]);
            if ($cek->fetch()) {
                $errors[] = 'Username sudah digunakan akun lain.';
            }
        }

        if ($namaLengkap === '') {
            $errors[] = 'Nama lengkap wajib diisi.';
        }

        $adaPassword = $password !== '';
        if (!$sedangUbah && !$adaPassword) {
            $errors[] = 'Password awal wajib diisi untuk akun baru.';
        }
        if ($adaPassword) {
            $panjang = mb_strlen($password);
            if ($panjang < 8) {
                $errors[] = 'Password minimal 8 karakter.';
            } elseif ($panjang > 100) {
                $errors[] = 'Password maksimal 100 karakter.';
            } elseif ($password !== $konfirmasi) {
                $errors[] = 'Konfirmasi password tidak sama dengan password.';
            }
        }

        if (!$errors) {
            try {
                if ($sedangUbah) {
                    if ($adaPassword) {
                        $stmt = $pdo->prepare(
                            "UPDATE users
                                SET username = ?, nama_lengkap = ?, is_active = ?, password = ?
                              WHERE id = ? AND role = 'kasir'"
                        );
                        $stmt->execute([
                            $username, $namaLengkap, $isActive ? 1 : 0,
                            password_hash($password, PASSWORD_DEFAULT), $id,
                        ]);
                    } else {
                        $stmt = $pdo->prepare(
                            "UPDATE users
                                SET username = ?, nama_lengkap = ?, is_active = ?
                              WHERE id = ? AND role = 'kasir'"
                        );
                        $stmt->execute([$username, $namaLengkap, $isActive ? 1 : 0, $id]);
                    }
                    set_flash('success', 'Data akun ' . $username . ' berhasil diperbarui.'
                        . ($adaPassword ? ' Password baru sudah disimpan.' : ''));
                } else {
                    $stmt = $pdo->prepare(
                        "INSERT INTO users (username, password, nama_lengkap, role, is_active)
                         VALUES (?, ?, ?, 'kasir', ?)"
                    );
                    $stmt->execute([
                        $username,
                        password_hash($password, PASSWORD_DEFAULT),
                        $namaLengkap,
                        $isActive ? 1 : 0,
                    ]);
                    set_flash('success', 'Akun kasir ' . $username . ' berhasil ditambahkan.');
                }

                redirect('admin/kasir/index.php');
            } catch (PDOException $e) {
                error_log('[AKUN_KASIR] ' . $e->getMessage());
                $errors[] = 'Data gagal disimpan. Periksa kembali isian Anda lalu coba lagi.';
            }
        }

    /* ----- Aktifkan atau nonaktifkan akun ----- */
    } elseif ($action === 'toggle') {
        $akun = $id > 0 ? ambil_akun_kasir($pdo, $id) : null;

        if (!$akun) {
            set_flash('error', 'Akun kasir tidak ditemukan.');
        } else {
            $aktifBaru = (int) $akun['is_active'] === 1 ? 0 : 1;

            $stmt = $pdo->prepare("UPDATE users SET is_active = ? WHERE id = ? AND role = 'kasir'");
            $stmt->execute([$aktifBaru, $id]);

            set_flash('success', $aktifBaru === 1
                ? 'Akun ' . $akun['username'] . ' diaktifkan kembali.'
                : 'Akun ' . $akun['username'] . ' dinonaktifkan. Kasir tersebut tidak bisa login lagi.');
        }
        redirect('admin/kasir/index.php');

    /* ----- Hapus akun ----- */
    } elseif ($action === 'hapus') {
        $akun = $id > 0 ? ambil_akun_kasir($pdo, $id) : null;

        if (!$akun) {
            set_flash('error', 'Akun kasir tidak ditemukan.');
        } elseif (jumlah_riwayat_akun($pdo, $id) > 0) {
            set_flash('error', 'Akun ' . $akun['username'] . ' sudah memiliki riwayat transaksi atau stok, '
                . 'sehingga tidak bisa dihapus. Nonaktifkan akun tersebut saja.');
        } else {
            try {
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'kasir'");
                $stmt->execute([$id]);
                set_flash('success', 'Akun ' . $akun['username'] . ' berhasil dihapus.');
            } catch (PDOException $e) {
                error_log('[HAPUS_AKUN_KASIR] ' . $e->getMessage());
                set_flash('error', 'Akun tidak dapat dihapus karena masih terhubung dengan data lain. Nonaktifkan saja.');
            }
        }
        redirect('admin/kasir/index.php');

    } else {
        set_flash('error', 'Aksi tidak dikenali.');
        redirect('admin/kasir/index.php');
    }
}

/* ---------- Mode ubah (GET ?edit=id) ---------- */
if (!is_post() && isset($_GET['edit'])) {
    $akun = ambil_akun_kasir($pdo, (int) $_GET['edit']);

    if (!$akun) {
        set_flash('error', 'Akun kasir yang akan diubah tidak ditemukan.');
        redirect('admin/kasir/index.php');
    }

    $form = [
        'id'           => (int) $akun['id'],
        'username'     => $akun['username'],
        'nama_lengkap' => $akun['nama_lengkap'],
        'is_active'    => (int) $akun['is_active'] === 1,
    ];
}

/* ---------- Daftar akun kasir ---------- */
$daftar = $pdo->query(
    "SELECT u.id, u.username, u.nama_lengkap, u.is_active, u.created_at,
            (SELECT COUNT(*) FROM transactions t WHERE t.user_id = u.id)     AS jml_transaksi,
            (SELECT COUNT(*) FROM transactions t WHERE t.batal_oleh = u.id)  AS jml_batal,
            (SELECT COUNT(*) FROM stock_movements m WHERE m.user_id = u.id)  AS jml_stok
       FROM users u
      WHERE u.role = 'kasir'
      ORDER BY u.nama_lengkap ASC"
)->fetchAll();

$jumlahAktif = 0;
foreach ($daftar as $row) {
    if ((int) $row['is_active'] === 1) {
        $jumlahAktif++;
    }
}

$sedangUbah = (int) $form['id'] > 0;

require __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <div>
        <h2>Kelola Akun Kasir</h2>
        <p class="muted">Tambah, ubah, aktifkan, atau nonaktifkan akun yang bisa dipakai untuk melayani transaksi.</p>
    </div>
</div>

<div class="grid-split">
    <section class="card">
        <h2><?= $sedangUbah ? 'Ubah Akun Kasir' : 'Tambah Akun Kasir' ?></h2>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <ul>
                    <?php foreach ($errors as $err): ?>
                        <li><?= e($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="post" action="<?= e(url('admin/kasir/index.php')) ?>" autocomplete="off">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="simpan">
            <input type="hidden" name="id" value="<?= (int) $form['id'] ?>">

            <div class="form-group">
                <label for="nama_lengkap">Nama Lengkap</label>
                <input type="text" id="nama_lengkap" name="nama_lengkap" maxlength="100" required
                       value="<?= e($form['nama_lengkap']) ?>" placeholder="contoh: Siti Aminah">
            </div>

            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" maxlength="50" required
                       value="<?= e($form['username']) ?>" placeholder="contoh: kasir2">
                <div class="hint">3 sampai 50 karakter. Huruf, angka, titik, atau garis bawah.</div>
            </div>

            <div class="form-group">
                <label for="password"><?= $sedangUbah ? 'Password Baru' : 'Password Awal' ?></label>
                <div class="password-field">
                    <input type="password" id="password" name="password" maxlength="100"
                           autocomplete="new-password" <?= $sedangUbah ? '' : 'required' ?>>
                    <?= tombol_toggle_password('password') ?>
                </div>
                <div class="hint">
                    <?= $sedangUbah
                        ? 'Kosongkan jika tidak ingin mengubah password. Minimal 8 karakter.'
                        : 'Minimal 8 karakter. Berikan password ini kepada kasir.' ?>
                </div>
            </div>

            <div class="form-group">
                <label for="konfirmasi">Ulangi Password</label>
                <div class="password-field">
                    <input type="password" id="konfirmasi" name="konfirmasi" maxlength="100"
                           autocomplete="new-password">
                    <?= tombol_toggle_password('konfirmasi') ?>
                </div>
            </div>

            <label class="checkbox-row">
                <input type="checkbox" name="is_active" value="1" <?= $form['is_active'] ? 'checked' : '' ?>>
                Akun aktif (bisa login)
            </label>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <?= $sedangUbah ? 'Simpan Perubahan' : 'Tambah Akun' ?>
                </button>
                <?php if ($sedangUbah): ?>
                    <a class="btn btn-secondary" href="<?= e(url('admin/kasir/index.php')) ?>">Batal</a>
                <?php endif; ?>
            </div>
        </form>
    </section>

    <section class="card">
        <div class="card-header">
            <h2>Daftar Akun Kasir</h2>
            <span class="muted"><?= count($daftar) ?> akun, <?= $jumlahAktif ?> aktif</span>
        </div>

        <?php if (empty($daftar)): ?>
            <div class="empty-state">Belum ada akun kasir. Tambahkan akun pertama lewat formulir di samping.</div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Nama</th>
                            <th>Username</th>
                            <th>Status</th>
                            <th class="num">Transaksi</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($daftar as $k):
                            $aktif      = (int) $k['is_active'] === 1;
                            $riwayat    = (int) $k['jml_transaksi'] + (int) $k['jml_batal'] + (int) $k['jml_stok'];
                            $bolehHapus = $riwayat === 0;
                        ?>
                            <tr>
                                <td>
                                    <strong><?= e($k['nama_lengkap']) ?></strong>
                                    <div class="hint">Dibuat <?= e(tanggal_indo($k['created_at'])) ?></div>
                                </td>
                                <td><code><?= e($k['username']) ?></code></td>
                                <td>
                                    <span class="badge badge-<?= $aktif ? 'aktif' : 'nonaktif' ?>">
                                        <?= $aktif ? 'Aktif' : 'Nonaktif' ?>
                                    </span>
                                </td>
                                <td class="num"><?= (int) $k['jml_transaksi'] ?></td>
                                <td class="aksi">
                                    <a class="btn btn-secondary btn-sm"
                                       href="<?= e(url('admin/kasir/index.php?edit=' . (int) $k['id'])) ?>">Ubah</a>

                                    <form method="post" action="<?= e(url('admin/kasir/index.php')) ?>">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="toggle">
                                        <input type="hidden" name="id" value="<?= (int) $k['id'] ?>">
                                        <button type="submit" class="btn btn-secondary btn-sm"
                                                data-confirm="<?= e($aktif
                                                    ? 'Nonaktifkan akun ini? Kasir tidak bisa login sampai diaktifkan kembali.'
                                                    : 'Aktifkan kembali akun ini?') ?>">
                                            <?= $aktif ? 'Nonaktifkan' : 'Aktifkan' ?>
                                        </button>
                                    </form>

                                    <?php if ($bolehHapus): ?>
                                        <form method="post" action="<?= e(url('admin/kasir/index.php')) ?>">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="hapus">
                                            <input type="hidden" name="id" value="<?= (int) $k['id'] ?>">
                                            <button type="submit" class="btn btn-danger btn-sm"
                                                    data-confirm="<?= e('Hapus akun ' . $k['username'] . ' secara permanen?') ?>">Hapus</button>
                                        </form>
                                    <?php else: ?>
                                        <button type="button" class="btn btn-secondary btn-sm" disabled
                                                title="Akun ini memiliki riwayat dan tidak bisa dihapus. Gunakan Nonaktifkan.">
                                            Hapus
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <p class="hint" style="margin-top:12px;">
                Akun yang sudah pernah melayani transaksi atau mutasi stok tidak bisa dihapus agar riwayat tetap utuh.
                Gunakan Nonaktifkan untuk menutup aksesnya.
            </p>
        <?php endif; ?>
    </section>
</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>