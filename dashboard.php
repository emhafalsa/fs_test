<?php
/**
 * DASHBOARD.PHP
 * -----------------------------------------------------------------
 * Halaman utama setelah login.
 *
 * Menampilkan:
 *   - Empat kartu statistik (Total File, Total Storage, File Hari Ini, Kategori)
 *   - Tabel "File Terbaru" dengan aksi cepat
 */

require_once __DIR__ . '/includes/bootstrap.php';

require_login();

// Ambil data statistik
$stats = dashboard_stats();

// File terbaru (5 buah)
$latestFiles = latest_files(5);

// Format ukuran storage: konversi ke MB / GB supaya enak dibaca
$totalBytes  = (int) $stats['total_size'];
$storageUsed = format_bytes($totalBytes);

// Persentase kapasitas disk (jika bisa dibaca)
$diskTotal  = @disk_total_space(UPLOAD_DIR);
$diskFree   = @disk_free_space(UPLOAD_DIR);
$diskUsed   = ($diskTotal && $diskTotal > 0) ? ($diskTotal - $diskFree) : 0;
$diskPercent = ($diskTotal && $diskTotal > 0)
    ? min(100, round(($diskUsed / $diskTotal) * 100, 1))
    : null;

$pageTitle  = 'Dashboard';
$activeMenu = 'dashboard';
require __DIR__ . '/includes/header.php';
?>

<div class="page-head">
    <div>
        <h2>Halo, <?= e(current_user()) ?>!</h2>
        <p>Ringkasan file yang tersimpan di sistem.</p>
    </div>

    <div style="display:flex; gap:8px; flex-wrap:wrap;">
        <button type="button" class="btn btn-primary" data-modal-open>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                 stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                <polyline points="17 8 12 3 7 8"></polyline>
                <line x1="12" y1="3" x2="12" y2="15"></line>
            </svg>
            Upload Cepat
        </button>

        <a href="index.php" class="btn btn-outline">Lihat Semua File</a>
    </div>
</div>

<!-- ============ KARTU STATISTIK ============ -->
<div class="stats-grid">

    <!-- Total File -->
    <div class="stat-card">
        <span class="stat-icon blue" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                 stroke-linecap="round" stroke-linejoin="round">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                <polyline points="14 2 14 8 20 8"></polyline>
            </svg>
        </span>
        <div class="stat-info">
            <p class="stat-label">Total File</p>
            <p class="stat-value"><?= e(number_format((int) $stats['total_files'], 0, ',', '.')) ?></p>
        </div>
    </div>

    <!-- Total Storage -->
    <div class="stat-card">
        <span class="stat-icon green" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                 stroke-linecap="round" stroke-linejoin="round">
                <ellipse cx="12" cy="5" rx="9" ry="3"></ellipse>
                <path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"></path>
                <path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"></path>
            </svg>
        </span>
        <div class="stat-info">
            <p class="stat-label">Total Storage</p>
            <p class="stat-value"><?= e($storageUsed) ?></p>
            <?php if ($diskPercent !== null): ?>
                <div class="progress" style="margin-top:8px;" title="Terpakai <?= e((string) $diskPercent) ?>% dari disk">
                    <div class="progress-bar" style="width: <?= e((string) $diskPercent) ?>%;"></div>
                </div>
                <p class="cell-sub"><?= e((string) $diskPercent) ?>% dari kapasitas disk</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- File Hari Ini -->
    <div class="stat-card">
        <span class="stat-icon amber" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                 stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="4" width="18" height="18" rx="2"></rect>
                <line x1="16" y1="2" x2="16" y2="6"></line>
                <line x1="8" y1="2" x2="8" y2="6"></line>
                <line x1="3" y1="10" x2="21" y2="10"></line>
            </svg>
        </span>
        <div class="stat-info">
            <p class="stat-label">File Hari Ini</p>
            <p class="stat-value"><?= e(number_format((int) $stats['files_today'], 0, ',', '.')) ?></p>
            <p class="cell-sub"><?= e(date('d F Y')) ?></p>
        </div>
    </div>

    <!-- Jumlah Kategori -->
    <div class="stat-card">
        <span class="stat-icon gray" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                 stroke-linecap="round" stroke-linejoin="round">
                <path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
            </svg>
        </span>
        <div class="stat-info">
            <p class="stat-label">Kategori</p>
            <p class="stat-value"><?= e(number_format((int) $stats['total_category'], 0, ',', '.')) ?></p>
            <a href="category.php" class="cell-sub">Kelola kategori</a>
        </div>
    </div>
</div>

<!-- ============ FILE TERBARU ============ -->
<div class="card">
    <div class="card-header">
        <h3>File Terbaru</h3>
        <a href="index.php" class="btn btn-sm btn-outline">Lihat Semua</a>
    </div>

    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th style="width:48px;" class="text-center">No</th>
                    <th>Nama File</th>
                    <th>Kategori</th>
                    <th>Tipe</th>
                    <th>Ukuran</th>
                    <th>Tanggal</th>
                    <th class="text-right" style="width:200px;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($latestFiles === []): ?>
                    <tr>
                        <td colspan="7" style="padding:0;">
                            <div class="empty-state">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                    <path d="M3 7a2 2 0 0 1 2-2h4l2 2h9a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                                </svg>
                                <h3>Belum ada file</h3>
                                <p>Mulai dengan mengupload file pertama Anda.</p>
                                <a href="upload.php" class="btn btn-primary">Upload File</a>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($latestFiles as $index => $file): ?>
                        <?php $ext = file_extension((string) $file['original_name']); ?>
                        <tr>
                            <td class="text-center text-gray"><?= e((string) ($index + 1)) ?></td>

                            <td>
                                <p class="cell-name">
                                    <a href="detail.php?id=<?= e((string) $file['id']) ?>">
                                        <?= e((string) $file['file_name']) ?>
                                    </a>
                                </p>
                                <p class="cell-sub"><?= e((string) $file['original_name']) ?></p>
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

                                    <form method="POST" action="delete.php" style="display:inline;"
                                          data-confirm="Yakin ingin menghapus file ini?">
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
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
