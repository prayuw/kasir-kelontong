<?php

declare(strict_types=1);

/**
 * Escape output HTML untuk mencegah XSS.
 */
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Membentuk URL absolut dari path relatif terhadap root aplikasi.
 * Contoh: url('admin/dashboard.php') → /kasir-kelontong/admin/dashboard.php
 */
function url(string $path = ''): string
{
    return APP_BASE_PATH . '/' . ltrim($path, '/');
}

/**
 * Redirect ke halaman lain di dalam aplikasi, lalu hentikan script.
 */
function redirect(string $path): void
{
    header('Location: ' . url($path));
    exit;
}

/**
 * Format angka menjadi rupiah. Contoh: 15000 → Rp 15.000
 */
function rupiah(float|int|string $angka): string
{
    return 'Rp ' . number_format((float) $angka, 0, ',', '.');
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
}

/* ---------- CSRF ---------- */

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function csrf_valid(): bool
{
    $token = $_POST['csrf_token'] ?? '';
    return is_string($token) && $token !== '' && hash_equals(csrf_token(), $token);
}

/* ---------- Flash message ---------- */

/**
 * Menyimpan pesan sekali tampil. Tipe: success, error, info, warning.
 */
function set_flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function get_flash(): ?array
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return is_array($flash) ? $flash : null;
}

/* ---------- Halaman error sederhana ---------- */

/**
 * Menampilkan halaman error berdiri sendiri (tanpa sidebar), lalu menghentikan script.
 */
function show_error_page(int $code, string $judul, string $pesan): void
{
    http_response_code($code);

    $user = current_user();
    $link = $user ? home_path_for_role($user['role']) : 'auth/login.php';
    $label = $user ? 'Kembali ke dashboard' : 'Kembali ke halaman login';

    echo '<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1.0">'
        . '<title>' . e($judul) . '</title>'
        . '<link rel="stylesheet" href="' . e(url('assets/css/style.css')) . '"></head>'
        . '<body class="auth-body"><div class="auth-card">'
        . '<h1>' . e($judul) . '</h1>'
        . '<p class="muted">' . e($pesan) . '</p>'
        . '<a class="btn btn-primary btn-block" href="' . e(url($link)) . '">' . e($label) . '</a>'
        . '</div></body></html>';
    exit;
}

/* ---------- Helper Tahap 4 ---------- */

/**
 * Tanggal dan waktu dalam Bahasa Indonesia. Contoh: 20 Oktober 2026, 14:30
 */
function tanggal_indo(string $datetime): string
{
    $bulan = [
        '',
        'Januari',
        'Februari',
        'Maret',
        'April',
        'Mei',
        'Juni',
        'Juli',
        'Agustus',
        'September',
        'Oktober',
        'November',
        'Desember'
    ];

    $ts = strtotime($datetime);
    if ($ts === false) {
        return $datetime;
    }

    return date('j', $ts) . ' ' . $bulan[(int) date('n', $ts)] . ' ' . date('Y', $ts)
        . ', ' . date('H:i', $ts);
}

/**
 * Validasi angka bulat positif dari input formulir (tanpa titik atau koma).
 */
function is_valid_angka(string $value, int $maxDigit = 9): bool
{
    return preg_match('/^\d{1,' . $maxDigit . '}$/', $value) === 1;
}

/**
 * Status stok: habis (0), menipis (<= stok minimum), atau aman.
 */
function status_stok(int $stok, int $stokMinimum): string
{
    if ($stok <= 0) {
        return 'habis';
    }
    if ($stok <= $stokMinimum) {
        return 'menipis';
    }
    return 'aman';
}

/**
 * Lencana HTML untuk status stok.
 */
function badge_stok(int $stok, int $stokMinimum): string
{
    $status = status_stok($stok, $stokMinimum);
    $label  = ['habis' => 'Habis', 'menipis' => 'Menipis', 'aman' => 'Aman'][$status];

    return '<span class="badge badge-' . $status . '">' . e($label) . '</span>';
}

