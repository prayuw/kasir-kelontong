<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_role(ROLE_ADMIN);

/**
 * Membuat isi backup database dalam bentuk SQL dan langsung mengirimkannya ke browser.
 * Fungsi ini selalu menghentikan script setelah selesai.
 */
function unduh_backup(PDO $pdo): void
{
    $namaFile = 'backup_db_kasir_kelontong_' . date('Ymd_His') . '.sql';
    $dbName   = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();

    error_log('[BACKUP] ' . current_user()['username'] . ' mengunduh ' . $namaFile);

    header('Content-Type: application/sql; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $namaFile . '"');
    header('Cache-Control: no-store');

    echo "-- Backup database: {$dbName}\n";
    echo '-- Dibuat: ' . date('Y-m-d H:i:s') . " (Asia/Jakarta)\n\n";
    echo "SET NAMES utf8mb4;\n";
    echo "SET FOREIGN_KEY_CHECKS = 0;\n\n";

    $daftarTabel = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);

    // Struktur tabel
    foreach ($daftarTabel as $tabel) {
        $nama   = '`' . str_replace('`', '``', (string) $tabel) . '`';
        $create = $pdo->query('SHOW CREATE TABLE ' . $nama)->fetch(PDO::FETCH_NUM)[1];

        echo "DROP TABLE IF EXISTS {$nama};\n";
        echo $create . ";\n\n";
    }

    // Isi tabel
    foreach ($daftarTabel as $tabel) {
        $nama  = '`' . str_replace('`', '``', (string) $tabel) . '`';
        $rows  = $pdo->query('SELECT * FROM ' . $nama)->fetchAll(PDO::FETCH_ASSOC);

        if (empty($rows)) {
            continue;
        }

        $kolom = implode(', ', array_map(
            static fn (string $c): string => '`' . str_replace('`', '``', $c) . '`',
            array_keys($rows[0])
        ));

        echo "-- Data tabel {$nama}\n";
        foreach ($rows as $row) {
            $nilai = [];
            foreach ($row as $v) {
                // quote() mengamankan karakter khusus seperti tanda kutip dan baris baru
                $nilai[] = $v === null ? 'NULL' : $pdo->quote((string) $v);
            }
            echo "INSERT INTO {$nama} ({$kolom}) VALUES (" . implode(', ', $nilai) . ");\n";
        }
        echo "\n";
    }

    echo "SET FOREIGN_KEY_CHECKS = 1;\n";
    exit;
}

$pdo = db();

// Unduhan hanya diterima lewat POST dengan token CSRF yang valid
if (is_post()) {
    if (!csrf_valid()) {
        set_flash('error', 'Sesi formulir tidak valid. Silakan coba lagi.');
        redirect('admin/backup.php');
    }
    unduh_backup($pdo);
}

$pageTitle  = 'Backup Database';
$activeMenu = 'backup';

$jumlahTabel = (int) $pdo->query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()')->fetchColumn();
$ukuranMB    = (float) $pdo->query(
    'SELECT COALESCE(SUM(data_length + index_length), 0) / 1024 / 1024
       FROM information_schema.tables WHERE table_schema = DATABASE()'
)->fetchColumn();

require __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h2>Backup Database</h2>
        <p class="muted">Simpan salinan data toko secara berkala, misalnya seminggu sekali.</p>
    </div>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-label">Jumlah Tabel</div>
        <div class="stat-value"><?= $jumlahTabel ?></div>
        <div class="stat-hint">Dalam db_kasir_kelontong</div>
    </div>
    <div class="stat-card info">
        <div class="stat-label">Perkiraan Ukuran Data</div>
        <div class="stat-value"><?= e(number_format($ukuranMB, 2, ',', '.')) ?> MB</div>
        <div class="stat-hint">Ukuran file backup biasanya lebih kecil</div>
    </div>
</div>

<div class="grid-2">
    <section class="card">
        <h2>Unduh Backup</h2>
        <p class="muted">File berformat <code>.sql</code> berisi struktur dan seluruh data. Simpan di tempat yang aman, misalnya flashdisk atau Google Drive pribadi.</p>

        <form method="post" action="<?= e(url('admin/backup.php')) ?>" style="margin-top:14px;">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-primary"
                    data-confirm="Buat dan unduh file backup sekarang?">Unduh Backup (.sql)</button>
        </form>
    </section>

    <section class="card">
        <h2>Cara Memulihkan Data</h2>
        <ol style="padding-left:18px; margin:0;">
            <li style="margin-bottom:8px;">Buka phpMyAdmin, lalu pilih database <code>db_kasir_kelontong</code>.</li>
            <li style="margin-bottom:8px;">Pilih tab <strong>Import</strong>, lalu pilih file backup.</li>
            <li style="margin-bottom:8px;">Klik <strong>Go</strong>. Semua tabel dan data akan diganti dengan isi backup.</li>
        </ol>
        <p class="hint" style="margin-top:10px;">Pemulihan menimpa data yang sekarang. Buat backup terbaru sebelum memulihkan data lama.</p>
    </section>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>