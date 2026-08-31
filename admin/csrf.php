<?php
require __DIR__ . '/config.php';
header('Content-Type: application/json');
echo json_encode(['csrf_token' => admin_csrf_token(), 'logged_in' => admin_is_logged_in()]);
