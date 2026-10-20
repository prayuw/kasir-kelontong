<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

// Session dimulai dengan pengaturan keamanan dasar
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => APP_BASE_PATH . '/',
        'httponly' => true,   // cookie tidak bisa dibaca JavaScript
        'samesite' => 'Lax',
    ]);
    session_start();
}

// Header keamanan dasar untuk setiap halaman
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: same-origin');
}

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';

// Sesi yang sudah terlalu lama tidak aktif langsung dihentikan
enforce_session_timeout();