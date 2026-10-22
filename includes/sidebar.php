<?php
/**
 * SIDEBAR NAVIGATION
 * -----------------------------------------------------------------
 * Menu navigasi utama. Variabel $activeMenu (di-set di halaman)
 * menentukan menu mana yang mendapat gaya "aktif".
 *
 * $activeMenu berisi salah satu dari:
 *   dashboard | files | upload | category | settings
 */

$activeMenu = $activeMenu ?? 'dashboard';

// Daftar menu. Menambah menu cukup menambah satu baris di sini.
$menuItems = [
    [
        'key'   => 'dashboard',
        'label' => 'Dashboard',
        'url'   => 'dashboard.php',
        'icon'  => '<rect x="3" y="3" width="7" height="9" rx="1"></rect>'
                 . '<rect x="14" y="3" width="7" height="5" rx="1"></rect>'
                 . '<rect x="14" y="12" width="7" height="9" rx="1"></rect>'
                 . '<rect x="3" y="16" width="7" height="5" rx="1"></rect>',
    ],
    [
        'key'   => 'files',
        'label' => 'Semua File',
        'url'   => 'index.php',
        'icon'  => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>'
                 . '<polyline points="14 2 14 8 20 8"></polyline>',
    ],
    [
        'key'   => 'upload',
        'label' => 'Upload File',
        'url'   => 'upload.php',
        'icon'  => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>'
                 . '<polyline points="17 8 12 3 7 8"></polyline>'
                 . '<line x1="12" y1="3" x2="12" y2="15"></line>',
    ],
    [
        'key'   => 'category',
        'label' => 'Kategori',
        'url'   => 'category.php',
        'icon'  => '<path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>',
    ],
    [
        'key'   => 'settings',
        'label' => 'Pengaturan',
        'url'   => 'settings.php',
        'icon'  => '<circle cx="12" cy="12" r="3"></circle>'
                 . '<path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.6a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"></path>',
    ],
];
?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <span class="brand-mark" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                 stroke-linecap="round" stroke-linejoin="round">
                <path d="M4 4h5l2 2h9a1 1 0 0 1 1 1v11a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1z"></path>
            </svg>
        </span>
        <span class="brand-text">FileStorage</span>
        <button type="button" class="btn-icon sidebar-close" id="sidebarClose" aria-label="Tutup menu">&times;</button>
    </div>

    <nav class="sidebar-nav" aria-label="Navigasi utama">
        <ul>
            <?php foreach ($menuItems as $item): ?>
                <?php $isActive = ($activeMenu === $item['key']); ?>
                <li>
                    <a href="<?= e($item['url']) ?>"
                       class="nav-link<?= $isActive ? ' active' : '' ?>"
                       <?= $isActive ? 'aria-current="page"' : '' ?>>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                             stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <?= $item['icon'] ?>
                        </svg>
                        <span><?= e($item['label']) ?></span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </nav>

    <div class="sidebar-footer">
        <p>Versi 1.0</p>
    </div>
</aside>
