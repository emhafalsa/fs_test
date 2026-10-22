<?php
/**
 * HEADER + NAVBAR
 * -----------------------------------------------------------------
 * Dipakai setiap halaman. Mengatur <head>, membuka <body>,
 * sidebar, dan navbar.
 *
 * Variabel opsional yang bisa diset SEBELUM meng-include file ini:
 *   $pageTitle  -> judul halaman (default: "Dashboard")
 *   $activeMenu -> menu aktif di sidebar (dashboard|files|upload|category|settings)
 */

require_once __DIR__ . '/bootstrap.php';

$pageTitle  = $pageTitle  ?? 'Dashboard';
$activeMenu = $activeMenu ?? 'dashboard';
$flash      = take_flash();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> | Sistem Penyimpanan File</title>
    <link rel="stylesheet" href="assets/css/style.css?v=1">
</head>
<body>

<!-- Overlay gelap saat sidebar dibuka di mode mobile -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<div class="app-layout">

    <?php require __DIR__ . '/sidebar.php'; ?>

    <div class="main-wrapper">

        <!-- NAVBAR -->
        <header class="navbar">
            <button type="button" class="btn-icon navbar-toggle" id="sidebarToggle"
                    aria-label="Buka menu" aria-expanded="false">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                     stroke-linecap="round" aria-hidden="true">
                    <line x1="3" y1="6" x2="21" y2="6"></line>
                    <line x1="3" y1="12" x2="21" y2="12"></line>
                    <line x1="3" y1="18" x2="21" y2="18"></line>
                </svg>
            </button>

            <div class="navbar-title">
                <h1><?= e($pageTitle) ?></h1>
            </div>

            <div class="navbar-right">
                <span class="user-chip">
                    <span class="user-avatar" aria-hidden="true"><?= e(strtoupper(substr(current_user(), 0, 1))) ?></span>
                    <span class="user-name"><?= e(current_user()) ?></span>
                </span>

                <form method="POST" action="logout.php" class="d-inline">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-outline btn-sm">Logout</button>
                </form>
            </div>
        </header>

        <main class="content">
