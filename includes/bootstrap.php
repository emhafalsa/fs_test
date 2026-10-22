<?php
/**
 * BOOTSTRAP
 * -----------------------------------------------------------------
 * Dipanggil pertama kali di setiap halaman.
 * Isinya:
 *   1. Session + cookie keamanan
 *   2. Inisialisasi session login
 *   3. Konfigurasi upload (ukuran, ekstensi, MIME)
 *   4. Fungsi bantu (helper): escape, ukuran file, CSRF, flash message
 *
 * Halaman hanya perlu: require_once __DIR__ . '/includes/bootstrap.php';
 */

declare(strict_types=1);

// -------------------------------------------------------------
// 1. SESSION
// -------------------------------------------------------------

// Parameter cookie dibuat lebih dulu agar session_start() memakainya.
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,   // JavaScript tidak boleh membaca cookie session
        'samesite' => 'Lax',  // mencegah cookie ikut pada request dari situs lain
    ]);
    session_name('FILESTORAGE_SESSID');
    session_start();
}

// -------------------------------------------------------------
// 2. KONEKSI DATABASE
// -------------------------------------------------------------
require_once __DIR__ . '/../config/database.php';

// -------------------------------------------------------------
// 3. KONFIGURASI APLIKASI
// -------------------------------------------------------------

/** Ukuran maksimal upload: 10 MB dalam byte. */
define('MAX_FILE_SIZE', 10 * 1024 * 1024);

/** Ekstensi yang boleh diunggah (tanpa titik, huruf kecil). */
const ALLOWED_EXTENSIONS = [
    'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
    'jpg', 'jpeg', 'png', 'zip',
];

/**
 * MIME type yang diizinkan per ekstensi.
 * Dipakai untuk memverifikasi isi file, bukan sekadar nama file.
 */
const ALLOWED_MIME_TYPES = [
    'pdf'  => ['application/pdf'],
    'doc'  => ['application/msword'],
    'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
    'xls'  => ['application/vnd.ms-excel'],
    'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
    'ppt'  => ['application/vnd.ms-powerpoint'],
    'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation'],
    'jpg'  => ['image/jpeg'],
    'jpeg' => ['image/jpeg'],
    'png'  => ['image/png'],
    'zip'  => ['application/zip', 'application/x-zip', 'application/x-zip-compressed'],
];

/** Folder tempat file fisik disimpan. */
define('UPLOAD_DIR', __DIR__ . '/../uploads');

// -------------------------------------------------------------
// 4. HELPER: OUTPUT & KEAMANAN
// -------------------------------------------------------------

/**
 * Escape output HTML (pencegahan XSS).
 *
 * Setiap data dari database yang ditampilkan ke HTML harus melewati
 * fungsi ini, supaya karakter seperti <script> tidak dieksekusi browser.
 */
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Redirect lalu hentikan eksekusi script.
 */
function redirect(string $path): never
{
    header('Location: ' . $path);
    exit;
}

/**
 * Simpan pesan singkat untuk ditampilkan pada request berikutnya.
 *
 * Dipakai untuk notifikasi seperti "File berhasil diupload" atau
 * pesan error validasi setelah form disubmit ulang.
 */
function flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

/**
 * Ambil sekaligus hapus pesan flash.
 */
function take_flash(): ?array
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);

    return $flash;
}

// -------------------------------------------------------------
// 5. HELPER: LOGIN SEDERHANA
// -------------------------------------------------------------
// Ini untuk keperluan demonstrasi/pembelajaran, belum memakai user table.
// Di aplikasi nyata, ganti dengan tabel users + password_hash().

/**
 * Data akun bawaan (dipakai selama settings belum pernah disimpan).
 * Ganti lewat halaman settings.php, bukan di sini.
 */
const DEMO_USER = 'admin';
const DEMO_PASS = 'admin123';

/** Lokasi file pengaturan (diubah lewat settings.php). */
define('SETTINGS_FILE', __DIR__ . '/../storage_settings.json');

