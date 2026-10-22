<?php
/**
 * DETAIL.PHP
 * -----------------------------------------------------------------
 * Menampilkan detail satu file: metadata + preview.
 *
 * Preview yang bisa ditampilkan langsung di browser:
 *   - PDF  -> <iframe>
 *   - Gambar (JPG/JPEG/PNG) -> <img>
 *
 * Untuk format lain (DOC/XLS/PPT/ZIP) browser tidak bisa menampilkan
 * isinya, jadi hanya tampil informasi + tombol download.
 */

require_once __DIR__ . '/includes/bootstrap.php';

require_login();

// ============================================================
// AMBIL DATA FILE
// ============================================================

$id = (int) ($_GET['id'] ?? 0);

if ($id <= 0) {
    flash('error', 'ID file tidak valid.');
    redirect('index.php');
}

$file = find_file($id);

if ($file === null) {
    flash('error', 'File tidak ditemukan. Mungkin sudah dihapus.');
    redirect('index.php');
}

// ============================================================
// PERSIAPAN DATA UNTUK TAMPILAN
// ============================================================

$originalName = (string) $file['original_name'];
$storedName   = (string) $file['file_path'];
$ext          = file_extension($originalName);

// URL untuk preview & download
$previewUrl = 'preview.php?id=' . $id;
$downloadUrl = 'download.php?id=' . $id;

// Cek apakah file fisik masih ada di folder uploads
$physicalPath = upload_path($storedName);
$fileExists   = $physicalPath !== null;

// Tentukan jenis preview
$isPdf   = ($ext === 'pdf');
$isImage = in_array($ext, ['jpg', 'jpeg', 'png'], true);
$canPreview = ($isPdf || $isImage) && $fileExists;

$pageTitle  = 'Detail File';
$activeMenu = 'files';
require __DIR__ . '/includes/header.php';
?>

<!-- Breadcrumb -->
<nav class="breadcrumb" aria-label="Breadcrumb">
    <a href="dashboard.php">Dashboard</a>
    <span aria-hidden="true">/</span>
    <a href="index.php">Semua File</a>
    <span aria-hidden="true">/</span>
    <span aria-current="page">Detail</span>
</nav>

<div class="page-head">
    <div>
        <h2><?= e((string) $file['file_name']) ?></h2>
        <p>Nama asli: <?= e($originalName) ?></p>
    </div>
    <div style="display:flex; gap:8px; flex-wrap:wrap;">
        <?php if ($fileExists): ?>
            <a href="<?= e($downloadUrl) ?>" class="btn btn-primary">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                     stroke-linecap="round" stroke-linejoin="round">
                    <path d="M4 16v1a3 3 0 0 0 3 3h10a3 3 0 0 0 3-3v-1"></path>
                    <line x1="12" y1="4" x2="12" y2="16"></line>
                    <polyline points="7 11 12 16 17 11"></polyline>
                </svg>
                Download
            </a>
        <?php endif; ?>

        <a href="edit.php?id=<?= e((string) $id) ?>" class="btn btn-amber-soft">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                 stroke-linecap="round" stroke-linejoin="round">
                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                <path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4z"></path>
            </svg>
            Edit
        </a>

        <a href="index.php" class="btn btn-outline">Kembali</a>
    </div>
</div>

<!-- Peringatan kalau file fisik hilang -->
<?php if (! $fileExists): ?>
    <div class="alert alert-error" role="alert">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="10"></circle>
            <line x1="12" y1="8" x2="12" y2="12"></line>
            <line x1="12" y1="16" x2="12.01" y2="16"></line>
        </svg>
        <div>
            <strong>File fisik tidak ditemukan.</strong>
            Data masih ada di database, tetapi file
            <code><?= e($storedName) ?></code> tidak ada di folder uploads/.
            Silakan upload ulang melalui halaman Edit.
        </div>
    </div>
<?php endif; ?>

