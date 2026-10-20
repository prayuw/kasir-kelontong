<?php
declare(strict_types=1);

// Setiap halaman harus mengisi variabel ini sebelum include header
$pageTitle  = $pageTitle ?? APP_NAME;
$activeMenu = $activeMenu ?? '';

$user = current_user();
if (!$user) {
    redirect('auth/login.php');
}

$flash = get_flash();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> | <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">
</head>
<body>
<div class="app-layout">

    <?php require __DIR__ . '/sidebar.php'; ?>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <div class="main-wrapper">
        <header class="topbar">
            <button type="button" class="menu-toggle" id="menuToggle" aria-label="Buka menu">☰</button>
            <h1 class="topbar-title"><?= e($pageTitle) ?></h1>
            <div class="user-badge">
                <strong><?= e($user['nama_lengkap']) ?></strong>
                <span class="role-tag role-<?= e($user['role']) ?>"><?= e(ucfirst($user['role'])) ?></span>
            </div>
        </header>

        <main class="content">
            <?php if ($flash): ?>
                <div class="alert alert-<?= e($flash['type']) ?>" data-autohide>
                    <?= e($flash['message']) ?>
                </div>
            <?php endif; ?>