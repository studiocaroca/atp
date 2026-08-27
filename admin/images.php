<?php
require __DIR__ . '/config.php';
admin_require_login();

// Lists the top-level files in assets/imgs and lets you overwrite one of
// them in place. Every <img> in index.html points at a fixed filename in
// that folder, so replacing the file's bytes (keeping the same name)
// updates the live site with zero HTML changes — no path/filename
// mapping to keep in sync anywhere else.
$allowedExt = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg', 'ico'];

function admin_list_images() {
    global $allowedExt;
    $files = [];
    foreach (scandir(IMAGES_DIR) as $entry) {
        $path = IMAGES_DIR . '/' . $entry;
        if (!is_file($path)) {
            continue;
        }
        $ext = strtolower(pathinfo($entry, PATHINFO_EXTENSION));
        if (in_array($ext, $GLOBALS['allowedExt'], true)) {
            $files[] = $entry;
        }
    }
    sort($files, SORT_FLAG_CASE | SORT_STRING);
    return $files;
}

$message = '';
$messageType = '';
$images = admin_list_images();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!admin_csrf_check()) {
        $message = 'La sesión expiró, volvé a cargar la página e intentá de nuevo.';
        $messageType = 'error';
    } else {
        $target = basename($_POST['target_file'] ?? '');

        if (!in_array($target, $images, true)) {
            $message = 'Elegí un archivo válido de la lista.';
            $messageType = 'error';
        } elseif (empty($_FILES['new_image']['tmp_name']) || $_FILES['new_image']['error'] !== UPLOAD_ERR_OK) {
            $message = 'Elegí un archivo para subir.';
            $messageType = 'error';
        } else {
            $tmpPath = $_FILES['new_image']['tmp_name'];
            $maxBytes = 12 * 1024 * 1024;

            if ($_FILES['new_image']['size'] > $maxBytes) {
                $message = 'El archivo pesa más de 12MB.';
                $messageType = 'error';
            } elseif (strtolower(pathinfo($target, PATHINFO_EXTENSION)) !== 'svg' && @getimagesize($tmpPath) === false) {
                $message = 'El archivo subido no parece ser una imagen válida.';
                $messageType = 'error';
            } else {
                $targetPath = IMAGES_DIR . '/' . $target;
                if (!is_writable($targetPath) && !is_writable(IMAGES_DIR)) {
                    $message = 'No se pudo reemplazar la imagen: el servidor no tiene permiso de escritura sobre assets/imgs.';
                    $messageType = 'error';
                } elseif (move_uploaded_file($tmpPath, $targetPath)) {
                    $message = "\"$target\" reemplazada. Ya se ve en la página.";
                    $messageType = 'success';
                } else {
                    $message = 'No se pudo guardar el archivo subido.';
                    $messageType = 'error';
                }
            }
        }
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
    <title>Reemplazar imágenes — Admin ATP</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="admin-topbar">
        <a href="index.php">Apto para Todo Público — Admin</a>
        <nav>
            <a href="index.php">Textos</a>
            <a href="images.php">Imágenes</a>
            <a href="../index.html" target="_blank">Ver sitio ↗</a>
            <a href="logout.php">Salir</a>
        </nav>
    </div>

    <div class="admin-wrap">
        <h1 class="admin-title">Reemplazar una imagen</h1>
        <p class="admin-subtitle">Elegí qué imagen del sitio querés reemplazar y subí el archivo nuevo — se guarda con el mismo nombre, así aparece en el mismo lugar donde se usa hoy. Subí una imagen del mismo tipo (foto por foto, logo por logo) para que se vea bien.</p>

        <?php if ($message): ?>
            <div class="admin-alert <?= htmlspecialchars($messageType) ?>"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <fieldset class="admin-section">
            <legend>Subir reemplazo</legend>
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                <div class="admin-field">
                    <label for="target_file">Imagen a reemplazar</label>
                    <select id="target_file" name="target_file" required>
                        <option value="">Elegí un archivo…</option>
                        <?php foreach ($images as $file): ?>
                            <option value="<?= htmlspecialchars($file) ?>"><?= htmlspecialchars($file) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="admin-current-preview">
                        <span>El nombre del archivo elegido no cambia — la imagen nueva ocupa su lugar.</span>
                    </div>
                </div>
                <div class="admin-field">
                    <label for="new_image">Archivo nuevo</label>
                    <input type="file" id="new_image" name="new_image" accept="image/*" required>
                </div>
                <button type="submit" class="admin-btn">Reemplazar</button>
            </form>
        </fieldset>
    </div>
</body>
</html>
