<?php
declare(strict_types=1);

/* ---------- Konstanta keamanan ---------- */
const LOGIN_WINDOW_SECONDS = 900;     // jendela penghitungan: 15 menit
const LOGIN_MAX_PER_USER   = 5;       // gagal per username dalam jendela
const LOGIN_MAX_PER_IP     = 20;      // gagal per IP dalam jendela (melindungi dari tebakan massal)
const SESSION_IDLE_SECONDS = 1800;    // sesi berakhir setelah 30 menit tidak ada aktivitas

/**
 * Halaman awal setiap role setelah login.
 */
function home_path_for_role(string $role): string
{
    return $role === ROLE_ADMIN ? 'admin/dashboard.php' : 'kasir/transaksi.php';
}

/**
 * Mengambil data pengguna yang sedang login dari database.
 * Status aktif diperiksa setiap kali, sehingga akun yang dinonaktifkan langsung tidak bisa dipakai.
 */
function current_user(): ?array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    $id = $_SESSION['user_id'] ?? null;
    if ($id === null) {
        return null;
    }

    $stmt = db()->prepare(
        'SELECT id, username, nama_lengkap, role, is_active
           FROM users
          WHERE id = ?
          LIMIT 1'
    );
    $stmt->execute([(int) $id]);
    $row = $stmt->fetch();

    if (!$row || (int) $row['is_active'] !== 1) {
        unset($_SESSION['user_id']);
        return null;
    }

    $cache = $row;
    return $cache;
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

/**
 * Menyimpan sesi login setelah password benar.
 * session_regenerate_id mencegah session fixation.
 */
function login_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user_id']       = (int) $user['id'];
    $_SESSION['last_activity'] = time();
    unset($_SESSION['session_expired']);
}

/**
 * Menghapus seluruh sesi dan cookie session.
 */
function logout_user(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }

    session_destroy();
}

/**
 * Menghentikan sesi yang sudah melewati batas waktu tidak aktif.
 * Dipanggil dari bootstrap.php di setiap halaman.
 */
function enforce_session_timeout(): void
{
    if (empty($_SESSION['user_id'])) {
        return;
    }

    $now  = time();
    $last = (int) ($_SESSION['last_activity'] ?? $now);

    if ($now - $last > SESSION_IDLE_SECONDS) {
        logout_user();
        session_start();
        $_SESSION['session_expired'] = true;
        return;
    }

    $_SESSION['last_activity'] = $now;
}

/**
 * Halaman wajib login. Jika belum login, diarahkan ke halaman login.
 */
function require_login(): void
{
    if (!is_logged_in()) {
        $expired = !empty($_SESSION['session_expired']);
        unset($_SESSION['session_expired']);

        set_flash('info', $expired
            ? 'Sesi Anda berakhir karena tidak ada aktivitas. Silakan login kembali.'
            : 'Silakan login terlebih dahulu.');
        redirect('auth/login.php');
    }
}

/**
 * Membatasi halaman untuk role tertentu. Pemeriksaan dilakukan di server.
 * Contoh: require_role(ROLE_ADMIN);
 */
function require_role(string ...$roles): void
{
    require_login();
    $user = current_user();

    if (!in_array($user['role'], $roles, true)) {
        show_error_page(403, 'Akses Ditolak', 'Anda tidak memiliki izin untuk membuka halaman ini.');
    }
}

/* ---------- Login ---------- */

/**
 * Alamat IP pengunjung. Header seperti X-Forwarded-For sengaja tidak dipercaya
 * karena bisa dipalsukan oleh klien.
 */
function client_ip(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45);
}

/**
 * Memvalidasi username dan password menggunakan prepared statement.
 * Mengembalikan array: ['ok' => bool, 'user' => array|null, 'message' => string]
 */
function attempt_login(string $username, string $password): array
{
    $pdo         = db();
    $usernameKey = mb_strtolower($username);
    $ip          = client_ip();
    $sejak       = date('Y-m-d H:i:s', time() - LOGIN_WINDOW_SECONDS);

    // Bersihkan catatan yang sudah lebih dari sehari agar tabel tidak membesar
    $pdo->prepare('DELETE FROM login_attempts WHERE attempted_at < ?')
        ->execute([date('Y-m-d H:i:s', time() - 86400)]);

    // Hitung kegagalan per username dan per IP dalam jendela waktu
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM login_attempts WHERE username = ? AND attempted_at >= ?');
    $stmt->execute([$usernameKey, $sejak]);
    $gagalUser = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM login_attempts WHERE ip_address = ? AND attempted_at >= ?');
    $stmt->execute([$ip, $sejak]);
    $gagalIp = (int) $stmt->fetchColumn();

    // Jika sudah melewati batas, password tidak diperiksa sama sekali
    if ($gagalUser >= LOGIN_MAX_PER_USER || $gagalIp >= LOGIN_MAX_PER_IP) {
        return [
            'ok'      => false,
            'user'    => null,
            'message' => 'Terlalu banyak percobaan login gagal. Coba lagi dalam 15 menit.',
        ];
    }

    $stmt = $pdo->prepare(
        'SELECT id, password, nama_lengkap, role, is_active
           FROM users
          WHERE username = ?
          LIMIT 1'
    );
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    $cocok = $user !== false
        && password_verify($password, $user['password'])
        && (int) $user['is_active'] === 1;

    if (!$cocok) {
        $ins = $pdo->prepare('INSERT INTO login_attempts (ip_address, username, attempted_at) VALUES (?, ?, ?)');
        $ins->execute([$ip, $usernameKey, date('Y-m-d H:i:s')]);

        // Pesan dibuat umum agar tidak memberi tahu apakah username itu ada
        return [
            'ok'      => false,
            'user'    => null,
            'message' => 'Username atau password salah, atau akun tidak aktif.',
        ];
    }

    // Login berhasil: hitungan kegagalan untuk username ini direset
    $pdo->prepare('DELETE FROM login_attempts WHERE username = ?')->execute([$usernameKey]);

    // Jika algoritma hash berubah di masa depan, hash diperbarui otomatis
    if (password_needs_rehash($user['password'], PASSWORD_DEFAULT)) {
        $upd = $pdo->prepare('UPDATE users SET password = ? WHERE id = ?');
        $upd->execute([password_hash($password, PASSWORD_DEFAULT), (int) $user['id']]);
    }

    return ['ok' => true, 'user' => $user, 'message' => ''];
}