/**
 * Baca pengaturan yang disimpan settings.php.
 *
 * Kalau file belum ada (instalasi baru), kembalikan nilai bawaan.
 * Password disimpan sebagai hash, bukan teks polos.
 */
function app_settings(): array
{
    static $cache = null;

    if ($cache !== null) {
        return $cache;
    }

    $defaults = [
        'username'      => DEMO_USER,
        'password_hash' => password_hash(DEMO_PASS, PASSWORD_DEFAULT),
        'max_size_mb'   => 10,
    ];

    if (! is_file(SETTINGS_FILE)) {
        return $cache = $defaults;
    }

    $decoded = json_decode((string) file_get_contents(SETTINGS_FILE), true);

    if (! is_array($decoded)) {
        return $cache = $defaults;
    }

    return $cache = array_merge($defaults, $decoded);
}

/**
 * Cek kredensial login.
 *
 * Password dibandingkan memakai password_verify() sehingga aman
 * walaupun file pengaturan dibaca orang lain.
 */
function verify_login(string $username, string $password): bool
{
    $settings = app_settings();

    if (! hash_equals((string) $settings['username'], $username)) {
        return false;
    }

    return password_verify($password, (string) $settings['password_hash']);
}

/**
 * Batas ukuran upload aktif, dalam byte.
 *
 * Nilai efektif = min(batas dari aplikasi, batas upload_max_filesize PHP).
 * Kalau batas di settings.php lebih besar dari batas PHP, yang dipakai
 * tetap yang paling kecil supaya upload tidak ditolak diam-diam.
 */
function active_max_file_size(): int
{
    $settings = app_settings();
    $fromApp  = (int) $settings['max_size_mb'] * 1024 * 1024;
    $fromPhp  = ini_bytes_to_int(ini_get('upload_max_filesize'));

    if ($fromPhp > 0) {
        return max(0, min($fromApp, $fromPhp));
    }

    return $fromApp;
}

/** Ubah "10M"/"8M"/"2G" menjadi byte. Return 0 bila tidak bisa dibaca. */
function ini_bytes_to_int(string $value): int
{
    $value = trim($value);

    if ($value === '') {
        return 0;
    }

    $unit   = strtolower(substr($value, -1));
    $number = (int) $value;

    return match ($unit) {
        'g'     => $number * 1024 * 1024 * 1024,
        'm'     => $number * 1024 * 1024,
        'k'     => $number * 1024,
        default => (int) $value,
    };
}

function is_logged_in(): bool
{
    return ! empty($_SESSION['auth_user']);
}

function current_user(): string
{
    return (string) ($_SESSION['auth_user'] ?? '');
}

/** Paksa user login sebelum mengakses halaman. */
function require_login(): void
{
    if (! is_logged_in()) {
        redirect('index.php');
    }
}

// -------------------------------------------------------------
// 6. HELPER: CSRF
// -------------------------------------------------------------
// CSRF = Cross Site Request Forgery. Serangan di mana situs lain
// diam-diam mengirim form ke situs kita. Solusinya: token rahasia
// yang harus ikut di setiap POST.

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

/** Cetak input hidden token CSRF (dipakai di dalam <form>). */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/**
 * Verifikasi token CSRF pada request POST.
 * Jika tidak cocok, hentikan request dengan pesan 403.
 */
function csrf_verify(): void
{
    $token = $_POST['csrf_token'] ?? '';

    if (! is_string($token) || ! hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        exit('Token keamanan tidak valid. Silakan muat ulang halaman.');
    }
}

// -------------------------------------------------------------
// 7. HELPER: FORMAT & UPLOAD
// -------------------------------------------------------------

/**
 * Ubah ukuran byte menjadi format yang enak dibaca.
 * Contoh: 1536 -> "1.5 KB"
 */
function format_bytes(int|float $bytes): string
{
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = 0;

    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }

    return round($bytes, $i === 0 ? 0 : 1) . ' ' . $units[$i];
}

/**
 * Ambil ekstensi file dari nama file (huruf kecil, tanpa titik).
 */
function file_extension(string $filename): string
{
    return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
}

