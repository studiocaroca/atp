<?php
require __DIR__ . '/config.php';
admin_require_login();

// Same idea as images.php — every video in assets/imgs/videos, replace
// one in place (same filename, so whatever references it picks up the
// new content automatically). Play videos aren't listed here even
// though they live in the same folder: they're already fully managed
// (upload/replace/remove, tied to a specific play) from
// admin/obras.php, so this page only covers the standalone ones used
// directly in index.html.
define('OBRAS_FILE_V', SITE_ROOT . '/obras.json');
$allowedVideoExt = ['mp4'];
$maxVideoBytes = 60 * 1024 * 1024;

function admin_video_owned_by_a_play() {
    $raw = file_exists(OBRAS_FILE_V) ? file_get_contents(OBRAS_FILE_V) : '{"plays":[]}';
    $data = json_decode($raw, true);
    $owned = [];
    if (is_array($data) && !empty($data['plays'])) {
        foreach ($data['plays'] as $play) {
            if (!empty($play['video'])) $owned[] = basename($play['video']);
        }
    }
    return $owned;
}

function admin_list_videos() {
    global $allowedVideoExt;
    $dir = IMAGES_DIR . '/videos';
    if (!is_dir($dir)) return [];
    $owned = admin_video_owned_by_a_play();
    $files = [];
    foreach (scandir($dir) as $entry) {
        if ($entry === '.' || $entry === '..') continue;
        if (!is_file($dir . '/' . $entry)) continue;
        $ext = strtolower(pathinfo($entry, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedVideoExt, true)) continue;
        if (in_array($entry, $owned, true)) continue;
        $files[] = $entry;
    }
    sort($files, SORT_FLAG_CASE | SORT_STRING);
    return $files;
}

$message = '';
$messageType = '';
$videos = admin_list_videos();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!admin_csrf_check()) {
        $message = 'La sesión expiró, volvé a cargar la página e intentá de nuevo.';
        $messageType = 'error';
    } else {
        $target = basename($_POST['target_file'] ?? '');

        if (!in_array($target, $videos, true)) {
            $message = 'Elegí un archivo válido de la lista.';
            $messageType = 'error';
        } elseif (empty($_FILES['new_video']['tmp_name']) || $_FILES['new_video']['error'] !== UPLOAD_ERR_OK) {
            $message = 'Elegí un archivo para subir.';
            $messageType = 'error';
        } elseif ($_FILES['new_video']['size'] > $maxVideoBytes) {
            $message = 'El archivo pesa más de 60MB.';
            $messageType = 'error';
        } elseif (strtolower(pathinfo($_FILES['new_video']['name'], PATHINFO_EXTENSION)) !== 'mp4') {
            $message = 'El archivo tiene que ser un .mp4.';
            $messageType = 'error';
        } else {
            $targetPath = IMAGES_DIR . '/videos/' . $target;
            if (!is_writable($targetPath) && !is_writable(dirname($targetPath))) {
                $message = 'No se pudo reemplazar el video: el servidor no tiene permiso de escritura sobre assets/imgs/videos.';
                $messageType = 'error';
            } elseif (move_uploaded_file($_FILES['new_video']['tmp_name'], $targetPath)) {
                $message = '"' . $target . '" reemplazado. Ya se ve en la página.';
                $messageType = 'success';
            } else {
                $message = 'No se pudo guardar el archivo subido.';
                $messageType = 'error';
            }
        }
    }
    $videos = admin_list_videos();
}

$csrf = admin_csrf_token();
$cacheBust = time();

// Same labels used in index.html's alt text / surrounding copy, so
// it's clear which video is which without having to play each one.
$videoLabels = [
    'after-movie-web.mp4' => 'Aftermovie — Festival Distendido (AcceDER I)',
    'distendida-gdz-web.mp4' => 'Función distendida en La Granja de Zenón (Servicios)',
];
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Reemplazar videos — Admin ATP</title>
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
        <h1 class="admin-title">Reemplazar un video</h1>
        <p class="admin-subtitle">El video nuevo se guarda con el mismo nombre, así aparece en el mismo lugar donde se usa hoy. Los videos de las obras teatrales se manejan aparte, desde <a href="obras.php">Obras teatrales</a>.</p>

        <?php if ($message): ?>
            <div class="admin-alert <?= htmlspecialchars($messageType) ?>"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <div class="admin-video-grid">
            <?php foreach ($videos as $video): ?>
                <div class="admin-video-card">
                    <video class="admin-video-preview" src="../assets/imgs/videos/<?= htmlspecialchars($video) ?>?v=<?= $cacheBust ?>" controls preload="metadata"></video>
                    <div class="admin-image-card-name"><?= htmlspecialchars($videoLabels[$video] ?? $video) ?></div>
                    <form method="post" enctype="multipart/form-data" class="admin-image-card-form">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                        <input type="hidden" name="target_file" value="<?= htmlspecialchars($video) ?>">
                        <input type="file" name="new_video" accept="video/mp4" required>
                        <button type="submit" class="admin-btn admin-btn-small">Reemplazar</button>
                    </form>
                </div>
            <?php endforeach; ?>
            <?php if (!$videos): ?>
                <p class="admin-hint">No hay videos sueltos para reemplazar acá — los de las obras se manejan desde Obras teatrales.</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
