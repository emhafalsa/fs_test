<?php
/**
 * EDIT.PHP
 * -----------------------------------------------------------------
 * Mengubah data file: nama, kategori, deskripsi, dan (opsional)
 * mengganti file fisik.
 *
 * Aturan penting soal ganti file:
 *   - File baru disimpan lebih dulu.
 *   - Database baru di-update setelah file baru berhasil disimpan.
 *   - File lama dihapus paling akhir.
 *
 * Urutan ini mencegah kondisi setengah jadi: bila upload file baru
 * gagal, file lama dan data lama tetap utuh.
 */

require_once __DIR__ . '/includes/bootstrap.php';

require_login();

// ============================================================
// AMBIL DATA FILE
// ============================================================

$id = (int) ($_GET['id'] ?? ($_POST['id'] ?? 0));

if ($id <= 0) {
    flash('error', 'ID file tidak valid.');
    redirect('index.php');
}

$file = find_file($id);

if ($file === null) {
    flash('error', 'File tidak ditemukan.');
    redirect('index.php');
}

// ============================================================
// PROSES POST (form disubmit)
// ============================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $name        = trim((string) ($_POST['file_name'] ?? ''));
    $categoryId  = (string) ($_POST['category_id'] ?? '');
    $description = trim((string) ($_POST['description'] ?? ''));
    $oldStored   = (string) $file['file_path'];

    $errors = [];

    // --- Validasi input teks ---
    if ($name === '') {
        $errors[] = 'Nama file wajib diisi.';
    } elseif (mb_strlen($name) > 150) {
        $errors[] = 'Nama file maksimal 150 karakter.';
    }

    if ($categoryId !== '' && ! ctype_digit($categoryId)) {
        $errors[] = 'Kategori yang dipilih tidak valid.';
        $categoryId = '';
    }

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

    // --- File baru (opsional) ---
    $newFile      = null;
    $hasNewFile   = isset($_FILES['file'])
                    && ($_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;

    if ($hasNewFile) {
        $check = validate_upload($_FILES['file']);

        if (! $check['ok']) {
            $errors[] = 'File baru: ' . $check['error'];
        } else {
            $newFile = $_FILES['file'];
        }
    }

    // --- Simpan perubahan ---
    if ($errors === []) {
        try {
            $updateData = [
                'file_name'   => $name,
                'category_id' => $categoryId !== '' ? (int) $categoryId : null,
                'description' => $description !== '' ? $description : null,
                'updated_at'  => date('Y-m-d H:i:s'),
            ];

            $oldFileRemoved = false;

            if ($newFile !== null) {
                // 1. Simpan file baru
                $moved = move_uploaded($newFile);

                if (! $moved['ok']) {
                    throw new RuntimeException($moved['error']);
                }

                $updateData['original_name'] = (string) $newFile['name'];
                $updateData['file_path']     = $moved['stored_name'];
                $updateData['file_type']     = (string) ($newFile['type'] ?? 'application/octet-stream');
                $updateData['file_size']     = (int) $newFile['size'];

                // 2. Update database
                db_run(
                    'UPDATE files SET
                        file_name = ?, original_name = ?, file_path = ?, file_type = ?,
                        file_size = ?, category_id = ?, description = ?, updated_at = ?
                      WHERE id = ?',
                    [
                        $updateData['file_name'],
                        $updateData['original_name'],
                        $updateData['file_path'],
                        $updateData['file_type'],
                        $updateData['file_size'],
                        $updateData['category_id'],
                        $updateData['description'],
                        $updateData['updated_at'],
                        $id,
                    ]
                );

                // 3. Terakhir: hapus file lama (tidak tahu sama sekali
                //    kalau langkah 1-2 gagal, file lama tetap ada)
                $oldFileRemoved = delete_uploaded_file($oldStored);
            } else {
                // Tidak ganti file: hanya update kolom yang bisa diedit
                db_run(
                    'UPDATE files SET
                        file_name = ?, category_id = ?, description = ?, updated_at = ?
                      WHERE id = ?',
                    [
                        $updateData['file_name'],
                        $updateData['category_id'],
                        $updateData['description'],
                        $updateData['updated_at'],
                        $id,
                    ]
                );
            }

            flash(
                'success',
                $newFile !== null
                    ? 'File berhasil diperbarui (termasuk file baru).'
                    : 'Data file berhasil diperbarui.'
            );

            redirect('detail.php?id=' . $id);
        } catch (Throwable $e) {
            error_log('Update file gagal: ' . $e->getMessage());

            $_SESSION['edit_errors'] = ['Terjadi kesalahan saat menyimpan. Silakan coba lagi.'];
            $_SESSION['edit_old']    = [
                'file_name'   => $name,
                'category_id' => $categoryId,
                'description' => $description,
            ];

            flash('error', 'Perubahan gagal disimpan.');
            redirect('edit.php?id=' . $id);
        }
    }

    // Ada error: simpan untuk ditampilkan
    $_SESSION['edit_errors'] = $errors;
    $_SESSION['edit_old']    = [
        'file_name'   => $name,
        'category_id' => $categoryId,
        'description' => $description,
    ];

    flash('error', 'Perubahan tidak dapat disimpan.');
    redirect('edit.php?id=' . $id);
}

// ============================================================
// TAMPILKAN FORM
// ============================================================

$errors = $_SESSION['edit_errors'] ?? [];
$old    = $_SESSION['edit_old'] ?? [];
unset($_SESSION['edit_errors'], $_SESSION['edit_old']);

