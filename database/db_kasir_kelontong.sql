-- =====================================================
-- Database : db_kasir_kelontong
-- Sistem   : Kasir Toko Kelontong Berbasis Web
-- Engine   : InnoDB, charset utf8mb4
-- =====================================================

CREATE DATABASE IF NOT EXISTS db_kasir_kelontong CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE db_kasir_kelontong;

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS stock_movements;
DROP TABLE IF EXISTS transaction_details;
DROP TABLE IF EXISTS transactions;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS store_settings;
DROP TABLE IF EXISTS users;
SET FOREIGN_KEY_CHECKS = 1;

-- -----------------------------------------------------
-- 1. users: data admin dan kasir
-- -----------------------------------------------------
CREATE TABLE users (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    username VARCHAR(50) NOT NULL,
    password VARCHAR(255) NOT NULL, -- hasil password_hash()
    nama_lengkap VARCHAR(100) NOT NULL,
    role ENUM('admin', 'kasir') NOT NULL DEFAULT 'kasir',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT uq_users_username UNIQUE (username)
) ENGINE = InnoDB;

-- -----------------------------------------------------
-- 2. categories: kategori barang
-- -----------------------------------------------------
CREATE TABLE categories (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nama_kategori VARCHAR(100) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT uq_categories_nama UNIQUE (nama_kategori)
) ENGINE = InnoDB;

