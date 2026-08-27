<?php
// Shared bootstrap for every admin/ page: starts the session and
// centralizes the login credentials, paths and auth helpers so the
// password only lives in one place.

session_start();

define('ADMIN_USERNAME', 'admin');
// bcrypt hash of "atpadmin" — generated once with password_hash(); the
// plaintext password is never stored in this file.
define('ADMIN_PASSWORD_HASH', '$2y$10$ZX//Ez/6HiS3ZVgXZGGByO1MC3zsMvz.ZhxeTrVHscRsKJDNp3E8u');

define('SITE_ROOT', dirname(__DIR__));
define('TRANSLATIONS_FILE', SITE_ROOT . '/translations.json');
define('IMAGES_DIR', SITE_ROOT . '/assets/imgs');

function admin_is_logged_in() {
    return !empty($_SESSION['atp_admin_logged_in']);
}

// Call at the top of every page that requires a session, before any
// HTML is written.
function admin_require_login() {
    if (!admin_is_logged_in()) {
        header('Location: login.php');
        exit;
    }
}

function admin_csrf_token() {
    if (empty($_SESSION['atp_admin_csrf'])) {
        $_SESSION['atp_admin_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['atp_admin_csrf'];
}

function admin_csrf_check() {
    $token = $_POST['csrf_token'] ?? '';
    return !empty($_SESSION['atp_admin_csrf']) && hash_equals($_SESSION['atp_admin_csrf'], $token);
}
