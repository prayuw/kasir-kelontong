<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

if (!is_post() || !csrf_valid()) {
    redirect('index.php');
}

logout_user();

// Sesi baru dibuat kembali agar pesan konfirmasi bisa tampil di halaman login
session_start();
set_flash('success', 'Anda berhasil logout.');
redirect('auth/login.php');