<?php
/**
 * DELETE.PHP
 * -----------------------------------------------------------------
 * Menghapus file: baris di database + file fisik di folder uploads/.
 *
 * Hanya boleh dipanggil lewat POST dengan token CSRF, supaya tidak
 * bisa terhapus hanya karena seseorang membuka link atau gambar
 * yang mengarah ke file ini.
 */

require_once __DIR__ . '/includes/bootstrap.php';

require_login();

// Hanya proses POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    flash('error', 'Metode tidak diizinkan.');
    redirect('index.php');
}

// Verifikasi token CSRF
csrf_verify();

$id = (int) ($_POST['id'] ?? 0);

if ($id <= 0) {
    flash('error', 'ID file tidak valid.');
    redirect('index.php');
}

// Ambil data file dulu (kita butuh nama file fisik sebelum dihapus)
$file = find_file($id);

if ($file === null) {
    flash('error', 'File tidak ditemukan. Mungkin sudah dihapus.');
    redirect('index.php');
}

$fileName    = (string) $file['file_name'];
$storedName  = (string) $file['file_path'];

try {
    // ============================================================
    // 1. Hapus dari database
    // ============================================================

    db_run('DELETE FROM files WHERE id = ?', [$id]);

    // ============================================================
    // 2. Hapus file fisik dari folder uploads/
    // ============================================================
    // Dilakukan setelah database sukses. Kalau file fisik gagal
    // dihapus, data database tetap bersih (lebih baik daripada
    // data hilang tapi file ny tertinggal).

    $deletedFile = delete_uploaded_file($storedName);

    flash(
        'success',
        $deletedFile
            ? 'File "' . $fileName . '" berhasil dihapus.'
            : 'Data "' . $fileName . '" dihapus, tetapi file fisik tidak ditemukan di server.'
    );
} catch (Throwable $e) {
    error_log('Hapus file gagal: ' . $e->getMessage());
    flash('error', 'Gagal menghapus file. Silakan coba lagi.');
}

redirect('index.php');
