<?php
/**
 * PREVIEW.PHP
 * -----------------------------------------------------------------
 * Menampilkan isi file langsung di browser (inline) untuk PDF dan gambar.
 *
 * Kenapa perlu file ini?
 *   Folder uploads/ sengaja DITUTUP agar file .php di dalamnya tidak
 *   bisa dieksekusi (lihat uploads/.htaccess). Karena itu browser
 *   tidak bisa membuka file langsung lewat URL folder uploads.
 *
 *   File ini menjadi "jembatan": menerima permintaan, memeriksa
 *   otorisasi, lalu mengirim isi file dengan header yang benar.
 */

require_once __DIR__ . '/includes/bootstrap.php';

require_login();

// ============================================================
// AMBIL DATA FILE
// ============================================================

$id = (int) ($_GET['id'] ?? 0);

if ($id <= 0) {
    http_response_code(400);
    exit('ID tidak valid.');
}

$file = find_file($id);

if ($file === null) {
    http_response_code(404);
    exit('File tidak ditemukan.');
}

// ============================================================
// KEAMANAN: hanya izinkan format yang bisa ditampilkan
// ============================================================

$ext = file_extension((string) $file['original_name']);

$previewable = [
    'pdf'  => 'application/pdf',
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png'  => 'image/png',
];

if (! isset($previewable[$ext])) {
    http_response_code(415);
    exit('Format file ini tidak dapat ditampilkan di browser. Gunakan tombol Download.');
}

// ============================================================
// CEK FILE FISIK
// ============================================================

$path = upload_path((string) $file['file_path']);

if ($path === null) {
    http_response_code(404);
    exit('File tidak ditemukan di server.');
}

// ============================================================
// KIRIM HEADER & ISI FILE
// ============================================================

$mime        = $previewable[$ext];
$fileSize    = (int) $file['file_size'];
$downloadName = (string) $file['original_name'];

// Header keamanan: cegah file ini dipakai sebagai HTML/JS (cegah XSS lewat SVG/HTML)
header('Content-Type: ' . $mime);
header('Content-Length: ' . (string) (filesize($path) ?: $fileSize));
header('Content-Disposition: inline; filename="' . str_replace('"', '', $downloadName) . '"');
header('X-Content-Type-Options: nosniff');
header('Content-Security-Policy: default-src \'none\'; img-src \'self\'; object-src \'self\'; frame-ancestors \'self\'');
header('Cache-Control: private, max-age=3600');

// Matikan output buffering bawaan PHP agar file tidak ikut terkompresi tak sengaja
while (ob_get_level() > 0) {
    ob_end_clean();
}

// Kirim isi file ke browser dalam potongan (chunk) supaya hemat memory
$handle = fopen($path, 'rb');

if ($handle === false) {
    http_response_code(500);
    exit('Gagal membaca file.');
}

while (! feof($handle)) {
    echo fread($handle, 8192);
    flush();
}

fclose($handle);
exit;