<div class="detail-layout">
    <!-- ================= PREVIEW ================= -->
    <div class="card">
        <div class="card-header">
            <h3>Preview</h3>
            <?php if ($canPreview): ?>
                <a href="<?= e($previewUrl) ?>" target="_blank" rel="noopener"
                   class="btn btn-sm btn-outline">Buka di Tab Baru</a>
            <?php endif; ?>
        </div>

        <div class="card-body">
            <?php if ($canPreview): ?>
                <div class="preview-box">
                    <?php if ($isPdf): ?>
                        <!--
                            PDF ditampilkan di dalam iframe.
                            Header Content-Type dari preview.php sudah
                            application/pdf, jadi browser merender-nya.
                        -->
                        <iframe src="<?= e($previewUrl) ?>"
                                title="Preview PDF <?= e($originalName) ?>"></iframe>
                    <?php else: ?>
                        <img src="<?= e($previewUrl) ?>" alt="<?= e($originalName) ?>">
                    <?php endif; ?>
                </div>

            <?php else: ?>
                <div class="preview-box">
                    <div class="preview-empty">
                        <?php if (! $fileExists): ?>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                <path d="M3 7a2 2 0 0 1 2-2h4l2 2h9a2 2 0 0 1 1 1v8a2 2 0 0 1-1 1H5a2 2 0 0 1-2-2z"></path>
                            </svg>
                            <h3>File tidak ditemukan</h3>
                            <p>File fisik tidak ada di folder uploads.</p>

                        <?php else: ?>
                            <!--
                                Format office (DOC/XLS/PPT) dan ZIP tidak bisa
                                dirender browser. Tampilkan ikon + tombol download.
                            -->
                            <div class="file-icon-lg" aria-hidden="true"><?= e(strtoupper($ext)) ?></div>
                            <h3>Preview tidak tersedia</h3>
                            <p>
                                Format <strong><?= e(strtoupper($ext)) ?></strong>
                                tidak dapat ditampilkan langsung di browser.
                                Silakan download file untuk membukanya.
                            </p>
                            <a href="<?= e($downloadUrl) ?>" class="btn btn-primary">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                     stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M4 16v1a3 3 0 0 0 3 3h10a3 3 0 0 0 3-3v-1"></path>
                                    <line x1="12" y1="4" x2="12" y2="16"></line>
                                    <polyline points="7 11 12 16 17 11"></polyline>
                                </svg>
                                Download File
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ================= INFORMASI FILE ================= -->
    <div class="card">
        <div class="card-header">
            <h3>Informasi File</h3>
        </div>

        <div class="card-body">
            <dl class="meta-list">
                <div class="meta-item">
                    <dt>Nama File</dt>
                    <dd class="wrap"><?= e((string) $file['file_name']) ?></dd>
                </div>
                <div class="meta-item">
                    <dt>Nama Asli</dt>
                    <dd class="wrap"><?= e($originalName) ?></dd>
                </div>
                <div class="meta-item">
                    <dt>Kategori</dt>
                    <dd>
                        <?= $file['category_name'] ? e((string) $file['category_name']) : 'Tanpa Kategori' ?>
                    </dd>
                </div>
                <div class="meta-item">
                    <dt>Ekstensi</dt>
                    <dd><?= e(strtoupper($ext)) ?></dd>
                </div>
                <div class="meta-item">
                    <dt>MIME Type</dt>
                    <dd class="wrap" style="font-weight:400;"><?= e((string) $file['file_type']) ?></dd>
                </div>
                <div class="meta-item">
                    <dt>Ukuran</dt>
                    <dd><?= e(format_bytes((int) $file['file_size'])) ?></dd>
                </div>
                <div class="meta-item">
                    <dt>Tanggal Upload</dt>
                    <dd><?= e(date('d M Y H:i', strtotime((string) $file['uploaded_at']))) ?></dd>
                </div>
                <div class="meta-item">
                    <dt>Terakhir Update</dt>
                    <dd><?= e(date('d M Y H:i', strtotime((string) $file['updated_at']))) ?></dd>
                </div>
                <div class="meta-item">
                    <dt>Nama di Server</dt>
                    <dd class="wrap" style="font-weight:400;"><?= e($storedName) ?></dd>
                </div>
                <div class="meta-item">
                    <dt>Status</dt>
                    <dd>
                        <?php if ($fileExists): ?>
                            <span class="badge badge-green">Tersedia</span>
                        <?php else: ?>
                            <span class="badge badge-amber">File Hilang</span>
                        <?php endif; ?>
                    </dd>
                </div>
            </dl>

            <?php if ($file['description']): ?>
                <div style="margin-top:20px; padding-top:16px; border-top:1px solid var(--border);">
                    <h3 style="font-size:0.85rem; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.4px; margin-bottom:8px;">
                        Deskripsi
                    </h3>
                    <p style="font-size:0.9rem; white-space:pre-line; line-height:1.7;">
                        <?= e((string) $file['description']) ?>
                    </p>
                </div>
            <?php endif; ?>
        </div>

        <div class="card-footer">
            <!-- Hapus: tombol POST + konfirmasi JavaScript -->
            <form method="POST" action="delete.php"
                  data-confirm="Yakin ingin menghapus file &quot;<?= e((string) $file['file_name']) ?>&quot;? File akan dihapus permanen dari server.">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= e((string) $id) ?>">
                <button type="submit" class="btn btn-danger-soft btn-block">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                         stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="3 6 5 6 21 6"></polyline>
                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                    </svg>
                    Hapus File
                </button>
            </form>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
