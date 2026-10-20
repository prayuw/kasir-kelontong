<?php

declare(strict_types=1);

/*
 * Pengaturan umum aplikasi.
 */

const APP_NAME    = 'Kasir Kelontong Berkah Jaya';
const APP_VERSION = '1.0.0';

// Sesuaikan jika nama folder proyek berbeda
const APP_BASE_PATH = '/kasir-kelontong';

// Peran yang dikenali sistem
const ROLE_ADMIN = 'admin';
const ROLE_KASIR = 'kasir';

// Nilai awal ambang stok menipis jika produk belum mengatur stok_minimum
const DEFAULT_STOK_MINIMUM = 5;

// Zona waktu Indonesia Barat (Jember, Jawa Timur)
date_default_timezone_set('Asia/Jakarta');

ini_set('display_errors', '0');
error_reporting(E_ALL);
ini_set('log_errors', '1');