<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function json_keluar(int $status, array $data): void
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

if (!is_logged_in()) {
    json_keluar(401, ['ok' => false, 'message' => 'Sesi login sudah berakhir. Silakan login ulang.']);
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    json_keluar(405, ['ok' => false, 'message' => 'Metode tidak diizinkan.']);
}

$q          = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 100);
$kategoriId = (int) ($_GET['kategori'] ?? 0);

$where  = ['p.is_active = 1'];
$params = [];

if ($q !== '') {
    // Escape karakter khusus LIKE agar pencarian berjalan sesuai ketikan
    $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';
    $where[]  = '(p.nama_barang LIKE ? OR p.kode_barang LIKE ?)';
    $params[] = $like;
    $params[] = $like;
}
if ($kategoriId > 0) {
    $where[]  = 'p.category_id = ?';
    $params[] = $kategoriId;
}

$stmt = db()->prepare(
    'SELECT p.id, p.kode_barang, p.nama_barang, p.satuan, p.harga_jual, p.stok, p.stok_minimum
       FROM products p
      WHERE ' . implode(' AND ', $where) . '
      ORDER BY p.nama_barang ASC
      LIMIT 30'
);
$stmt->execute($params);

$items = array_map(static fn (array $r): array => [
    'id'           => (int) $r['id'],
    'kode_barang'  => $r['kode_barang'],
    'nama_barang'  => $r['nama_barang'],
    'satuan'       => $r['satuan'],
    'harga_jual'   => (int) $r['harga_jual'],
    'stok'         => (int) $r['stok'],
    'stok_minimum' => (int) $r['stok_minimum'],
], $stmt->fetchAll());

json_keluar(200, ['ok' => true, 'items' => $items]);