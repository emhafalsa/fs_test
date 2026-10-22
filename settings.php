<?php
/**
 * SETTINGS.PHP
 * -----------------------------------------------------------------
 * Halaman pengaturan sederhana.
 *
 * Yang bisa diatur:
 *   - Kredensial login (username & password)
 *   - Batas ukuran file maksimal
 *
 * Nilai disimpan di file teks storage_settings.json supaya tidak perlu
 * tabel database tambahan. Password disimpan sebagai hash lewat
 * password_hash() dan dicek dengan password_verify() di verify_login().
 *
 * CATATAN: untuk aplikasi nyata dengan banyak user, pakai tabel users
 * plus password_hash() per user.
 */

require_once __DIR__ . '/includes/bootstrap.php';

require_login();

// Satu-satunya sumber: kredensial + batas ukuran.
$settingsFile = SETTINGS_FILE;
$settings     = app_settings();

/**
 * Tulis pengaturan ke storage_settings.json.
 * Return true bila berhasil disimpan.
 */
function save_settings(array $settings): bool
{
    $json = json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

    if ($json === false) {
        return false;
    }

    return file_put_contents(SETTINGS_FILE, $json, LOCK_EX) !== false;
}

// ============================================================
// PROSES POST
// ============================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $action = (string) ($_POST['action'] ?? '');

    // --------------------------------------------------------
    // GANTI KREDENSIAL LOGIN
    // --------------------------------------------------------
    if ($action === 'credentials') {
        $newUsername = trim((string) ($_POST['username'] ?? ''));
        $newPassword = (string) ($_POST['new_password'] ?? '');
        $confirmPass = (string) ($_POST['confirm_password'] ?? '');

        $errors = [];

        if ($newUsername === '') {
            $errors[] = 'Username wajib diisi.';
        } elseif (mb_strlen($newUsername) > 50) {
            $errors[] = 'Username maksimal 50 karakter.';
        }

        if ($newPassword === '') {
            $errors[] = 'Password baru wajib diisi.';
        } elseif (mb_strlen($newPassword) < 6) {
            $errors[] = 'Password baru minimal 6 karakter.';
        }

        if ($newPassword !== $confirmPass) {
            $errors[] = 'Konfirmasi password tidak cocok.';
        }

        if ($errors === []) {
            $settings['username']      = $newUsername;
            $settings['password_hash'] = password_hash($newPassword, PASSWORD_DEFAULT);

            if (save_settings($settings)) {
                // Cegah session fixation setelah ganti kredensial
                session_regenerate_id(true);
                $_SESSION['auth_user'] = $newUsername;

                flash('success', 'Kredensial login berhasil diperbarui.');
            } else {
                flash('error', 'Gagal menyimpan pengaturan. Periksa izin tulis folder.');
            }
        } else {
            foreach ($errors as $error) {
                $_SESSION['settings_errors'][] = $error;
            }

            flash('error', 'Kredensial tidak dapat disimpan.');
        }

        redirect('settings.php');
    }

    // --------------------------------------------------------
    // UBAH BATAS UKURAN FILE
    // --------------------------------------------------------
    if ($action === 'max_size') {
        $maxMb = (int) ($_POST['max_size_mb'] ?? 10);

        if ($maxMb < 1 || $maxMb > 100) {
            flash('error', 'Batas ukuran harus antara 1 sampai 100 MB.');
        } elseif (save_settings(['max_size_mb' => $maxMb] + $settings)) {
            flash('success', 'Batas ukuran file diubah menjadi ' . $maxMb . ' MB.');
        } else {
            flash('error', 'Gagal menyimpan pengaturan. Periksa izin tulis folder.');
        }

        redirect('settings.php');
    }
}

$errors = $_SESSION['settings_errors'] ?? [];
unset($_SESSION['settings_errors']);

$pageTitle  = 'Pengaturan';
$activeMenu = 'settings';
require __DIR__ . '/includes/header.php';
?>

<div class="page-head">
    <div>
        <h2>Pengaturan</h2>
        <p>Konfigurasi kredensial login dan batas ukuran file.</p>
    </div>
</div>