-- -----------------------------------------------------
-- 3. products: data produk dan stok
-- -----------------------------------------------------
CREATE TABLE products (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    kode_barang VARCHAR(30) NOT NULL,
    nama_barang VARCHAR(150) NOT NULL,
    category_id INT UNSIGNED NULL,
    satuan VARCHAR(20) NOT NULL DEFAULT 'pcs',
    harga_modal DECIMAL(12, 2) NOT NULL DEFAULT 0,
    harga_jual DECIMAL(12, 2) NOT NULL DEFAULT 0,
    stok INT NOT NULL DEFAULT 0,
    stok_minimum INT NOT NULL DEFAULT 5,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT uq_products_kode UNIQUE (kode_barang),
    CONSTRAINT chk_products_harga CHECK (
        harga_modal >= 0
        AND harga_jual >= 0
    ),
    CONSTRAINT chk_products_stok CHECK (
        stok >= 0
        AND stok_minimum >= 0
    ),
    CONSTRAINT fk_products_category FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE = InnoDB;

-- -----------------------------------------------------
-- 4. transactions: data utama transaksi
-- -----------------------------------------------------
CREATE TABLE transactions (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    kode_transaksi VARCHAR(30) NOT NULL, -- contoh: TRX-20261020-0001
    user_id INT UNSIGNED NOT NULL, -- kasir yang melayani
    total_item INT UNSIGNED NOT NULL DEFAULT 0,
    total_belanja DECIMAL(12, 2) NOT NULL,
    uang_bayar DECIMAL(12, 2) NOT NULL,
    kembalian DECIMAL(12, 2) NOT NULL,
    total_modal DECIMAL(12, 2) NOT NULL DEFAULT 0,
    laba_kotor DECIMAL(12, 2) NOT NULL DEFAULT 0,
    status ENUM('selesai', 'batal') NOT NULL DEFAULT 'selesai',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT uq_transactions_kode UNIQUE (kode_transaksi),
    CONSTRAINT chk_transactions_nilai CHECK (
        total_belanja >= 0
        AND uang_bayar >= total_belanja
        AND kembalian >= 0
    ),
    CONSTRAINT fk_transactions_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    INDEX idx_transactions_created (created_at)
) ENGINE = InnoDB;

-- -----------------------------------------------------
-- 5. transaction_details: rincian barang per transaksi
--    Harga disalin (snapshot) agar perubahan harga tidak mengubah riwayat.
-- -----------------------------------------------------
CREATE TABLE transaction_details (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    transaction_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    nama_barang VARCHAR(150) NOT NULL, -- snapshot nama
    harga_modal DECIMAL(12, 2) NOT NULL, -- snapshot modal
    harga_jual DECIMAL(12, 2) NOT NULL, -- snapshot jual
    qty INT UNSIGNED NOT NULL,
    subtotal DECIMAL(12, 2) NOT NULL,
    laba_kotor DECIMAL(12, 2) NOT NULL,
    PRIMARY KEY (id),
    CONSTRAINT chk_detail_qty CHECK (qty > 0),
    CONSTRAINT fk_detail_transaction FOREIGN KEY (transaction_id) REFERENCES transactions (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_detail_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    INDEX idx_detail_transaction (transaction_id),
    INDEX idx_detail_product (product_id)
) ENGINE = InnoDB;

-- -----------------------------------------------------
-- 6. stock_movements: riwayat perubahan stok
--    qty bertanda: positif = stok masuk, negatif = stok keluar
-- -----------------------------------------------------
CREATE TABLE stock_movements (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    product_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    jenis ENUM(
        'masuk',
        'penjualan',
        'koreksi',
        'pembatalan'
    ) NOT NULL,
    qty INT NOT NULL,
    stok_sebelum INT NOT NULL,
    stok_sesudah INT NOT NULL,
    referensi VARCHAR(50) NULL, -- contoh: kode transaksi
    keterangan VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_stock_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_stock_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    INDEX idx_stock_product (product_id),
    INDEX idx_stock_created (created_at)
) ENGINE = InnoDB;

-- -----------------------------------------------------
-- 7. store_settings: informasi toko untuk struk (1 baris)
-- -----------------------------------------------------
CREATE TABLE store_settings (
    id TINYINT UNSIGNED NOT NULL DEFAULT 1,
    nama_toko VARCHAR(100) NOT NULL,
    alamat VARCHAR(255) NULL,
    telepon VARCHAR(30) NULL,
    pesan_struk VARCHAR(255) NULL,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT chk_settings_singleton CHECK (id = 1)
) ENGINE = InnoDB;

-- =====================================================
-- DATA AWAL
-- =====================================================

-- Pengaturan toko
INSERT INTO
    store_settings (
        id,
        nama_toko,
        alamat,
        telepon,
        pesan_struk
    )
VALUES (
        1,
        'Toko Kelontong Berkah Jaya',
        'Jl. Contoh No. 1, Jember',
        '0812-0000-0000',
        'Terima kasih sudah berbelanja. Barang yang sudah dibeli tidak dapat dikembalikan.'
    );

-- Akun awal. Password default untuk kedua akun: password
-- Hash ini dibuat dengan password_hash() dan wajib diganti setelah login pertama.
INSERT INTO
    users (
        username,
        password,
        nama_lengkap,
        role,
        is_active
    )
VALUES (
        'admin',
        '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
        'Pemilik Toko',
        'admin',
        1
    ),
    (
        'kasir1',
        '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
        'Kasir Satu',
        'kasir',
        1
    );

-- Kategori
INSERT INTO
    categories (nama_kategori)
VALUES ('Sembako'),
    ('Makanan Ringan'),
    ('Minuman'),
    ('Mi Instan'),
    ('Perlengkapan Mandi'),
    ('Kebutuhan Rumah Tangga');

-- Contoh produk
INSERT INTO
    products (
        kode_barang,
        nama_barang,
        category_id,
        satuan,
        harga_modal,
        harga_jual,
        stok,
        stok_minimum
    )
VALUES (
        'SMB-001',
        'Beras Premium 5 kg',
        1,
        'karung',
        62000,
        68000,
        20,
        3
    ),
    (
        'SMB-002',
        'Minyak Goreng 1 L',
        1,
        'botol',
        14000,
        16000,
        4,
        5
    ),
    (
        'SMB-003',
        'Gula Pasir 1 kg',
        1,
        'bungkus',
        13000,
        15000,
        15,
        5
    ),
    (
        'MRG-001',
        'Keripik Kentang 68 g',
        2,
        'bungkus',
        7000,
        9000,
        30,
        6
    ),
    (
        'MRG-002',
        'Biskuit Coklat 110 g',
        2,
        'bungkus',
        5500,
        7000,
        3,
        5
    ),
    (
        'MNM-001',
        'Air Mineral 600 ml',
        3,
        'botol',
        2800,
        4000,
        48,
        12
    ),
    (
        'MNM-002',
        'Kopi Sachet Susu',
        3,
        'sachet',
        1500,
        2000,
        60,
        10
    ),
    (
        'MIN-001',
        'Mi Instan Goreng',
        4,
        'bungkus',
        2800,
        3500,
        80,
        20
    ),
    (
        'MDI-001',
        'Sabun Mandi Batang 90 g',
        5,
        'batang',
        3500,
        4500,
        12,
        5
    ),
    (
        'MDI-002',
        'Sampo Sachet 10 ml',
        5,
        'sachet',
        700,
        1000,
        100,
        20
    ),
    (
        'RMT-001',
        'Sabun Cuci Piring 800 ml',
        6,
        'botol',
        12000,
        15000,
        2,
        4
    ),
    (
        'RMT-002',
        'Deterjen Bubuk 800 g',
        6,
        'bungkus',
        17000,
        21000,
        9,
        3
    );

-- Catatan: stok awal di atas diisi langsung. Riwayat stok_movements
-- dimulai saat transaksi atau barang masuk pertama dicatat lewat aplikasi.