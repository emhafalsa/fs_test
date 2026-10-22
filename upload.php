<?php
/**
 * UPLOAD.PHP
 * -----------------------------------------------------------------
 * Memproses upload file. Bisa menerima:
 *   - SATU file  (dari form biasa di halaman ini)
 *   - BANYAK file (dari modal upload, name="files[]")
 *
 * Alur:
 *   1. Wajib login + token CSRF
 *   2. Cek file: ada? tidak kosong? ukuran <= 10 MB? ekstensi & MIME ok?
 *   3. Buat nama file unik
 *   4. Pindahkan ke folder uploads/
 *   5. Simpan metadata ke tabel files
 *   6. Kembalikan hasil lewat flash message
 */

require_once __DIR__ . '/includes/bootstrap.php';

require_login();

// ============================================================
// PROSES POST (form disubmit)
// ============================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    // --- Kumpulkan input teks ---
    $fileNameInput = trim((string) ($_POST['file_name'] ?? ''));
    $categoryId    = (string) ($_POST['category_id'] ?? '');
    $description   = trim((string) ($_POST['description'] ?? ''));

    // --- Kumpulkan semua file yang dikirim ---
    // Dua kemungkinan nama field: "files[]" (modal, multi) atau "file" (satu).
    $files = [];

    if (isset($_FILES['files'])) {
        // Bentuk array $_FILES['files'] diratakan jadi daftar file biasa
        $files = rearray_files($_FILES['files']);
    } elseif (isset($_FILES['file'])) {
        $files = [$_FILES['file']];
    }

    // ============================================================
    // Validasi input teks
    // ============================================================

    $errors = [];

    if ($fileNameInput === '') {
        $errors[] = 'Nama file wajib diisi.';
    } elseif (mb_strlen($fileNameInput) > 150) {
        $errors[] = 'Nama file maksimal 150 karakter.';
    }

    if ($categoryId !== '' && ! ctype_digit($categoryId)) {
        $errors[] = 'Kategori yang dipilih tidak valid.';
        $categoryId = '';
    }

    // Kategori harus benar-benar ada di database
    if ($categoryId !== '') {
        $category = find_category((int) $categoryId);

        if ($category === null) {
            $errors[] = 'Kategori tidak ditemukan.';
            $categoryId = '';
        }
    }

    if (mb_strlen($description) > 2000) {
        $errors[] = 'Deskripsi maksimal 2000 karakter.';
    }

    // ============================================================
    // Validasi file
    // ============================================================

    $validFiles = [];

    if ($files === []) {
        $errors[] = 'File wajib dipilih.';
    } else {
        foreach ($files as $index => $file) {
            $check = validate_upload($file);

            if (! $check['ok']) {
                $name = (string) ($file['name'] ?? 'File #' . ($index + 1));
                $errors[] = $name . ': ' . $check['error'];
                continue;
            }

            $validFiles[] = $file;
        }
    }

    // ============================================================
    // Simpan (hanya bila tidak ada error)
    // ============================================================

    if ($errors === [] && $validFiles !== []) {
        $saved    = 0;
        $userId   = null; // tabel files tidak mewajibkan user_id
        $now      = date('Y-m-d H:i:s');

        // Transaksi: bila ada file yang gagal di tengah jalan,
        // semua perubahan database dibatalkan.
        try {
            db()->beginTransaction();

            foreach ($validFiles as $file) {
                $originalName = (string) $file['name'];

                // Nama tampilan: pakai input user, atau nama file asli
                $displayName = $fileNameInput !== '' ? $fileNameInput : pathinfo($originalName, PATHINFO_FILENAME);

                // Untuk upload banyak, nama unik per file agar tidak saling tumpang tindih
                if (count($validFiles) > 1) {
                    $displayName .= ' (' . pathinfo($originalName, PATHINFO_FILENAME) . ')';
                }

                $displayName = mb_substr($displayName, 0, 150);

                // 1. Simpan file fisik, dapat nama unik
                $moved = move_uploaded($file);

                if (! $moved['ok']) {
                    throw new RuntimeException($moved['error']);
                }

                // 2. Simpan metadata ke database
                db_run(
                    'INSERT INTO files
                        (file_name, original_name, file_path, file_type, file_size,
                         category_id, description, uploaded_at, updated_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    [
                        $displayName,
                        $originalName,
                        $moved['stored_name'],
                        (string) ($file['type'] ?? 'application/octet-stream'),
                        (int) $file['size'],
                        $categoryId !== '' ? (int) $categoryId : null,
                        $description !== '' ? $description : null,
                        $now,
                        $now,
                    ]
                );

                $saved++;
            }

            db()->commit();

            // Pesan berhasil sesuai jumlah file
            if ($saved === 1) {
                flash('success', 'File berhasil diunggah.');
            } else {
                flash('success', $saved . ' file berhasil diunggah.');
            }

            redirect('index.php');
        } catch (Throwable $e) {
            // Ada masalah: batalkan semua perubahan database
            if (db()->inTransaction()) {
                db()->rollBack();
            }

            error_log('Upload gagal: ' . $e->getMessage());
            $errors[] = 'Terjadi kesalahan saat menyimpan file. Silakan coba lagi.';
        }
    }

    // ============================================================
    // Ada error: kembali ke form dengan pesan
    // ============================================================

    if ($errors !== []) {
        // Simpan pesan error di session supaya bisa ditampilkan di form
        $_SESSION['upload_errors'] = $errors;
        $_SESSION['upload_old']    = [
            'file_name'   => $fileNameInput,
            'category_id' => $categoryId,
            'description' => $description,
        ];

        flash('error', 'Upload gagal. Periksa kembali isian Anda.');
        redirect('upload.php');
    }
}

// ============================================================
// TAMPILKAN HALAMAN
// ============================================================

