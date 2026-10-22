<?php
/**
 * DOWNLOAD.PHP
 * -----------------------------------------------------------------
 * Mengirim file ke browser dengan header "Content-Disposition: attachment",
 * sehingga browser MENYIMPAN file, bukan menampilkannya di halaman.
 *
 * Nama file yang dikirim memakai original_name (nama asli dari user),
 * supaya file hasil download recognizable.
 *
 * Catatan keamanan:
 *   - Path file diambil lewat upload_path() yang memakai basename(),
 *     jadi parameter id tidak bisa dipakai untuk keluar dari folder uploads.
 *   - Hanya user yang sudah login yang boleh mengunduh.
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
    flash('error', 'File tidak ditemukan.');
    redirect('index.php');
}

// ============================================================
// CEK KETERSEDIAAN FILE FISIK
// ============================================================

$path = upload_path((string) $file['file_path']);

if ($path === null) {
    flash('error', 'File tidak ditemukan di server. Silakan upload ulang.');
    redirect('detail.php?id=' . $id);
}

// ============================================================
// KIRIM HEADER DOWNLOAD
// ============================================================

// Nama file yang dilihat user. Dibersihkan dari karakter yang
// bisa merusak header HTTP (CR/LF, tanda kutip).
$downloadName = str_replace(["\r", "\n", '"'], '', (string) $file['original_name']);
$downloadName = $downloadName !== '' ? $downloadName : 'download';

$mime     = (string) $file['file_type'];
$fileSize = filesize($path);

// Beberapa browser hanya menerima ASCII pada Content-Disposition.
// Nama beralfabet (bahasa Indonesia) perlu di-encode dengan RFC 5987.
$nameForHeader = preg_replace('/[^\x20-\x7E]/', '_', $downloadName) ?? 'download';
$nameEncoded   = rawurlencode($downloadName);

header('Content-Type: ' . ($mime !== '' ? $mime : 'application/octet-stream'));
header('Content-Length: ' . (string) $fileSize);
header('Content-Disposition: attachment; filename="' . $nameForHeader . '"; filename*=UTF-8\'\'' . $nameEncoded);
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

// Bersihkan output buffering supaya tidak ada teks lain yang ikut terkirim
while (ob_get_level() > 0) {
    ob_end_clean();
}

// Kirim isi file per chunk
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