// Kalau tidak ada input sebelumnya, pakai data yang tersimpan di database
$nameValue = (string) ($old['file_name']   ?? $file['file_name']);
$catValue  = (string) ($old['category_id'] ?? ($file['category_id'] ?? ''));
$descValue = (string) ($old['description'] ?? ($file['description'] ?? ''));

$categories = all_categories();

$pageTitle  = 'Edit File';
$activeMenu = 'files';
require __DIR__ . '/includes/header.php';
?>

<nav class="breadcrumb" aria-label="Breadcrumb">
    <a href="dashboard.php">Dashboard</a>
    <span aria-hidden="true">/</span>
    <a href="index.php">Semua File</a>
    <span aria-hidden="true">/</span>
    <a href="detail.php?id=<?= e((string) $id) ?>">Detail</a>
    <span aria-hidden="true">/</span>
    <span aria-current="page">Edit</span>
</nav>

<div class="page-head">
    <div>
        <h2>Edit File</h2>
        <p>Mengubah data &quot;<?= e((string) $file['file_name']) ?>&quot;</p>
    </div>
    <a href="detail.php?id=<?= e((string) $id) ?>" class="btn btn-outline">Batal</a>
</div>

<div class="detail-layout">
    <!-- ================= FORM EDIT ================= -->
    <div class="card">
        <div class="card-header">
            <h3>Form Edit</h3>
        </div>

        <div class="card-body">
            <?php if ($errors !== []): ?>
                <div class="alert alert-error" role="alert">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="8" x2="12" y2="12"></line>
                        <line x1="12" y1="16" x2="12.01" y2="16"></line>
                    </svg>
                    <div>
                        <strong>Perubahan tidak dapat disimpan:</strong>
                        <ul>
                            <?php foreach ($errors as $error): ?>
                                <li><?= e($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            <?php endif; ?>

            <form method="POST" action="edit.php?id=<?= e((string) $id) ?>"
                  enctype="multipart/form-data" data-loading>
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= e((string) $id) ?>">

                <!-- Nama file -->
                <div class="form-row">
                    <label class="form-label" for="edit-name">Nama File <span class="req">*</span></label>
                    <input class="form-control" type="text" name="file_name" id="edit-name"
                           value="<?= e($nameValue) ?>" maxlength="150" required>
                    <p class="form-hint">Nama yang tampil di daftar file.</p>
                </div>

                <!-- Kategori -->
                <div class="form-row">
                    <label class="form-label" for="edit-category">Kategori</label>
                    <select class="form-control" name="category_id" id="edit-category">
                        <option value="">-- Tanpa Kategori --</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= e((string) $cat['id']) ?>"
                                    <?= $catValue === (string) $cat['id'] ? 'selected' : '' ?>>
                                <?= e($cat['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Deskripsi -->
                <div class="form-row">
                    <label class="form-label" for="edit-description">Deskripsi</label>
                    <textarea class="form-control" name="description" id="edit-description"
                              rows="4" maxlength="2000"
                              placeholder="Keterangan singkat tentang file"><?= e($descValue) ?></textarea>
                </div>

                <!-- Ganti file (opsional) -->
                <div class="form-row">
                    <label class="form-label" for="edit-file">Ganti File (opsional)</label>
                    <input class="form-control" type="file" name="file" id="edit-file"
                           accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png,.zip">
                    <p class="form-hint">
                        Biarkan kosong bila tidak ingin mengganti file.
                        File lama akan otomatis dihapus setelah file baru berhasil disimpan.
                        Maksimal 10 MB.
                    </p>
                </div>

                <!-- Tombol -->
                <div class="form-row" style="display:flex; gap:10px; flex-wrap:wrap; margin-top:24px;">
                    <button type="submit" class="btn btn-primary">
                        <span class="spinner" aria-hidden="true"></span>
                        Simpan Perubahan
                    </button>
                    <a href="detail.php?id=<?= e((string) $id) ?>" class="btn btn-outline">Batal</a>
                </div>
            </form>
        </div>
    </div>

    <!-- ================= INFO FILE SAAT INI ================= -->
    <div class="card">
        <div class="card-header">
            <h3>File Saat Ini</h3>
        </div>

        <div class="card-body">
            <dl class="meta-list">
                <div class="meta-item">
                    <dt>Nama Asli</dt>
                    <dd class="wrap" style="font-weight:400;"><?= e((string) $file['original_name']) ?></dd>
                </div>
                <div class="meta-item">
                    <dt>Tipe</dt>
                    <dd><?= e(strtoupper(file_extension((string) $file['original_name']))) ?></dd>
                </div>
                <div class="meta-item">
                    <dt>Ukuran</dt>
                    <dd><?= e(format_bytes((int) $file['file_size'])) ?></dd>
                </div>
                <div class="meta-item">
                    <dt>Nama di Server</dt>
                    <dd class="wrap" style="font-weight:400;"><?= e((string) $file['file_path']) ?></dd>
                </div>
                <div class="meta-item">
                    <dt>Upload</dt>
                    <dd><?= e(date('d M Y', strtotime((string) $file['uploaded_at']))) ?></dd>
                </div>
                <div class="meta-item">
                    <dt>Update</dt>
                    <dd><?= e(date('d M Y', strtotime((string) $file['updated_at']))) ?></dd>
                </div>
            </dl>

            <div class="alert alert-info" style="margin-top:16px; margin-bottom:0;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="12" y1="16" x2="12" y2="12"></line>
                    <line x1="12" y1="8" x2="12.01" y2="8"></line>
                </svg>
                <div>
                    Mengganti nama atau kategori TIDAK mengubah file fisik.
                    Nama file di server tetap <code><?= e((string) $file['file_path']) ?></code>.
                </div>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
