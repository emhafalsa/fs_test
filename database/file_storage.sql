-- =============================================================
--  SISTEM PENYIMPANAN DAN PENGELOLAAN FILE
--  Database : file_storage (MySQL / MariaDB)
--  Import   : phpMyAdmin > Import, atau CLI
--             mysql -u root -p < file_storage.sql
-- =============================================================

-- 1. Buat database (hapus baris ini jika database sudah ada manual)
DROP DATABASE IF EXISTS `file_storage`;
CREATE DATABASE `file_storage`
    DEFAULT CHARACTER SET utf8mb4
    DEFAULT COLLATE utf8mb4_unicode_ci;

USE `file_storage`;

-- 2. Tabel categories
--    Disimpan lebih dulu karena tabel files memakai kategori ini (foreign key).
CREATE TABLE `categories` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`        VARCHAR(100) NOT NULL,
    `description` TEXT         NULL,
    `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_categories_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Tabel files
--    file_path      : nama file unik di dalam folder uploads/ (contoh: document_68fabc123.pdf)
--    original_name  : nama asli dari user (untuk ditampilkan & saat download)
--    file_type      : MIME type asli hasil pemeriksaan server
--    file_size      : ukuran dalam byte
--    category_id    : relasi ke categories.id (NULL = belum terkategori)
CREATE TABLE `files` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `file_name`     VARCHAR(255) NOT NULL COMMENT 'nama unik tersimpan di folder uploads',
    `original_name` VARCHAR(255) NOT NULL COMMENT 'nama asli dari user',
    `file_path`     VARCHAR(255) NOT NULL COMMENT 'path relatif dari folder uploads',
    `file_type`     VARCHAR(120) NOT NULL COMMENT 'MIME type',
    `file_size`     BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'ukuran byte',
    `category_id`   INT UNSIGNED NULL,
    `description`   TEXT         NULL,
    `uploaded_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_files_category` (`category_id`),
    KEY `idx_files_uploaded` (`uploaded_at`),
    CONSTRAINT `fk_files_category`
        FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`)
        ON DELETE SET NULL
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Data contoh kategori (bukan wajib, boleh dihapus)
INSERT INTO `categories` (`name`, `description`) VALUES
    ('Dokumen Akademik', 'Kuliah, proposal, skripsi, dan tugas'),
    ('Dokumen Administrasi', 'Surat keputusan, notulen, dan administrasi'),
    ('Surat', 'Surat masuk, surat keluar, dan disposisi'),
    ('Laporan', 'Laporan kegiatan dan laporan keuangan'),
    ('Proposal', 'Pengajuan kegiatan dan hibah'),
    ('Skripsi', 'Dokumen skripsi dan sidang'),
    ('Lainnya', 'Berkas lain yang belum terkategori');