// Ambil pesan error dari percobaan sebelumnya (jika ada)
$errors     = $_SESSION['upload_errors'] ?? [];
$old        = $_SESSION['upload_old'] ?? [];
unset($_SESSION['upload_errors'], $_SESSION['upload_old']);

$oldName   = (string) ($old['file_name'] ?? '');
$oldCat    = (string) ($old['category_id'] ?? '');
$oldDesc   = (string) ($old['description'] ?? '');
$categories = all_categories();

$pageTitle  = 'Upload File';
$activeMenu = 'upload';
require __DIR__ . '/includes/header.php';
?>

<div class="page-head">
    <div>
        <h2>Upload File</h2>
        <p>Unggah satu atau beberapa file ke sistem.</p>
    </div>
    <a href="index.php" class="btn btn-outline">Kembali ke Daftar File</a>
</div>

<div class="detail-layout">
    <!-- ================= FORM UPLOAD ================= -->
    <div class="card">
        <div class="card-header">
            <h3>Form Upload</h3>
        </div>

        <div class="card-body">
            <!-- Daftar error validasi -->
            <?php if ($errors !== []): ?>
                <div class="alert alert-error" role="alert">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="8" x2="12" y2="12"></line>
                        <line x1="12" y1="16" x2="12.01" y2="16"></line>
                    </svg>
                    <div>
                        <strong>Upload tidak berhasil:</strong>
                        <ul>
                            <?php foreach ($errors as $error): ?>
                                <li><?= e($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            <?php endif; ?>

            <form method="POST" action="upload.php" enctype="multipart/form-data"
                  id="uploadPageForm" data-loading novalidate>
                <?= csrf_field() ?>

                <!-- File (boleh banyak) -->
                <div class="form-row">
                    <label class="form-label" for="upload-page-files">Pilih File <span class="req">*</span></label>
                    <input class="form-control" type="file" name="files[]" id="upload-page-files"
                           multiple required
                           accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png,.zip">
                    <p class="form-hint">
                        Bisa memilih lebih dari satu file sekaligus (Ctrl + klik).
                        Format: PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX, JPG, JPEG, PNG, ZIP.
                        Maksimal 10 MB per file.
                    </p>

                    <!-- Daftar file akan diisi JavaScript (lihat assets/js/script.js) -->
                    <ul class="file-list" id="fileList" aria-live="polite"></ul>
                </div>

                <!-- Nama file -->
                <div class="form-row">
                    <label class="form-label" for="upload-page-name">Nama File <span class="req">*</span></label>
                    <input class="form-control" type="text" name="file_name" id="upload-page-name"
                           value="<?= e($oldName) ?>" maxlength="150" required
                           placeholder="Contoh: Surat Undangan Rapat">
                    <p class="form-hint">
                        Nama yang tampil di daftar file. Boleh berbeda dari nama file asli.
                    </p>
                </div>

                <div class="form-grid">
                    <!-- Kategori -->
                    <div class="form-row">
                        <label class="form-label" for="upload-page-category">Kategori</label>
                        <select class="form-control" name="category_id" id="upload-page-category">
                            <option value="">-- Tanpa Kategori --</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= e((string) $cat['id']) ?>"
                                        <?= $oldCat === (string) $cat['id'] ? 'selected' : '' ?>>
                                    <?= e($cat['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Deskripsi -->
                <div class="form-row">
                    <label class="form-label" for="upload-page-description">Deskripsi</label>
                    <textarea class="form-control" name="description" id="upload-page-description"
                              rows="4" maxlength="2000"
                              placeholder="Keterangan singkat tentang file"><?= e($oldDesc) ?></textarea>
                </div>

                <!-- Tombol -->
                <div class="form-row" style="display:flex; gap:10px; flex-wrap:wrap; margin-top:24px;">
                    <button type="submit" class="btn btn-primary">
                        <span class="spinner" aria-hidden="true"></span>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                             stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                            <polyline points="17 8 12 3 7 8"></polyline>
                            <line x1="12" y1="3" x2="12" y2="15"></line>
                        </svg>
                        Upload Sekarang
                    </button>
                    <a href="index.php" class="btn btn-outline">Batal</a>
                </div>
            </form>
        </div>
    </div>

    <!-- ================= INFO SAMPING ================= -->
    <div class="card">
        <div class="card-header">
            <h3>Informasi</h3>
        </div>
        <div class="card-body">
            <div class="section-title">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                     stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="12" y1="16" x2="12" y2="12"></line>
                    <line x1="12" y1="8" x2="12.01" y2="8"></line>
                </svg>
                <h3>Ketentuan Upload</h3>
            </div>

            <dl class="meta-list">
                <div class="meta-item">
                    <dt>Ukuran maksimal</dt>
                    <dd>10 MB per file</dd>
                </div>
                <div class="meta-item">
                    <dt>Format PDF</dt>
                    <dd>Ya</dd>
                </div>
                <div class="meta-item">
                    <dt>Format gambar</dt>
                    <dd>JPG, JPEG, PNG</dd>
                </div>
                <div class="meta-item">
                    <dt>Format dokumen</dt>
                    <dd>DOC, DOCX, XLS, XLSX, PPT, PPTX</dd>
                </div>
                <div class="meta-item">
                    <dt>Format arsip</dt>
                    <dd>ZIP</dd>
                </div>
            </dl>

            <div class="alert alert-info" style="margin-top:16px; margin-bottom:0;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="12" y1="16" x2="12" y2="12"></line>
                    <line x1="12" y1="8" x2="12.01" y2="8"></line>
                </svg>
                <div>
                    Nama file di server dibuat unik otomatis
                    (<code>document_68fabc12.pdf</code>), sehingga file dengan nama sama
                    tidak saling menimpa.
                </div>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
