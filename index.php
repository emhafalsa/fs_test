<?php
/**
 * INDEX.PHP
 * -----------------------------------------------------------------
 * Ada dua fungsi sekaligus:
 *   1. Halaman LOGIN (ketika belum login)
 *   2. Halaman DAFTAR SEMUA FILE (setelah login)
 *
 * Dipakai juga sebagai menu "Semua File" di sidebar.
 */

require_once __DIR__ . '/includes/bootstrap.php';

// ============================================================
// BAGIAN 1: LOGIN
// ============================================================

if (! is_logged_in()) {
    $error = null;
    $oldUser = '';

    // Proses submit form login
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_verify();

        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $oldUser  = $username;

        // Password dicek dengan password_verify() terhadap hash yang
        // disimpan di storage_settings.json (diubah lewat settings.php).
        if (verify_login($username, $password)) {
            // Cegah session fixation: buat ID session baru setelah login
            session_regenerate_id(true);
            $_SESSION['auth_user'] = $username;
            flash('success', 'Selamat datang, ' . $username . '!');
            redirect('dashboard.php');
        }

        $error = 'Username atau password salah.';
    }
    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Login | Sistem Penyimpanan File</title>
        <link rel="stylesheet" href="assets/css/style.css?v=1">
    </head>
    <body class="login-page">

        <div class="login-card">
            <div class="login-logo">
                <span class="brand-mark" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                         stroke-linecap="round" stroke-linejoin="round">
                        <path d="M4 4h5l2 2h9a1 1 0 0 1 1 1v11a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1z"></path>
                    </svg>
                </span>
                <h1>FileStorage</h1>
            </div>
            <p class="login-sub">Sistem Penyimpanan dan Pengelolaan File</p>

            <?php if ($error): ?>
                <div class="alert alert-error" role="alert">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="8" x2="12" y2="12"></line>
                        <line x1="12" y1="16" x2="12.01" y2="16"></line>
                    </svg>
                    <div><?= e($error) ?></div>
                </div>
            <?php endif; ?>

            <form method="POST" action="index.php" data-loading>
                <?= csrf_field() ?>

                <div class="form-row">
                    <label class="form-label" for="username">Username</label>
                    <input class="form-control" type="text" name="username" id="username"
                           value="<?= e($oldUser) ?>" required autofocus
                           autocomplete="username" placeholder="admin">
                </div>

                <div class="form-row">
                    <label class="form-label" for="password">Password</label>
                    <input class="form-control" type="password" name="password" id="password"
                           required autocomplete="current-password" placeholder="••••••••">
                </div>

                <button type="submit" class="btn btn-primary btn-block">
                    <span class="spinner" aria-hidden="true"></span>
                    <span>Masuk</span>
                </button>
            </form>

            <?php if (! is_file(SETTINGS_FILE)): ?>
                <p class="login-hint">
                    Demo &rarr; <code><?= e(DEMO_USER) ?></code> / <code><?= e(DEMO_PASS) ?></code>
                </p>
            <?php else: ?>
                <p class="login-hint">Ganti kredensial di halaman Pengaturan.</p>
            <?php endif; ?>
        </div>

        <script src="assets/js/script.js?v=1"></script>
    </body>
    </html>
    <?php
    exit;
}

// ============================================================
// BAGIAN 2: DAFTAR SEMUA FILE
// ============================================================

require_login();

// Ambil parameter dari URL (sudah di-escape saat ditampilkan)
$search   = trim((string) ($_GET['search'] ?? ''));
$category = (string) ($_GET['category'] ?? '');
$sort     = (string) ($_GET['sort'] ?? 'uploaded_at');
$dir      = (string) ($_GET['dir'] ?? 'desc');
$page     = (int) ($_GET['page'] ?? 1);

$result = list_files([
    'search'   => $search,
    'category' => $category,
    'sort'     => $sort,
    'dir'      => $dir,
    'page'     => $page,
]);

$files    = $result['data'];
$total    = $result['total'];
$curPage  = $result['page'];
$perPage  = $result['per_page'];
$lastPage = $result['pages'];

$categories = all_categories();

// Hitung rentang data yang sedang ditampilkan
$from = $total === 0 ? 0 : (($curPage - 1) * $perPage) + 1;
$to   = min($total, $curPage * $perPage);

// Helper untuk membuat URL pagination/filter
$urlFor = static function (array $overrides = []) use ($search, $category, $sort, $dir): string {
    return build_list_url(array_merge([
        'search'   => $search,
        'category' => $category,
        'sort'     => $sort,
        'dir'      => $dir,
    ], $overrides));
};

