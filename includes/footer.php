<?php
/**
 * FOOTER
 * -----------------------------------------------------------------
 * Menutup tag yang dibuka di header.php, menampilkan container
 * untuk toast notification, dan memuat JavaScript.
 */

$flash = $flash ?? take_flash();
?>
        </main><!-- /.content -->

        <footer class="footer">
            <p>
                Sistem Penyimpanan dan Pengelolaan File &mdash;
                dibuat dengan PHP <?= e(PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION) ?>, MySQL, dan Vanilla JS.
            </p>
        </footer>
    </div><!-- /.main-wrapper -->
</div><!-- /.app-layout -->

<!-- ================= TOAST NOTIFICATION ================= -->
<!--
    Toast muncul otomatis lalu hilang sendiri.
    Dipanggil dari JS lewat fungsi window.showToast() atau
    elemen data-toast yang di-trigger.
-->
<div class="toast-container" id="toastContainer" aria-live="polite" aria-atomic="true">
    <?php if ($flash): ?>
        <div class="toast toast-<?= e($flash['type']) ?>" data-toast>
            <span class="toast-icon" aria-hidden="true">
                <?= $flash['type'] === 'success' ? '&#10003;' : '!' ?>
            </span>
            <div class="toast-body"><?= e($flash['message']) ?></div>
            <button type="button" class="toast-close" data-toast-close aria-label="Tutup">&times;</button>
        </div>
    <?php endif; ?>
</div>

<!-- ================= MODAL UPLOAD (global, dipakai di beberapa halaman) ================= -->
<div class="modal-backdrop" id="uploadModal" hidden>
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="uploadModalTitle">
        <div class="modal-header">
            <h2 id="uploadModalTitle">Upload File</h2>
            <button type="button" class="btn-icon" data-modal-close aria-label="Tutup">&times;</button>
        </div>
        <div class="modal-body">
            <!--
                Form ini selalu ada di layout supaya bisa dipanggil dari
                tombol mana pun lewat JS tanpa perlu memuat ulang halaman.
                File dikirim ke upload.php.
            -->
            <form method="POST" action="upload.php" enctype="multipart/form-data" id="uploadForm" novalidate>
                <?= csrf_field() ?>

                <div class="form-row">
                    <label class="form-label" for="upload-file">Pilih File <span class="req">*</span></label>
                    <!--
                        multiple = izinkan pilih beberapa file sekaligus.
                        JavaScript tetap memvalidasi ukuran per file.
                    -->
                    <input class="form-control" type="file" name="files[]" id="upload-file"
                           multiple required
                           accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png,.zip">
                    <p class="form-hint">
                        PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX, JPG, JPEG, PNG, ZIP. Maksimal 10 MB per file.
                    </p>
                    <!-- Daftar file yang dipilih, diisi oleh JavaScript -->
                    <ul class="file-list" id="fileList" aria-live="polite"></ul>
                </div>

                <div class="form-row">
                    <label class="form-label" for="upload-name">Nama File <span class="req">*</span></label>
                    <input class="form-control" type="text" name="file_name" id="upload-name"
                           maxlength="150" required placeholder="Contoh: Surat Undangan Rapat">
                    <p class="form-hint">Boleh berbeda dari nama file asli.</p>
                </div>

                <div class="form-row">
                    <label class="form-label" for="upload-category">Kategori</label>
                    <select class="form-control" name="category_id" id="upload-category">
                        <option value="">-- Tanpa Kategori --</option>
                        <?php foreach (all_categories() as $cat): ?>
                            <option value="<?= e((string) $cat['id']) ?>"><?= e($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-row">
                    <label class="form-label" for="upload-description">Deskripsi</label>
                    <textarea class="form-control" name="description" id="upload-description"
                              rows="3" maxlength="1000" placeholder="Keterangan singkat tentang file"></textarea>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" data-modal-close>Batal</button>
                    <button type="submit" class="btn btn-primary" data-btn-loading>
                        <span class="spinner" aria-hidden="true"></span>
                        <span>Upload</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- JavaScript -->
<script src="assets/js/script.js?v=1"></script>
</body>
</html>
