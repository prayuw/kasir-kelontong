<?php
declare(strict_types=1);

/*
 * Konfigurasi koneksi database menggunakan PDO.
 * Default Laragon: user "root" dan password kosong.
 * Jika password MySQL Anda berbeda, ubah DB_PASS.
 */

const DB_HOST    = 'localhost';
const DB_NAME    = 'db_kasir_kelontong';
const DB_USER    = 'root';
const DB_PASS    = '';
const DB_CHARSET = 'utf8mb4';

/**
 * Mengembalikan satu koneksi PDO yang dipakai ulang (singleton sederhana).
 */
function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;

    $options = [
        // Error dilempar sebagai exception agar mudah ditangkap dan ditangani
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        // Prepared statement sungguhan dari MySQL, bukan emulasi PHP
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    } catch (PDOException $e) {
        // Detail error hanya masuk ke log, tidak ditampilkan ke pengguna
        error_log('[DB] Koneksi gagal: ' . $e->getMessage());
        http_response_code(500);
        exit('Koneksi database gagal. Pastikan MySQL di Laragon sudah berjalan dan pengaturan di config/database.php sudah benar.');
    }

    return $pdo;
}