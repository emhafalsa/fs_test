<?php
/**
 * LOGOUT.PHP
 * -----------------------------------------------------------------
 * Menghapus data login dari session lalu kembali ke halaman login.
 *
 * Logout memakai POST (bukan link GET) supaya tidak bisa dipicu
 * diam-diam oleh halaman lain (celah CSRF).
 */

require_once __DIR__ . '/includes/bootstrap.php';

// Proses hanya jika request berupa POST (dari tombol Logout di navbar)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    // Kosongkan seluruh data session
    $_SESSION = [];

    // Hapus juga cookie session di browser
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();
}

redirect('index.php');