/**
 * Buat nama file unik agar file dengan nama sama tidak saling menimpa.
 * Contoh: document_68fabc1234.pdf
 *
 * @param string $originalName Nama asli dari user
 * @return string
 */
function unique_filename(string $originalName): string
{
    $ext  = file_extension($originalName);
    $base = pathinfo($originalName, PATHINFO_FILENAME);

    // Bersihkan nama: hanya huruf, angka, underscore, strip
    $base = preg_replace('/[^A-Za-z0-9_-]/', '_', $base) ?? 'file';
    $base = substr($base, 0, 40); // batasi panjang agar nama file tidak kepanjangan
    $base = $base !== '' ? $base : 'file';

    return $base . '_' . bin2hex(random_bytes(4)) . ($ext !== '' ? '.' . $ext : '');
}

/**
 * Validasi satu file upload.
 *
 * Yang diperiksa:
 *   1. Error dari PHP saat upload (UPLOAD_ERR_OK)
 *   2. File benar-benar ada
 *   3. File tidak kosong
 *   4. Ukuran tidak melebihi batas
 *   5. Ekstensi diizinkan
 *   6. MIME type asli file sesuai (mencegah .php disamarkan jadi .pdf)
 *
 * @param  array $file       Elemen dari $_FILES
 * @return array{ok:bool, error:?string}
 */
function validate_upload(array $file): array
{
    if (! isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        $message = match ($file['error'] ?? UPLOAD_ERR_NO_FILE) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Ukuran file melebihi batas 10 MB.',
            UPLOAD_ERR_PARTIAL    => 'File hanya terunggah sebagian. Silakan coba lagi.',
            UPLOAD_ERR_NO_FILE    => 'File wajib dipilih.',
            UPLOAD_ERR_NO_TMP_DIR => 'Server tidak memiliki folder sementara.',
            UPLOAD_ERR_CANT_WRITE => 'Server gagal menulis file.',
            UPLOAD_ERR_EXTENSION  => 'Upload dihentikan oleh ekstensi PHP.',
            default               => 'File gagal diunggah.',
        };

        return ['ok' => false, 'error' => $message];
    }

    if (! is_uploaded_file($file['tmp_name'])) {
        return ['ok' => false, 'error' => 'Berkas tidak valid.'];
    }

    if ($file['size'] <= 0) {
        return ['ok' => false, 'error' => 'File kosong tidak diperbolehkan.'];
    }

    if ($file['size'] > active_max_file_size()) {
        return [
            'ok'    => false,
            'error' => 'Ukuran file maksimal ' . format_bytes(active_max_file_size()) . '.',
        ];
    }

    $originalName = (string) $file['name'];
    $ext          = file_extension($originalName);

    if ($ext === '' || ! in_array($ext, ALLOWED_EXTENSIONS, true)) {
        return [
            'ok'    => false,
            'error' => 'Ekstensi tidak diizinkan. Gunakan: ' . strtoupper(implode(', ', ALLOWED_EXTENSIONS)) . '.',
        ];
    }

    // Cek MIME type asli file. new \finfo() membaca isi file, bukan nama.
    $finfo    = new finfo(FILEINFO_MIME_TYPE);
    $realMime = $finfo->file($file['tmp_name']);

    $allowedMimes = ALLOWED_MIME_TYPES[$ext] ?? [];

    if (! in_array($realMime, $allowedMimes, true)) {
        return [
            'ok'    => false,
            'error' => "Isi file tidak sesuai dengan ekstensi .{$ext} (deteksi: {$realMime}).",
        ];
    }

    return ['ok' => true, 'error' => null];
}

/**
 * Pindahkan file dari folder sementara ke folder uploads/.
 *
 * Nama file yang dipakai adalah nama unik hasil unique_filename(),
 * bukan nama asli dari user. Ini mencegah:
 *   - file menimpa file lain dengan nama sama
 *   - path traversal seperti "../../config/database.php"
 */