<div class="detail-layout">
    <div class="card">
        <div class="card-header">
            <h3>Umum</h3>
        </div>

        <div class="card-body">
            <dl class="meta-list">
                <div class="meta-item">
                    <dt>Versi PHP</dt>
                    <dd><?= e(PHP_VERSION) ?></dd>
                </div>
                <div class="meta-item">
                    <dt>Database</dt>
                    <dd><?= e(DB_NAME) ?> (MySQL)</dd>
                </div>
                <div class="meta-item">
                    <dt>Batas ukuran aktif</dt>
                    <dd><?= e(format_bytes(active_max_file_size())) ?></dd>
                </div>
                <div class="meta-item">
                    <dt>Upload timeout</dt>
                    <dd><?= e((string) ini_get('max_execution_time')) ?> detik</dd>
                </div>
                <div class="meta-item">
                    <dt>Upload max filesize</dt>
                    <dd><?= e((string) ini_get('upload_max_filesize')) ?></dd>
                </div>
                <div class="meta-item">
                    <dt>Upload max post size</dt>
                    <dd><?= e((string) ini_get('post_max_size')) ?></dd>
                </div>
                <div class="meta-item">
                    <dt>User login</dt>
                    <dd><?= e((string) $settings['username']) ?></dd>
                </div>
            </dl>

            <div class="alert alert-info" style="margin-top:20px; margin-bottom:0;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="12" y1="16" x2="12" y2="12"></line>
                    <line x1="12" y1="8" x2="12.01" y2="8"></line>
                </svg>
                <div>
                    Batas ukuran upload berasal dari dua sisi: nilai di bawah
                    (<code>upload_max_filesize</code> bawaan PHP) dan nilai yang kamu
                    pilih di sini. Aplikasi memakai yang lebih kecil, jadi menaikkan
                    angka di sini tidak berguna bila <code>upload_max_filesize</code>
                    PHP masih lebih kecil.
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3>Keamanan</h3>
        </div>

        <div class="card-body">
            <div class="section-title">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                     stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="11" width="18" height="11" rx="2"></rect>
                    <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                </svg>
                <h3>Fitur Keamanan Aktif</h3>
            </div>

            <dl class="meta-list">
                <div class="meta-item">
                    <dt>Database</dt>
                    <dd>PDO Prepared Statement</dd>
                </div>
                <div class="meta-item">
                    <dt>Form</dt>
                    <dd>Token CSRF</dd>
                </div>
                <div class="meta-item">
                    <dt>Output HTML</dt>
                    <dd>htmlspecialchars()</dd>
                </div>
                <div class="meta-item">
                    <dt>Password</dt>
                    <dd>Hash (password_hash)</dd>
                </div>
                <div class="meta-item">
                    <dt>Folder uploads</dt>
                    <dd>Ditutup (.htaccess)</dd>
                </div>
                <div class="meta-item">
                    <dt>Session ID</dt>
                    <dd>Diperbarui saat login</dd>
                </div>
            </dl>
        </div>
    </div>
</div>

<!-- ============ FORM KREDENSIAL ============ -->
<div class="card" style="margin-top:20px;">
    <div class="card-header">
        <h3>Kredensial Login</h3>
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
                    <?php foreach ($errors as $error): ?>
                        <p style="margin:0 0 4px;"><?= e($error) ?></p>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <form method="POST" action="settings.php" data-loading autocomplete="off">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="credentials">

            <div class="form-row">
                <div class="form-group">
                    <label for="set-username">Username</label>
                    <input class="form-control" type="text" name="username" id="set-username"
                           value="<?= e((string) $settings['username']) ?>"
                           required maxlength="50">
                </div>

                <div class="form-group">
                    <label for="set-password">Password Baru</label>
                    <input class="form-control" type="password" name="new_password" id="set-password"
                           required minlength="6" autocomplete="new-password"
                           placeholder="Minimal 6 karakter">
                </div>

                <div class="form-group">
                    <label for="set-password2">Ulangi Password</label>
                    <input class="form-control" type="password" name="confirm_password" id="set-password2"
                           required minlength="6" autocomplete="new-password"
                           placeholder="Ketik ulang password">
                </div>
            </div>

            <button type="submit" class="btn btn-primary">
                <span class="spinner" aria-hidden="true"></span>
                <span>Simpan Kredensial</span>
            </button>
        </form>
    </div>
</div>

<!-- ============ FORM BATAS UKURAN ============ -->
<div class="card" style="margin-top:20px;">
    <div class="card-header">
        <h3>Batas Ukuran File</h3>
    </div>

    <div class="card-body">
        <form method="POST" action="settings.php" data-loading>
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="max_size">

            <div class="form-group" style="max-width:260px;">
                <label for="max-size">Ukuran Maksimal (MB)</label>
                <input class="form-control" type="number" name="max_size_mb" id="max-size"
                       value="<?= e((string) $settings['max_size_mb']) ?>"
                       min="1" max="100" step="1" required>
            </div>

            <button type="submit" class="btn btn-primary">
                <span class="spinner" aria-hidden="true"></span>
                <span>Simpan Batas Ukuran</span>
            </button>
        </form>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
