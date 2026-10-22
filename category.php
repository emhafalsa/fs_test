<?php
/**
 * CATEGORY.PHP
 * -----------------------------------------------------------------
 * CRUD kategori:
 *   - Lihat semua kategori + jumlah file di dalamnya
 *   - Tambah kategori
 *   - Edit kategori
 *   - Hapus kategori
 *
 * Aturan penting: kategori yang masih dipakai file TIDAK boleh
 * dihapus. Sistem menawarkan dua pilihan:
 *   1. Batalkan hapus (paling aman)
 *   2. Pindahkan semua file ke kategori lain, baru hapus
 *
 * Kolom category_id di tabel files memakai ON DELETE SET NULL,
 * jadi secara teknis file tidak akan ikut terhapus. Tetap saja
 * menghapus kategori yang sedang dipakai membingungkan user,
 * karena file tiba-tiba kehilangan kategorinya.
 */

require_once __DIR__ . '/includes/bootstrap.php';

require_login();

// ============================================================
// TENTUKAN AKSI (dari POST, atau dari URL untuk form edit)
// ============================================================

$action    = (string) ($_POST['action'] ?? $_GET['action'] ?? '');
$editId    = (int) ($_POST['id'] ?? $_GET['id'] ?? 0);
$errors    = [];
$editMode  = false;
$editData  = null;

// ============================================================
// PROSES POST: tambah / edit / hapus
// ============================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    // --------------------------------------------------------
    // HAPUS KATEGORI
    // --------------------------------------------------------
    if ($action === 'delete') {
        $deleteId = (int) ($_POST['id'] ?? 0);
        $migrate  = (string) ($_POST['migrate_to'] ?? ''); // 'none' = batalkan

        $category = $deleteId > 0 ? find_category($deleteId) : null;

        if ($category === null) {
            flash('error', 'Kategori tidak ditemukan.');
        } else {
            $fileCount = category_file_count($deleteId);

            // Kategori yang masih dipakai file: wajib pilih tujuan pemindahan
            if ($fileCount > 0 && $migrate === 'none') {
                flash('error', 'Kategori "' . $category['name'] . '" masih memiliki '
                        . $fileCount . ' file. Pilih kategori tujuan terlebih dahulu.');
            } else {
                try {
                    db()->beginTransaction();

                    if ($fileCount > 0) {
                        // Pindahkan file ke kategori tujuan.
                        // Kalau migrate_to kosong, file jadi tanpa kategori
                        // (category_id = NULL, didukung ON DELETE SET NULL).
                        $targetId = ($migrate !== '' && ctype_digit($migrate))
                            ? (int) $migrate
                            : null;

                        if ($targetId !== null) {
                            $target = find_category($targetId);

                            if ($target === null) {
                                throw new RuntimeException('Kategori tujuan tidak ditemukan.');
                            }
                        }

                        db_run('UPDATE files SET category_id = ? WHERE category_id = ?', [$targetId, $deleteId]);
                    }

                    db_run('DELETE FROM categories WHERE id = ?', [$deleteId]);

                    db()->commit();

                    flash(
                        'success',
                        $fileCount > 0
                            ? 'Kategori "' . $category['name'] . '" dihapus. '
                                . $fileCount . ' file dipindahkan' . ($migrate !== '' ? ' ke kategori tujuan.' : ' menjadi tanpa kategori.')
                            : 'Kategori "' . $category['name'] . '" berhasil dihapus.'
                    );
                } catch (Throwable $e) {
                    if (db()->inTransaction()) {
                        db()->rollBack();
                    }

                    error_log('Hapus kategori gagal: ' . $e->getMessage());
                    flash('error', 'Gagal menghapus kategori. Silakan coba lagi.');
                }
            }
        }

        redirect('category.php');
    }

    // --------------------------------------------------------
    // TAMBAH atau EDIT KATEGORI
    // --------------------------------------------------------
    if ($action === 'create' || $action === 'update') {
        $name        = trim((string) ($_POST['name'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));

        if ($name === '') {
            $errors[] = 'Nama kategori wajib diisi.';
        } elseif (mb_strlen($name) > 100) {
            $errors[] = 'Nama kategori maksimal 100 karakter.';
        }

        if (mb_strlen($description) > 500) {
            $errors[] = 'Deskripsi maksimal 500 karakter.';
        }

        // Nama kategori harus unik (tabel punya UNIQUE KEY)
        if ($name !== '') {
            $isTaken = db_value(
                'SELECT id FROM categories WHERE name = ? AND id <> ?',
                [$name, $editId]
            );

            if ($isTaken !== null) {
                $errors[] = 'Kategori dengan nama "' . $name . '" sudah ada.';
            }
        }

        if ($errors === []) {
            try {
                if ($action === 'update') {
                    $existing = find_category($editId);

                    if ($existing === null) {
                        flash('error', 'Kategori tidak ditemukan.');
                        redirect('category.php');
                    }

                    db_run(
                        'UPDATE categories SET name = ?, description = ?, updated_at = ? WHERE id = ?',
                        [$name, $description !== '' ? $description : null, date('Y-m-d H:i:s'), $editId]
                    );

                    flash('success', 'Kategori "' . $name . '" berhasil diperbarui.');
                } else {
                    db_run(
                        'INSERT INTO categories (name, description, created_at, updated_at) VALUES (?, ?, ?, ?)',
                        [$name, $description !== '' ? $description : null, date('Y-m-d H:i:s'), date('Y-m-d H:i:s')]
                    );

                    flash('success', 'Kategori "' . $name . '" berhasil ditambahkan.');
                }

                redirect('category.php');
            } catch (Throwable $e) {
                error_log('Simpan kategori gagal: ' . $e->getMessage());
                flash('error', 'Gagal menyimpan kategori. Silakan coba lagi.');
                redirect('category.php');
            }
        }

        // Ada error: tampilkan lagi form dengan pesan
        if ($action === 'update') {
            $editMode = true;
            $editData = [
                'id'          => $editId,
                'name'        => $name,
                'description' => $description,
            ];
        }

        $_SESSION['category_errors'] = $errors;
        $_SESSION['category_old']    = [
            'name'        => $name,
            'description' => $description,
        ];

        flash('error', 'Kategori tidak dapat disimpan.');
        redirect('category.php?action=edit&id=' . $editId);
    }
}

