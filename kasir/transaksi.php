<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_role(ROLE_KASIR);

$pageTitle  = 'Transaksi Baru';
$activeMenu = 'transaksi';

$pdo = db();

$kategoriList = $pdo->query('SELECT id, nama_kategori FROM categories ORDER BY nama_kategori ASC')->fetchAll();

// Jika transaksi sebelumnya gagal, keranjang dan uang bayar dikembalikan agar kasir tidak perlu memilih ulang
$oldCheckout = $_SESSION['old_checkout'] ?? ['items' => [], 'uang_bayar' => ''];
unset($_SESSION['old_checkout']);
$oldJson = json_encode($oldCheckout, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);

require __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h2>Transaksi Baru</h2>
        <p class="muted">Pilih barang, masukkan uang yang diterima, lalu selesaikan transaksi.</p>
    </div>
</div>

<div class="pos-layout" id="pos" data-api="<?= e(url('api/cari_produk.php')) ?>">

    <section class="card pos-products">
        <div class="filter-bar">
            <div>
                <label for="cariProduk">Cari barang</label>
                <input type="search" id="cariProduk" placeholder="Nama atau kode, contoh: mi" autocomplete="off">
            </div>
            <div>
                <label for="filterKategori">Kategori</label>
                <select id="filterKategori">
                    <option value="">Semua kategori</option>
                    <?php foreach ($kategoriList as $k): ?>
                        <option value="<?= (int) $k['id'] ?>"><?= e($k['nama_kategori']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="muted" id="statusProduk" style="margin-bottom:12px;">Memuat produk...</div>
        <div class="product-grid" id="hasilProduk"></div>
    </section>

    <section class="card pos-cart">
        <div class="card-header">
            <h2>Keranjang</h2>
            <button type="button" class="btn btn-secondary btn-sm" id="kosongkan">Kosongkan</button>
        </div>

        <div id="pesanPos" class="alert" hidden></div>

        <div class="cart-list" id="daftarKeranjang"></div>

        <div class="totals">
            <div><span>Jumlah item</span><strong id="totalItem">0</strong></div>
            <div class="grand"><span>Total belanja</span><strong id="totalBelanja">Rp 0</strong></div>
        </div>

        <form id="formTransaksi" method="post" action="<?= e(url('kasir/proses_transaksi.php')) ?>" style="margin-top:16px;">
            <?= csrf_field() ?>

            <div class="form-group">
                <label for="uangBayar">Uang diterima (Rp)</label>
                <input type="number" id="uangBayar" name="uang_bayar" min="1" max="9999999999" step="1"
                       inputmode="numeric" placeholder="contoh: 20000">
            </div>

            <div class="quick-cash">
                <button type="button" class="btn btn-secondary btn-sm" data-nominal="pas">Uang pas</button>
                <button type="button" class="btn btn-secondary btn-sm" data-nominal="20000">20.000</button>
                <button type="button" class="btn btn-secondary btn-sm" data-nominal="50000">50.000</button>
                <button type="button" class="btn btn-secondary btn-sm" data-nominal="100000">100.000</button>
            </div>

            <div class="kembalian-box">
                <div><span>Kembalian</span><strong id="kembalian">Rp 0</strong></div>
                <div class="hint" id="pesanBayar"></div>
            </div>

            <button type="submit" class="btn btn-primary btn-block btn-lg" id="btnSimpan" disabled>
                Selesaikan Transaksi
            </button>
        </form>
    </section>
</div>

<script type="application/json" id="old-checkout"><?= $oldJson ?></script>
<script src="<?= e(url('assets/js/kasir.js')) ?>"></script>

<?php require __DIR__ . '/../includes/footer.php'; ?>