$pageTitle  = 'Semua File';
$activeMenu = 'files';
require __DIR__ . '/includes/header.php';
?>

<!-- Page head -->
<div class="page-head">
    <div>
        <h2>Daftar File</h2>
        <p>
            <?= e((string) $total) ?> file ditemukan<?= $search !== '' ? ' untuk "' . e($search) . '"' : '' ?>
        </p>
    </div>
    <div style="display:flex; gap:8px; flex-wrap:wrap;">
        <a href="upload.php" class="btn btn-outline">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                 stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                <polyline points="17 8 12 3 7 8"></polyline>
                <line x1="12" y1="3" x2="12" y2="15"></line>
            </svg>
            Upload
        </a>
        <button type="button" class="btn btn-primary" data-modal-open>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                 stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                <polyline points="17 8 12 3 7 8"></polyline>
                <line x1="12" y1="3" x2="12" y2="15"></line>
            </svg>
            Upload Cepat
        </button>
    </div>
</div>

<!-- Card: filter + tabel -->
<div class="card">

    <!-- Toolbar: search, filter kategori, sorting -->
    <div class="card-header">
        <form method="GET" action="index.php" id="filterForm" data-loading
              style="display:flex; gap:10px; flex-wrap:wrap; width:100%;">

            <!-- Search -->
            <div class="search-wrap">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                     stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <circle cx="11" cy="11" r="8"></circle>
                    <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                </svg>
                <input class="form-control" type="search" name="search" id="searchInput"
                       value="<?= e($search) ?>" placeholder="Cari nama file, deskripsi...">
            </div>

            <!-- Filter kategori -->
            <select class="form-control" name="category" data-auto-submit
                    style="flex:0 1 190px;" aria-label="Filter kategori">
                <option value="">Semua Kategori</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= e((string) $cat['id']) ?>"
                            <?= $category === (string) $cat['id'] ? 'selected' : '' ?>>
                        <?= e($cat['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <!-- Sorting -->
            <select class="form-control" name="sort" data-auto-submit
                    style="flex:0 1 165px;" aria-label="Urutkan berdasarkan">
                <option value="uploaded_at"   <?= $sort === 'uploaded_at' ? 'selected' : '' ?>>Tanggal</option>
                <option value="file_name"     <?= $sort === 'file_name' ? 'selected' : '' ?>>Nama File</option>
                <option value="original_name" <?= $sort === 'original_name' ? 'selected' : '' ?>>Nama Asli</option>
                <option value="file_type"     <?= $sort === 'file_type' ? 'selected' : '' ?>>Tipe</option>
                <option value="file_size"     <?= $sort === 'file_size' ? 'selected' : '' ?>>Ukuran</option>
                <option value="category"      <?= $sort === 'category' ? 'selected' : '' ?>>Kategori</option>
            </select>

            <!-- Arah sort (hidden, diubah JS lewat header tabel) -->
            <input type="hidden" name="dir" id="dirInput" value="<?= e($dir) ?>">
            <input type="hidden" name="page" value="1">

            <button type="submit" class="btn btn-outline btn-sm" data-filter-submit>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                     stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="11" cy="11" r="8"></circle>
                    <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                </svg>
                Cari
            </button>

            <?php if ($search !== '' || $category !== ''): ?>
                <a href="index.php" class="btn btn-outline btn-sm">Reset</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Indikator loading (dikelola JS) -->
    <div class="table-loading" id="tableLoading">Memuat data...</div>

    <!-- Tabel -->
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th style="width:48px;" class="text-center">No</th>
                    <th data-sort="file_name"     data-sort-active="<?= e($sort) ?>" data-dir-active="<?= e($dir) ?>">Nama File</th>
                    <th data-sort="original_name" data-sort-active="<?= e($sort) ?>" data-dir-active="<?= e($dir) ?>">Nama Asli</th>
                    <th data-sort="category"      data-sort-active="<?= e($sort) ?>" data-dir-active="<?= e($dir) ?>">Kategori</th>
                    <th data-sort="file_type"     data-sort-active="<?= e($sort) ?>" data-dir-active="<?= e($dir) ?>">Tipe</th>
                    <th data-sort="file_size"     data-sort-active="<?= e($sort) ?>" data-dir-active="<?= e($dir) ?>">Ukuran</th>
                    <th data-sort="uploaded_at"   data-sort-active="<?= e($sort) ?>" data-dir-active="<?= e($dir) ?>">Tanggal</th>
                    <th class="text-right" style="width:220px;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($files === []): ?>
                    <tr>
                        <td colspan="8" style="padding:0;">
                            <div class="empty-state">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                    <path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                                </svg>
                                <h3>Tidak ada file</h3>
                                <p>Belum ada file yang cocok. Silakan upload file pertama Anda.</p>
                                <a href="upload.php" class="btn btn-primary">Upload File</a>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($files as $index => $file): ?>
                        <?php $ext = file_extension((string) $file['original_name']); ?>
                        <tr>
                            <td class="text-center text-gray"><?= e((string) (($curPage - 1) * $perPage + $index + 1)) ?></td>

                            <td>
                                <p class="cell-name">
                                    <a href="detail.php?id=<?= e((string) $file['id']) ?>">
                                        <?= e((string) $file['file_name']) ?>
                                    </a>
                                </p>
                            </td>

                            <td class="text-gray" title="<?= e((string) $file['original_name']) ?>">
                                <?= e((string) $file['original_name']) ?>
                            </td>

                            <td>
                                <?php if ($file['category_name']): ?>
                                    <span class="badge badge-blue"><?= e((string) $file['category_name']) ?></span>
                                <?php else: ?>
                                    <span class="badge">Tanpa Kategori</span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <span class="badge"><?= e(strtoupper($ext)) ?></span>
                            </td>

                            <td class="text-gray"><?= e(format_bytes((int) $file['file_size'])) ?></td>

                            <td class="text-gray">
                                <?= e(date('d M Y', strtotime((string) $file['uploaded_at']))) ?>
                                <p class="cell-sub"><?= e(date('H:i', strtotime((string) $file['uploaded_at']))) ?></p>
                            </td>

                            <td>
                                <div class="action-group">
                                    <a href="detail.php?id=<?= e((string) $file['id']) ?>"
                                       class="btn btn-sm btn-outline">Detail</a>

                                    <a href="download.php?id=<?= e((string) $file['id']) ?>"
                                       class="btn btn-sm btn-sky-soft">Download</a>

                                    <a href="edit.php?id=<?= e((string) $file['id']) ?>"
                                       class="btn btn-sm btn-amber-soft">Edit</a>

                                    <form method="POST" action="delete.php" data-confirm="Yakin ingin menghapus file ini? Tindakan ini tidak bisa dibatalkan."
                          style="display:inline;">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id" value="<?= e((string) $file['id']) ?>">
                                        <button type="submit" class="btn btn-sm btn-danger-soft">Hapus</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($lastPage > 1): ?>
        <div class="card-footer">
            <nav class="pagination" aria-label="Navigasi halaman">
                <span class="page-info" style="order:-1; margin-bottom:8px;">
                    Menampilkan <?= e((string) $from) ?>&ndash;<?= e((string) $to) ?> dari <?= e((string) $total) ?> file
                </span>

                <?php if ($curPage > 1): ?>
                    <a class="page-link" href="<?= e($urlFor(['page' => $curPage - 1])) ?>" rel="prev">&laquo;</a>
                <?php else: ?>
                    <span class="page-link disabled">&laquo;</span>
                <?php endif; ?>

                <?php
                // Tampilkan maksimal 5 nomor halaman di sekitar halaman sekarang
                $start = max(1, $curPage - 2);
                $end   = min($lastPage, $curPage + 2);

                if ($start > 1) {
                    echo '<a class="page-link" href="' . e($urlFor(['page' => 1])) . '">1</a>';
                    if ($start > 2) {
                        echo '<span class="page-link disabled">&hellip;</span>';
                    }
                }

                for ($p = $start; $p <= $end; $p++):
                    if ($p === $curPage):
                        ?>
                        <span class="page-link active" aria-current="page"><?= e((string) $p) ?></span>
                        <?php
                    else:
                        ?>
                        <a class="page-link" href="<?= e($urlFor(['page' => $p])) ?>"><?= e((string) $p) ?></a>
                        <?php
                    endif;
                endfor;

                if ($end < $lastPage) {
                    if ($end < $lastPage - 1) {
                        echo '<span class="page-link disabled">&hellip;</span>';
                    }
                    echo '<a class="page-link" href="' . e($urlFor(['page' => $lastPage])) . '">'
                        . e((string) $lastPage) . '</a>';
                }
                ?>

                <?php if ($curPage < $lastPage): ?>
                    <a class="page-link" href="<?= e($urlFor(['page' => $curPage + 1])) ?>" rel="next">&raquo;</a>
                <?php else: ?>
                    <span class="page-link disabled">&raquo;</span>
                <?php endif; ?>
            </nav>
        </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