function move_uploaded(array $file): array
{
    $storedName = unique_filename((string) $file['name']);
    $target     = UPLOAD_DIR . DIRECTORY_SEPARATOR . $storedName;

    if (! move_uploaded_file($file['tmp_name'], $target)) {
        return ['ok' => false, 'error' => 'Gagal menyimpan file ke folder uploads.'];
    }

    return ['ok' => true, 'error' => null, 'stored_name' => $storedName];
}

/**
 * Hapus file fisik dari folder uploads/ dengan aman.
 *
 * basename() memastikan tidak ada "../" yang lolos, sehingga
 * fungsi ini tidak bisa menghapus file di luar folder uploads.
 *
 * @return bool true bila file benar-benar terhapus
 */
function delete_uploaded_file(?string $storedName): bool
{
    if ($storedName === null || $storedName === '') {
        return false;
    }

    $safeName = basename($storedName);
    $path     = UPLOAD_DIR . DIRECTORY_SEPARATOR . $safeName;

    if (is_file($path)) {
        return unlink($path);
    }

    // File memang tidak ada (mungkin sudah dihapus manual)
    return true;
}

/**
 * Path absolut file dengan protections terhadap path traversal.
 */
function upload_path(string $storedName): ?string
{
    $safeName = basename($storedName);
    $path     = UPLOAD_DIR . DIRECTORY_SEPARATOR . $safeName;

    return is_file($path) ? $path : null;
}

// -------------------------------------------------------------
// 8. HELPER: QUERY DASHBOARD & KATEGORI
// -------------------------------------------------------------

/**
 * Ratakan struktur $_FILES untuk input "files[]" (multi upload).
 *
 * $_FILES untuk beberapa file tersusun "datar" per atribut:
 *   $_FILES['files']['name'][0], ['name'][1], ...
 * sedangkan tiap file butuh semua atributnya dikelompokkan.
 * Fungsi ini mengubahnya menjadi daftar file biasa:
 *   [ ['name'=>..,'tmp_name'=>..,...], ['name'=>..,...] ]
 *
 * @param  array $field Struktur $_FILES['files']
 * @return array<int, array<string, mixed>>
 */
function rearray_files(array $field): array
{
    $result = [];

    if (! isset($field['name']) || ! is_array($field['name'])) {
        return $result;
    }

    $count = count($field['name']);

    for ($i = 0; $i < $count; $i++) {
        $result[] = [
            'name'     => $field['name'][$i]     ?? '',
            'type'     => $field['type'][$i]     ?? '',
            'tmp_name' => $field['tmp_name'][$i] ?? '',
            'error'    => $field['error'][$i]    ?? UPLOAD_ERR_NO_FILE,
            'size'     => $field['size'][$i]     ?? 0,
        ];
    }

    return $result;
}

/**
 * Ambil semua kategori, diurutkan berdasarkan nama.
 */
function all_categories(): array
{
    return db_all('SELECT * FROM categories ORDER BY name ASC');
}

/**
 * Ambil satu kategori berdasarkan id.
 */
function find_category(int $id): ?array
{
    return db_one('SELECT * FROM categories WHERE id = ?', [$id]);
}

/**
 * Hitung jumlah file pada sebuah kategori.
 * Dipakai untuk mencegah kategori dihapus saat masih dipakai.
 */
function category_file_count(int $categoryId): int
{
    return (int) db_value('SELECT COUNT(*) FROM files WHERE category_id = ?', [$categoryId]);
}

/**
 * Ambil satu file berdasarkan id, sekaligus nama kategorinya.
 * LEFT JOIN supaya file tanpa kategori tetap muncul.
 */
function find_file(int $id): ?array
{
    return db_one(
        'SELECT f.*, c.name AS category_name
           FROM files f
      LEFT JOIN categories c ON c.id = f.category_id
          WHERE f.id = ?',
        [$id]
    );
}

/**
 * Daftar file dengan pencarian, filter kategori, sorting, dan pagination.
 *
 * Semua input user masuk sebagai PARAMETER (?), bukan disisipkan ke SQL.
 * Nilai sort & arahnya dicek lewat daftar putih (whitelist) supaya
 * tidak mungkin terjadi SQL Injection lewat parameter sort.
 *
 * @param array $options ['search'=>, 'category'=>, 'sort'=>, 'dir'=>, 'page'=>, 'per_page'=>]
 * @return array{data:array, total:int, page:int, per_page:int, pages:int}
 */
