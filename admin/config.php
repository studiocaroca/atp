<?php
// Shared bootstrap for every admin/ page: starts the session and
// centralizes the login credentials, paths and auth helpers so the
// password only lives in one place.

session_start();

// So the "Historial de cambios" timestamps read in local time instead of
// the server's own (often UTC) default.
date_default_timezone_set('America/Argentina/Buenos_Aires');

define('ADMIN_USERNAME', 'admin');
// bcrypt hash of "atpadmin" — generated once with password_hash(); the
// plaintext password is never stored in this file.
define('ADMIN_PASSWORD_HASH', '$2y$10$ZX//Ez/6HiS3ZVgXZGGByO1MC3zsMvz.ZhxeTrVHscRsKJDNp3E8u');

define('SITE_ROOT', dirname(__DIR__));
define('TRANSLATIONS_FILE', SITE_ROOT . '/translations.json');
define('IMAGES_DIR', SITE_ROOT . '/assets/imgs');

// Change history — see admin/log.php. Lives inside admin/ (not the site
// root, alongside translations.json/obras.json) since it's only useful
// to whoever's logged in here, never read by the public site; admin/
// .htaccess blocks direct requests for it, same as it does for .php
// files being read as source instead of executed.
define('CHANGE_LOG_FILE', __DIR__ . '/change-log.json');
define('CHANGE_LOG_MAX_ENTRIES', 500);

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

// Appends one entry to the change log (newest first) — call this right
// after a save actually succeeds (never for failed attempts or plain
// page views, so the log stays a record of real changes). $area is a
// short label for which admin page it happened on ("Textos", "Obras
// teatrales", "Imágenes", "Videos"); $summary is a human-readable line
// about what changed. Only one admin account exists today, so there's
// no "who" to record beyond that — just when and what.
function admin_log_change($area, $summary) {
    $entries = [];
    if (file_exists(CHANGE_LOG_FILE)) {
        $decoded = json_decode(file_get_contents(CHANGE_LOG_FILE), true);
        if (is_array($decoded)) {
            $entries = $decoded;
        }
    }
    array_unshift($entries, [
        'when' => date('c'),
        'area' => $area,
        'summary' => $summary,
    ]);
    if (count($entries) > CHANGE_LOG_MAX_ENTRIES) {
        $entries = array_slice($entries, 0, CHANGE_LOG_MAX_ENTRIES);
    }
    $json = json_encode($entries, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json !== false) {
        file_put_contents(CHANGE_LOG_FILE, $json, LOCK_EX);
    }
}
