<?php
require __DIR__ . '/config.php';

if (admin_is_logged_in()) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!admin_csrf_check()) {
        $error = 'La sesión expiró, volvé a intentar.';
    } else {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';

        if ($username === ADMIN_USERNAME && password_verify($password, ADMIN_PASSWORD_HASH)) {
            session_regenerate_id(true);
            $_SESSION['atp_admin_logged_in'] = true;
            header('Location: index.php');
            exit;
        }

        $error = 'Usuario o contraseña incorrectos.';
    }
}

$csrf = admin_csrf_token();
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Ingresar — Admin ATP</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="admin-login-wrap">
        <h1>Apto para Todo Público<br>Panel de edición</h1>

        <?php if ($error): ?>
            <div class="admin-alert error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="post" novalidate>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
            <div class="admin-field">
                <label for="username">Usuario</label>
                <input type="text" id="username" name="username" autocomplete="username" required>
            </div>
            <div class="admin-field">
                <label for="password">Contraseña</label>
                <input type="password" id="password" name="password" autocomplete="current-password" required>
            </div>
            <button type="submit" class="admin-btn" style="width:100%;">Ingresar</button>
        </form>
    </div>
</body>
</html>