function list_files(array $options = []): array
{
    $search   = trim((string) ($options['search'] ?? ''));
    $category = trim((string) ($options['category'] ?? ''));
    $sort     = (string) ($options['sort'] ?? 'uploaded_at');
    $dir      = strtolower((string) ($options['dir'] ?? 'desc'));
    $page     = max(1, (int) ($options['page'] ?? 1));
    $perPage  = min(100, max(5, (int) ($options['per_page'] ?? 10)));

    // --- Daftar putih kolom & arah sorting (anti SQL Injection) ---
    $sortable = [
        'file_name'    => 'f.file_name',
        'original_name'=> 'f.original_name',
        'file_type'    => 'f.file_type',
        'file_size'    => 'f.file_size',
        'uploaded_at'  => 'f.uploaded_at',
        'category'     => 'c.name',
    ];

    $orderBy = $sortable[$sort] ?? 'f.uploaded_at';
    $orderDir = $dir === 'asc' ? 'ASC' : 'DESC';

    // --- WHERE dinamis ---
    $where  = [];
    $params = [];

    if ($search !== '') {
        $where[]  = '(f.file_name LIKE ? OR f.original_name LIKE ? OR f.description LIKE ?)';
        $keyword  = '%' . $search . '%';
        $params[] = $keyword;
        $params[] = $keyword;
        $params[] = $keyword;
    }

    if ($category !== '' && ctype_digit($category)) {
        $where[]  = 'f.category_id = ?';
        $params[] = (int) $category;
    }

    $whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

    // --- Total data (untuk menghitung jumlah halaman) ---
    $countSql = 'SELECT COUNT(*) FROM files f LEFT JOIN categories c ON c.id = f.category_id' . $whereSql;
    $total    = (int) db_value($countSql, $params);

    $pages    = max(1, (int) ceil($total / $perPage));
    $page     = min($page, $pages);
    $offset   = ($page - 1) * $perPage;

    // --- Ambil data halaman ini ---
    // LIMIT/OFFSET ikut di-cast ke integer, jadi aman.
    $dataSql = "SELECT f.*, c.name AS category_name
                      FROM files f
                 LEFT JOIN categories c ON c.id = f.category_id
                  $whereSql
                  ORDER BY $orderBy $orderDir
                     LIMIT $perPage OFFSET $offset";

    $data = db_all($dataSql, $params);

    return [
        'data'     => $data,
        'total'    => $total,
        'page'     => $page,
        'per_page' => $perPage,
        'pages'    => $pages,
    ];
}

/**
 * Angka statistik untuk dashboard.
 */
function dashboard_stats(): array
{
    $today = date('Y-m-d');

    return [
        'total_files'    => (int) db_value('SELECT COUNT(*) FROM files'),
        'total_size'     => (int) db_value('SELECT COALESCE(SUM(file_size), 0) FROM files'),
        'files_today'    => (int) db_value(
            'SELECT COUNT(*) FROM files WHERE DATE(uploaded_at) = ?',
            [$today]
        ),
        'total_category' => (int) db_value('SELECT COUNT(*) FROM categories'),
    ];
}

/**
 * N file terbaru untuk tabel "File Terbaru" di dashboard.
 */
function latest_files(int $limit = 5): array
{
    $limit = min(50, max(1, $limit));

    return db_all(
        "SELECT f.*, c.name AS category_name
           FROM files f
      LEFT JOIN categories c ON c.id = f.category_id
       ORDER BY f.uploaded_at DESC
          LIMIT $limit"
    );
}

/**
 * Bangun URL halaman daftar file dengan query string tertentu.
 * Dipakai supaya pagination & filter tetap membawa state yang sama.
 */
function build_list_url(array $params = []): string
{
    return 'index.php?' . http_build_query($params);
}