// ============================================================
// PERSIAPAN UNTUK TAMPILAN
// ============================================================

$errors = $_SESSION['category_errors'] ?? [];
$old    = $_SESSION['category_old'] ?? [];
unset($_SESSION['category_errors'], $_SESSION['category_old']);

// Mode edit? ambil dari URL
if (! $editMode && $action === 'edit' && $editId > 0) {
    $existing = find_category($editId);

    if ($existing !== null) {
        $editMode = true;
        $editData = $existing;
    }
}

// Ambil semua kategori + jumlah file tiap kategori
$categories = db_all(
    'SELECT c.*, COUNT(f.id) AS file_count
       FROM categories c
  LEFT JOIN files f ON f.category_id = c.id
   GROUP BY c.id
   ORDER BY c.name ASC'
);

// Hitung file tanpa kategori
$uncategorizedCount = (int) db_value('SELECT COUNT(*) FROM files WHERE category_id IS NULL');

$pageTitle  = 'Kategori';
$activeMenu = 'category';
require __DIR__ . '/includes/header.php';
?>

<div class="page-head">
    <div>
        <h2>Kategori</h2>
        <p>Kelola daftar kategori file. Total <?= e((string) count($categories)) ?> kategori.</p>
    </div>
    <button type="button" class="btn btn-primary" id="newCategoryBtn">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
             stroke-linecap="round" stroke-linejoin="round">
            <line x1="12" y1="5" x2="12" y2="19"></line>
            <line x1="5" y1="12" x2="19" y2="12"></line>
        </svg>
        Tambah Kategori
    </button>
</div>

