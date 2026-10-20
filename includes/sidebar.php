<?php
declare(strict_types=1);

$menuAdmin = [
    'dashboard'  => ['Dashboard',          'admin/dashboard.php'],
    'produk'     => ['Data Barang',        'admin/produk/index.php'],
    'kategori'   => ['Kategori',           'admin/kategori/index.php'],
    'stok'       => ['Stok Barang',        'admin/stok/index.php'],
    'akun_kasir' => ['Akun Kasir',         'admin/kasir/index.php'],
    'transaksi'  => ['Riwayat Transaksi',  'admin/transaksi/index.php'],
    'laporan'    => ['Laporan Penjualan',  'admin/laporan/index.php'],
    'pengaturan' => ['Pengaturan Toko',    'admin/pengaturan.php'],
    'backup'     => ['Backup Database',    'admin/backup.php'],
];

$menuKasir = [
    'transaksi' => ['Transaksi Baru', 'kasir/transaksi.php'],
    'riwayat'   => ['Riwayat Saya',   'kasir/riwayat.php'],
];

$menu = $user['role'] === ROLE_ADMIN ? $menuAdmin : $menuKasir;
?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <div class="brand-icon">K</div>
        <div>
            <strong><?= e(APP_NAME) ?></strong>
            <small>Sistem Kasir</small>
        </div>
    </div>

    <nav class="sidebar-nav">
        <?php foreach ($menu as $key => [$label, $path]): ?>
            <a href="<?= e(url($path)) ?>"
               class="<?= $activeMenu === $key ? 'active' : '' ?>">
                <?= e($label) ?>
            </a>
        <?php endforeach; ?>

        <a href="<?= e(url('akun/ubah_password.php')) ?>"
           class="<?= $activeMenu === 'password' ? 'active' : '' ?>">
            Ganti Password
        </a>
    </nav>

    <form method="post" action="<?= e(url('auth/logout.php')) ?>" class="logout-form">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-outline btn-block">Logout</button>
    </form>
</aside>