/**
 * Menghitung informasi pagination. Halaman di luar jangkauan dikoreksi otomatis.
 */
function paginate_info(int $total, int $perPage, int $page): array
{
    $totalPages = max(1, (int) ceil($total / $perPage));
    $page       = min(max(1, $page), $totalPages);

    return [
        'total'       => $total,
        'per_page'    => $perPage,
        'page'        => $page,
        'total_pages' => $totalPages,
        'offset'      => ($page - 1) * $perPage,
    ];
}

/**
 * Menampilkan tautan nomor halaman. $query berisi filter yang tetap dibawa.
 */
function render_pagination(string $path, array $query, array $pg): void
{
    if ($pg['total_pages'] <= 1) {
        return;
    }

    echo '<nav class="pagination" aria-label="Halaman">';
    for ($i = 1; $i <= $pg['total_pages']; $i++) {
        $qs    = http_build_query(array_merge($query, ['page' => $i]));
        $class = $i === $pg['page'] ? 'page-link active' : 'page-link';
        echo '<a class="' . $class . '" href="' . e(url($path) . '?' . $qs) . '">' . $i . '</a>';
    }
    echo '</nav>';
}

/* ---------- Helper Tahap 6 ---------- */

/**
 * Mengambil string dari $_GET atau $_POST dengan aman (menolak array).
 */
function input_string(array $src, string $key, int $maxLen = 255): string
{
    $v = $src[$key] ?? '';
    if (!is_string($v)) {
        return '';
    }
    return mb_substr(trim($v), 0, $maxLen);
}

/**
 * Mengurai tanggal format Y-m-d. Mengembalikan null jika tidak valid.
 */
function parse_tanggal(string $value): ?string
{
    if ($value === '') {
        return null;
    }
    $dt = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    if ($dt === false || $dt->format('Y-m-d') !== $value) {
        return null;
    }
    return $value;
}

/**
 * Tanggal saja dalam Bahasa Indonesia. Contoh: 20 Oktober 2026
 */
function tanggal_indo_saja(string $tanggal): string
{
    $bulan = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
              'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

    $ts = strtotime($tanggal);
    if ($ts === false) {
        return $tanggal;
    }
    return date('j', $ts) . ' ' . $bulan[(int) date('n', $ts)] . ' ' . date('Y', $ts);
}

function status_transaksi_label(string $status): string
{
    return ['selesai' => 'Selesai', 'batal' => 'Dibatalkan'][$status] ?? ucfirst($status);
}

function badge_status_transaksi(string $status): string {
    if ($status === 'selesai') {
        return '<span class="badge badge-success">Selesai</span>';
    } elseif ($status === 'batal') {
        return '<span class="badge badge-danger">Dibatalkan</span>';
    }
    return '<span class="badge badge-secondary">' . e($status) . '</span>';
}

/* ---------- Tombol tampilkan/sembunyikan password ---------- */

/**
 * Tombol ikon mata untuk input password dengan id $targetId.
 * Tombol memakai type="button" agar tidak ikut mengirim formulir.
 */
function tombol_toggle_password(string $targetId): string
{
    $id = e($targetId);

    return '<button type="button" class="toggle-password"'
        . ' data-toggle-password="' . $id . '"'
        . ' aria-label="Tampilkan password"'
        . ' aria-pressed="false"'
        . ' aria-controls="' . $id . '"'
        . ' title="Tampilkan password">'
        // Ikon mata terbuka (tampil saat password disembunyikan)
        . '<svg class="icon-eye" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"'
        . ' stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
        . '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>'
        // Ikon mata dicoret (tampil saat password terlihat)
        . '<svg class="icon-eye-off" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"'
        . ' stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
        . '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/>'
        . '<path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/>'
        . '<path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>'
        . '</button>';
}