<div class="detail-layout">
    <!-- ================= DAFTAR KATEGORI ================= -->
    <div class="card">
        <div class="card-header">
            <h3>Daftar Kategori</h3>
        </div>

        <div class="table-wrap">
            <table class="table" style="min-width: 520px;">
                <thead>
                    <tr>
                        <th style="width:48px;" class="text-center">No</th>
                        <th>Nama Kategori</th>
                        <th>Deskripsi</th>
                        <th class="text-center" style="width:100px;">Jumlah File</th>
                        <th class="text-right" style="width:190px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($categories === []): ?>
                        <tr>
                            <td colspan="5" style="padding:0;">
                                <div class="empty-state">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                        <path d="M3 7a2 2 0 0 1 2-2h4l2 2h9a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                                    </svg>
                                    <h3>Belum ada kategori</h3>
                                    <p>Tambahkan kategori pertama Anda.</p>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($categories as $index => $cat): ?>
                            <tr>
                                <td class="text-center text-gray"><?= e((string) ($index + 1)) ?></td>

                                <td>
                                    <p class="cell-name"><?= e((string) $cat['name']) ?></p>
                                </td>

                                <td class="text-gray">
                                    <?= $cat['description'] ? e((string) $cat['description']) : '-' ?>
                                </td>

                                <td class="text-center">
                                    <?php if ((int) $cat['file_count'] > 0): ?>
                                        <a href="<?= e(build_list_url(['category' => (string) $cat['id']])) ?>">
                                            <span class="badge badge-blue"><?= e((string) $cat['file_count']) ?></span>
                                        </a>
                                    <?php else: ?>
                                        <span class="badge">0</span>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <div class="action-group">
                                        <a href="<?= e(build_list_url(['category' => (string) $cat['id']])) ?>"
                                           class="btn btn-sm btn-outline">Lihat File</a>

                                        <a href="category.php?action=edit&id=<?= e((string) $cat['id']) ?>#categoryForm"
                                           class="btn btn-sm btn-amber-soft">Edit</a>

                                        <?php if ((int) $cat['file_count'] > 0): ?>
                                            <!--
                                                Kategori yang dipakai file tidak dihapus langsung.
                                                Buka modal agar user memilih tujuan pemindahan.
                                            -->
                                            <button type="button" class="btn btn-sm btn-danger-soft"
                                                    data-delete-category
                                                    data-id="<?= e((string) $cat['id']) ?>"
                                                    data-name="<?= e((string) $cat['name']) ?>"
                                                    data-count="<?= e((string) $cat['file_count']) ?>">
                                                Hapus
                                            </button>
                                        <?php else: ?>
                                            <form method="POST" action="category.php" style="display:inline;"
                                                  data-confirm="Yakin ingin menghapus kategori &quot;<?= e((string) $cat['name']) ?>&quot;?">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?= e((string) $cat['id']) ?>">
                                                <button type="submit" class="btn btn-sm btn-danger-soft">Hapus</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <!-- Baris info file tanpa kategori -->
                        <?php if ($uncategorizedCount > 0): ?>
                            <tr>
                                <td class="text-center text-gray">-</td>
                                <td>
                                    <p class="cell-name" style="color:var(--text-muted);">
                                        Tanpa Kategori
                                    </p>
                                </td>
                                <td class="text-gray">File yang tidak punya kategori</td>
                                <td class="text-center">
                                    <a href="<?= e(build_list_url(['category' => '0'])) ?>">
                                        <span class="badge"><?= e((string) $uncategorizedCount) ?></span>
                                    </a>
                                </td>
                                <td class="text-gray" style="font-size:0.8rem;">—</td>
                            </tr>
                        <?php endif; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ================= FORM TAMBAH / EDIT ================= -->
    <div class="card" id="categoryFormCard">
        <div class="card-header">
            <h3 id="categoryFormTitle">
                <?= $editMode ? 'Edit Kategori' : 'Tambah Kategori' ?>
            </h3>
            <?php if ($editMode): ?>
                <a href="category.php" class="btn btn-sm btn-outline">Batal Edit</a>
            <?php endif; ?>
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
                        <ul>
                            <?php foreach ($errors as $error): ?>
                                <li><?= e($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            <?php endif; ?>

            <form method="POST" action="category.php" id="categoryForm" data-loading>
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="<?= $editMode ? 'update' : 'create' ?>">
                <?php if ($editMode): ?>
                    <input type="hidden" name="id" value="<?= e((string) $editData['id']) ?>">
                <?php endif; ?>

                <div class="form-row">
                    <label class="form-label" for="category-name">Nama Kategori <span class="req">*</span></label>
                    <input class="form-control" type="text" name="name" id="category-name"
                           value="<?= e($editMode ? (string) $editData['name'] : (string) ($old['name'] ?? '')) ?>"
                           maxlength="100" required
                           placeholder="Contoh: Dokumen Akademik">
                </div>

                <div class="form-row">
                    <label class="form-label" for="category-description">Deskripsi</label>
                    <textarea class="form-control" name="description" id="category-description"
                              rows="3" maxlength="500"
                              placeholder="Keterangan singkat kategori"><?= e($editMode ? (string) ($editData['description'] ?? '') : (string) ($old['description'] ?? '')) ?></textarea>
                </div>

                <button type="submit" class="btn btn-primary btn-block">
                    <span class="spinner" aria-hidden="true"></span>
                    <?= $editMode ? 'Simpan Perubahan' : 'Tambah Kategori' ?>
                </button>
            </form>

            <!--
                Contoh kategori yang disarankan (dipakai untuk membantu
                user memulai). Klik untuk mengisi otomatis.
            -->
            <div class="alert alert-info" style="margin-top:20px; margin-bottom:0;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="12" y1="16" x2="12" y2="12"></line>
                    <line x1="12" y1="8" x2="12.01" y2="8"></line>
                </svg>
                <div>
                    <strong>Contoh kategori:</strong><br>
                    Dokumen Akademik, Dokumen Administrasi, Surat, Laporan,
                    Proposal, Skripsi, Lainnya.
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ============================================================
     MODAL HAPUS KATEGORI YANG DIPAKAI FILE
     Muncul hanya bila kategori masih punya file.
     ============================================================ -->
<div class="modal-backdrop" id="deleteCategoryModal" hidden>
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="deleteCategoryTitle">
        <div class="modal-header">
            <h2 id="deleteCategoryTitle">Hapus Kategori</h2>
            <button type="button" class="btn-icon" data-modal-close aria-label="Tutup">&times;</button>
        </div>

        <div class="modal-body">
            <div class="alert alert-error" role="alert" style="margin-bottom:16px;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="12" y1="8" x2="12" y2="12"></line>
                    <line x1="12" y1="16" x2="12.01" y2="16"></line>
                </svg>
                <div>
                    Kategori <strong id="dcName"></strong> masih dipakai oleh
                    <strong id="dcCount"></strong> file.
                    File TIDAK akan ikut terhapus, tetapi kategorinya akan hilang.
                    Pilih tindakan:
                </div>
            </div>

            <form method="POST" action="category.php" id="deleteCategoryForm">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" id="dcId" value="">

                <div class="form-row">
                    <label class="form-label" for="dcMigrate">Pindahkan file ke kategori</label>
                    <select class="form-control" name="migrate_to" id="dcMigrate">
                        <option value="">-- Jadikan Tanpa Kategori --</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= e((string) $cat['id']) ?>" data-exclude="<?= e((string) $cat['id']) ?>">
                                <?= e((string) $cat['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="form-hint">
                        File yang dipindahkan tidak ikut terhapus,
                        hanya kategori Assigned-nya yang berubah.
                    </p>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" data-modal-close>Batal</button>
                    <button type="submit" class="btn btn-danger">Hapus Kategori</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Script kecil khusus halaman ini -->
<script>
/*
 * Mengisi form kategori: mode edit dimulai dari tombol Edit
 * yang menuju category.php?action=edit&id=..., sementara tombol
 * "Tambah Kategori" mengembalikan form ke mode tambah.
 */
(function () {
    'use strict';

    var newBtn = document.getElementById('newCategoryBtn');

    if (newBtn) {
        newBtn.addEventListener('click', function (event) {
            event.preventDefault();
            window.location.href = 'category.php#categoryForm';
        });
    }

    /* --- Modal hapus kategori yang masih dipakai file --- */
    var modal = document.getElementById('deleteCategoryModal');

    if (!modal) { return; }

    var nameEl = document.getElementById('dcName');
    var countEl = document.getElementById('dcCount');
    var idEl = document.getElementById('dcId');
    var migrateSel = document.getElementById('dcMigrate');

    document.querySelectorAll('[data-delete-category]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var id    = btn.getAttribute('data-id');
            var name  = btn.getAttribute('data-name');
            var count = btn.getAttribute('data-count');

            nameEl.textContent = name;
            countEl.textContent = count;
            idEl.value = id;

            // Sembunyikan opsi kategori yang sedang dihapus
            Array.prototype.forEach.call(migrateSel.options, function (option) {
                option.hidden = (option.getAttribute('data-exclude') === id);
                option.disabled = (option.getAttribute('data-exclude') === id);
            });

            modal.hidden = false;
            document.body.style.overflow = 'hidden';
        });
    });

    // Tombol tutup
    modal.querySelectorAll('[data-modal-close]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            modal.hidden = true;
            document.body.style.overflow = '';
        });
    });

    // Klik area gelap
    modal.addEventListener('click', function (event) {
        if (event.target === modal) {
            modal.hidden = true;
            document.body.style.overflow = '';
        }
    });
})();
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
