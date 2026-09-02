<?php
// Contact form endpoint — receives the POST from index.html's #contact
// form (see .contact-form's fetch handler in assets/js/atp.js) and
// emails it straight to the org's inbox via PHP's built-in mail().
// Replaces the earlier Formspree-based submission now that the site has
// its own PHP-capable hosting instead of GitHub Pages (which can't run
// this file at all — see admin/config.php for that same PHP dependency).

// This endpoint only ever returns JSON — a PHP warning/notice printed
// to the response body (e.g. mail() failing locally, or on a host with
// display_errors on) would otherwise get prepended as raw HTML in front
// of the JSON, breaking any consumer that parses the body.
ini_set('display_errors', '0');

header('Content-Type: application/json; charset=utf-8');

function fail($message, $code = 400) {
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $message]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    fail('Método no permitido.', 405);
}

// Honeypot — a real visitor never sees this field (hidden off-screen in
// CSS and skipped in tab order, see .hp-field in styles.scss), but a
// simple bot that blindly fills every input trips it. Pretend success
// without actually sending anything.
if (!empty($_POST['empresa'])) {
    echo json_encode(['success' => true]);
    exit;
}

$nombre = trim($_POST['nombre'] ?? '');
$email = trim($_POST['email'] ?? '');
$mensaje = trim($_POST['message'] ?? '');

if ($nombre === '' || $email === '' || $mensaje === '') {
    fail('Completá todos los campos.');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fail('El email no es válido.');
}

// Strips newlines from anything that ends up in a mail header — without
// this, a value like "x@x.com\nBcc: spamlist@..." could inject extra
// headers into the email (a classic mail-header-injection attack).
function clean_header_value($value) {
    return str_replace(["\r", "\n"], '', $value);
}

$to = 'aptoparatodopublicogc@gmail.com';
$subject = '=?UTF-8?B?' . base64_encode('Nuevo mensaje de contacto — Apto para Todo Público') . '?=';

$body = "Nombre: {$nombre}\n";
$body .= "Email: {$email}\n\n";
$body .= "Mensaje:\n{$mensaje}\n";

// The From address has to belong to the sending server's own domain, or
// most mail servers (including Gmail, the destination here) will flag
// or reject it as spoofed — it can't just be the destination Gmail
// address. Reply-To is the visitor's real address, so hitting "reply"
// in Gmail goes straight to them.
$fromDomain = clean_header_value($_SERVER['SERVER_NAME'] ?? 'aptoparatodopublico.com.ar');
$headers = [
    'From: Apto para Todo Público <no-reply@' . $fromDomain . '>',
    'Reply-To: ' . clean_header_value($nombre) . ' <' . clean_header_value($email) . '>',
    'Content-Type: text/plain; charset=UTF-8',
];

// @-suppressed for the same reason as ini_set() above — a connection
// failure here (e.g. no local mail server) must not leak a warning into
// the JSON response; $sent below already handles the failure case.
$sent = @mail($to, $subject, $body, implode("\r\n", $headers));

if (!$sent) {
    fail('No se pudo enviar el mensaje. Probá de nuevo en un rato.', 500);
}

echo json_encode(['success' => true]);
