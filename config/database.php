<?php
/**
 * KONEKSI DATABASE (PDO MySQL)
 * -----------------------------------------------------------------
 * File ini adalah satu-satunya tempat koneksi database dibuat.
 * Semua halaman cukup require_once file ini, lalu memakai fungsi db().
 *
 * Keamanan:
 *  - Semua query memakai Prepared Statement (placeholder ?), sehingga
 *    input user tidak pernah digabung langsung ke dalam string SQL.
 *  - ATTR_ERRMODE = ERRMODE_EXCEPTION membuat error dilempar sebagai
 *    PDOException supaya mudah dilacak, bukan gagal diam-diam.
 *  - ATTR_EMULATE_PREPARES = false memaksa MySQL menyiapkan query di
 *    sisi server (lebih aman dan sedikit lebih cepat).
 */

// ========================= KONFIGURASI =========================
// Ubah nilai di bawah ini sesuai pengaturan MySQL Anda.

define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'file_storage');
define('DB_USER', 'root');
define('DB_PASS', '');            // isi bila MySQL Anda memakai password
define('DB_CHARSET', 'utf8mb4');

/**
 * Membuat koneksi PDO dan menyimpannya di memori.
 *
 * Kata kunci static di sini berarti: fungsi dipanggil berkali-kali,
 * koneksi hanya benar-benar dibuat pada panggilan pertama.
 * Pola ini disebut singleton.
 *
 * @return PDO
 */
function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=%s',
        DB_HOST,
        DB_PORT,
        DB_NAME,
        DB_CHARSET
    );

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    } catch (PDOException $e) {
        // Jangan tampilkan detail koneksi ke layar pengguna.
        error_log('Koneksi database gagal: ' . $e->getMessage());
        http_response_code(500);
        exit('Koneksi database gagal. Periksa config/database.php.');
    }

    return $pdo;
}

/**
 * Jalankan query lalu kembalikan statement-nya.
 *
 * Contoh pakai:
 *   $rows  = db_all('SELECT * FROM categories');
 *   $file  = db_one('SELECT * FROM files WHERE id = ?', [$id]);
 *   db_run('DELETE FROM files WHERE id = ?', [$id]);
 */
function db_run(string $sql, array $params = []): PDOStatement
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    return $stmt;
}

/** Ambil semua baris hasil query. */
function db_all(string $sql, array $params = []): array
{
    return db_run($sql, $params)->fetchAll();
}

/** Ambil satu baris, atau null jika tidak ada. */
function db_one(string $sql, array $params = []): ?array
{
    $row = db_run($sql, $params)->fetch();

    return $row === false ? null : $row;
}

/** Ambil satu nilai kolom pertama (berguna untuk COUNT/SUM). */
function db_value(string $sql, array $params = []): mixed
{
    $value = db_run($sql, $params)->fetchColumn();

    return $value === false ? null : $value;
}
