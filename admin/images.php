<?php
require __DIR__ . '/config.php';
admin_require_login();

// Recursively lists every image in assets/imgs (including subfolders like
// quienes-somos/ and logos/) and lets you overwrite one of them in place.
// Every <img> in index.html points at a fixed path in that folder, so
// replacing the file's bytes (keeping the same name) updates the live
// site with zero HTML changes — no path/filename mapping to keep in
// sync anywhere else.
$allowedExt = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg', 'ico'];

// Not real editable content: flags/ backs the hidden (unused) language
// selector, formas/ is the decorative shape set used programmatically by
// the animated navbar logo, logo-caroca.svg (plus logo-caroca-black.svg,
// the mobile black-wordmark variant swapped in via <picture> — same mark,
// just recolored) is the web studio's own credit mark in the footer, and
// the unused ATP logo color variants are leftover files nothing on the
// site links to — none of these are meant to be swapped out from here.
// logo-blanco-azul-home.svg stays: it's the one actually shown in the
// header/home.
$excludedFolders = ['flags', 'formas'];
$excludedFiles = [
    'logo-caroca.svg',
    'logo-caroca-black.svg',
    'logo-azul-rojo.svg',
    'logo-blanco-amarillo.svg',
    'logo-blanco-azul.svg',
    'logo-blanco-azul2.svg',
    'logo-blanco-celeste.svg',
    'portada-contactanos.jpg',
    'portada-obras.jpg',
];

function admin_list_images() {
    global $allowedExt, $excludedFolders, $excludedFiles;
    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(IMAGES_DIR, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $fileInfo) {
        if (!$fileInfo->isFile()) {
            continue;
        }
        $ext = strtolower($fileInfo->getExtension());
        if (!in_array($ext, $allowedExt, true)) {
            continue;
        }
        $relative = substr($fileInfo->getPathname(), strlen(IMAGES_DIR) + 1);
        $relative = str_replace('\\', '/', $relative);

        $slash = strpos($relative, '/');
        $topFolder = $slash === false ? '' : substr($relative, 0, $slash);
        if (in_array($topFolder, $excludedFolders, true)) {
            continue;
        }
        if (in_array($relative, $excludedFiles, true)) {
            continue;
        }

        $files[] = $relative;
    }
    sort($files, SORT_FLAG_CASE | SORT_STRING);
    return $files;
}

// Groups a flat list of relative paths by their top folder ('' for files
// directly in assets/imgs) so the page can show one collapsible section
// per folder instead of one long flat list.
function admin_group_images($files) {
    $groups = [];
    foreach ($files as $file) {
        $slash = strrpos($file, '/');
        $folder = $slash === false ? '' : substr($file, 0, $slash);
        $groups[$folder][] = $file;
    }
    ksort($groups, SORT_FLAG_CASE | SORT_STRING);
    return $groups;
}

$folderLabels = [
    '' => 'Fotos y flyers',
    'quienes-somos' => 'Fotos de Quienes somos',
    'logos' => 'Logos de auspiciantes e instituciones',
];

$message = '';
$messageType = '';
$images = admin_list_images();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!admin_csrf_check()) {
        $message = 'La sesión expiró, volvé a cargar la página e intentá de nuevo.';
        $messageType = 'error';
    } else {
        // No basename() here on purpose — target paths can include a
        // subfolder (e.g. "quienes-somos/malena.jpg"). Safety comes from
        // the strict whitelist check below, not from path-stripping.
        $target = $_POST['target_file'] ?? '';

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
                if (!is_writable($targetPath) && !is_writable(dirname($targetPath))) {
                    $message = 'No se pudo reemplazar la imagen: el servidor no tiene permiso de escritura sobre assets/imgs.';
                    $messageType = 'error';
                } elseif (move_uploaded_file($tmpPath, $targetPath)) {
                    $message = '"' . basename($target) . '" reemplazada. Ya se ve en la página.';
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
$groups = admin_group_images($images);
// Cache-bust the thumbnails so a just-replaced image shows immediately
// instead of the browser's cached copy of the old one.
$cacheBust = time();
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
            <a href="obras.php">Obras teatrales</a>
            <a href="images.php">Imágenes</a>
            <a href="videos.php">Videos</a>
            <a href="../index.html" target="_blank">Ver sitio ↗</a>
            <a href="logout.php">Salir</a>
        </nav>
    </div>

    <div class="admin-wrap admin-wrap-wide">
        <h1 class="admin-title">Reemplazar una imagen</h1>
        <p class="admin-subtitle">Elegí la imagen que querés cambiar por su foto — el archivo nuevo se guarda con el mismo nombre, así aparece en el mismo lugar donde se usa hoy. Subí un archivo del mismo tipo (foto por foto, logo por logo) para que se vea bien.</p>

        <?php if ($message): ?>
            <div class="admin-alert <?= htmlspecialchars($messageType) ?>"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <?php foreach ($groups as $folder => $files): ?>
            <details class="admin-section" <?= $folder === '' ? 'open' : '' ?>>
                <summary><?= htmlspecialchars($folderLabels[$folder] ?? $folder) ?></summary>
                <div class="admin-section-body">
                    <div class="admin-image-grid">
                        <?php foreach ($files as $file): ?>
                            <div class="admin-image-card">
                                <div class="admin-image-card-thumb">
                                    <img src="../assets/imgs/<?= htmlspecialchars($file) ?>?v=<?= $cacheBust ?>" alt="<?= htmlspecialchars($file) ?>" loading="lazy">
                                </div>
                                <div class="admin-image-card-name"><?= htmlspecialchars(basename($file)) ?></div>
                                <form method="post" enctype="multipart/form-data" class="admin-image-card-form">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                                    <input type="hidden" name="target_file" value="<?= htmlspecialchars($file) ?>">
                                    <input type="file" name="new_image" accept="image/*" required>
                                    <button type="submit" class="admin-btn admin-btn-small">Reemplazar</button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </details>
        <?php endforeach; ?>
    </div>
</body>
</html>
