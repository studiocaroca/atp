<?php
require __DIR__ . '/config.php';
admin_require_login();

// Plays are data, not hand-authored HTML: index.html has only two empty
// containers (#projects-grid / #project-modals) that atp.js's
// renderObras() fills in from obras.json at load. This page is the only
// place that ever writes to that file, so adding/removing/editing a
// play here shows up on the site immediately with no HTML to touch.
define('OBRAS_FILE', SITE_ROOT . '/obras.json');
define('LOGOS_DIR', IMAGES_DIR . '/logos');
define('VIDEOS_DIR', IMAGES_DIR . '/videos');
$allowedExt = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
$maxBytes = 12 * 1024 * 1024;
$allowedVideoExt = ['mp4'];
$maxVideoBytes = 60 * 1024 * 1024;

// admin/.user.ini raises PHP's upload caps for this whole section, but
// some hosts ignore .user.ini — when a request blows past post_max_size,
// PHP silently empties $_POST/$_FILES with no error to catch, so this is
// the only way to tell that apart from a plain "you left everything blank".
function admin_post_too_large() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return false;
    if (!empty($_POST) || !empty($_FILES)) return false;
    $contentLength = isset($_SERVER['CONTENT_LENGTH']) ? (int) $_SERVER['CONTENT_LENGTH'] : 0;
    return $contentLength > 0;
}

function admin_load_obras() {
    $raw = file_exists(OBRAS_FILE) ? file_get_contents(OBRAS_FILE) : '{"plays":[]}';
    $data = json_decode($raw, true);
    if (!is_array($data) || !isset($data['plays']) || !is_array($data['plays'])) {
        $data = ['plays' => []];
    }
    return $data;
}

function admin_save_obras($data) {
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) return false;
    return file_put_contents(OBRAS_FILE, $json, LOCK_EX) !== false;
}

function admin_slugify($title) {
    $map = [
        'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
        'Á' => 'a', 'É' => 'e', 'Í' => 'i', 'Ó' => 'o', 'Ú' => 'u', 'Ü' => 'u', 'Ñ' => 'n',
    ];
    $slug = strtr($title, $map);
    $slug = strtolower($slug);
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    $slug = trim($slug, '-');
    return $slug !== '' ? $slug : 'obra';
}

function admin_unique_slug($base, $existingIds) {
    $slug = $base;
    $n = 2;
    while (in_array($slug, $existingIds, true)) {
        $slug = $base . '-' . $n;
        $n++;
    }
    return $slug;
}

// Saves one freshly-uploaded file as assets/imgs/obras-teatrales-{id}-{n}.{ext},
// picking the first number not already used by this play's own images.
// Returns the new filename, or null if the upload slot was empty/invalid.
function admin_save_play_image($fileError, $tmpName, $sizeBytes, $originalName, $playId, &$existingImages, &$error) {
    global $allowedExt, $maxBytes;

    if ($fileError === UPLOAD_ERR_NO_FILE) return null;
    if ($fileError !== UPLOAD_ERR_OK) {
        $error = 'Hubo un problema subiendo una de las fotos.';
        return null;
    }
    if ($sizeBytes > $maxBytes) {
        $error = 'Una de las fotos pesa más de 12MB.';
        return null;
    }
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt, true)) {
        $error = 'Una de las fotos no es un formato de imagen soportado (jpg, png, webp, gif).';
        return null;
    }
    if (@getimagesize($tmpName) === false) {
        $error = 'Uno de los archivos subidos no parece ser una imagen válida.';
        return null;
    }

    $n = 1;
    $prefix = 'obras-teatrales-' . $playId . '-';
    $taken = array_map(function ($img) { return $img; }, $existingImages);
    do {
        $candidate = $prefix . $n . '.' . $ext;
        $n++;
    } while (in_array($candidate, $taken, true) || file_exists(IMAGES_DIR . '/' . $candidate));

    if (!move_uploaded_file($tmpName, IMAGES_DIR . '/' . $candidate)) {
        $error = 'No se pudo guardar una de las fotos.';
        return null;
    }
    $existingImages[] = $candidate;
    return $candidate;
}

// Saves an uploaded video as assets/imgs/videos/obras-teatrales-{id}-web.mp4
// — a play has at most one, so unlike photos this always overwrites
// rather than numbering. Returns the "videos/..." path to store in
// play.video, or null if the upload slot was empty/invalid.
function admin_save_play_video($fileError, $tmpName, $sizeBytes, $originalName, $playId, &$error) {
    global $allowedVideoExt, $maxVideoBytes;

    if ($fileError === UPLOAD_ERR_NO_FILE) return null;
    if ($fileError !== UPLOAD_ERR_OK) {
        $error = 'Hubo un problema subiendo el video.';
        return null;
    }
    if ($sizeBytes > $maxVideoBytes) {
        $error = 'El video pesa más de 60MB.';
        return null;
    }
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedVideoExt, true)) {
        $error = 'El video tiene que ser un archivo .mp4.';
        return null;
    }

    if (!is_dir(VIDEOS_DIR)) @mkdir(VIDEOS_DIR, 0755, true);
    $filename = 'obras-teatrales-' . $playId . '-web.mp4';
    if (!move_uploaded_file($tmpName, VIDEOS_DIR . '/' . $filename)) {
        $error = 'No se pudo guardar el video.';
        return null;
    }
    return 'videos/' . $filename;
}

// Every logo already in assets/imgs/logos — the checkbox list of
// "existing auspiciantes" a play can be tagged with, so re-adding a
// sponsor that's already used elsewhere (Proteatro, FIBA, etc.) doesn't
// mean uploading their logo file over and over.
function admin_list_logos() {
    global $allowedExt;
    if (!is_dir(LOGOS_DIR)) return [];
    $files = [];
    foreach (scandir(LOGOS_DIR) as $entry) {
        if ($entry === '.' || $entry === '..') continue;
        $path = LOGOS_DIR . '/' . $entry;
        if (!is_file($path)) continue;
        $ext = strtolower(pathinfo($entry, PATHINFO_EXTENSION));
        if (in_array($ext, $allowedExt, true) || $ext === 'webp') $files[] = $entry;
    }
    sort($files, SORT_FLAG_CASE | SORT_STRING);
    return $files;
}

// "logo-banco-hipotecario.png" -> "Banco Hipotecario" — a reasonable
// default alt/label since sponsor logos aren't individually re-typed
// every time one gets reused on a new play.
function admin_logo_label($filename) {
    $name = pathinfo($filename, PATHINFO_FILENAME);
    $name = preg_replace('/^logo-/', '', $name);
    $name = str_replace('-', ' ', $name);
    return ucwords($name);
}

function admin_save_new_logo($fileError, $tmpName, $sizeBytes, $originalName, $label, &$error) {
    global $allowedExt, $maxBytes;

    if ($fileError === UPLOAD_ERR_NO_FILE) return null;
    if ($fileError !== UPLOAD_ERR_OK) {
        $error = 'Hubo un problema subiendo el logo del auspiciante.';
        return null;
    }
    if ($sizeBytes > $maxBytes) {
        $error = 'El logo del auspiciante pesa más de 12MB.';
        return null;
    }
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt, true) && $ext !== 'webp') {
        $error = 'El logo del auspiciante no es un formato de imagen soportado.';
        return null;
    }
    if (@getimagesize($tmpName) === false) {
        $error = 'El logo del auspiciante subido no parece ser una imagen válida.';
        return null;
    }

    $slug = admin_slugify($label !== '' ? $label : pathinfo($originalName, PATHINFO_FILENAME));
    if (!is_dir(LOGOS_DIR)) @mkdir(LOGOS_DIR, 0755, true);
    $n = 0;
    do {
        $filename = 'logo-' . $slug . ($n > 0 ? '-' . $n : '') . '.' . $ext;
        $n++;
    } while (file_exists(LOGOS_DIR . '/' . $filename));

    if (!move_uploaded_file($tmpName, LOGOS_DIR . '/' . $filename)) {
        $error = 'No se pudo guardar el logo del auspiciante.';
        return null;
    }
    return $filename;
}

// Rebuilds a play's sponsors array from the submitted checkbox list
// (existing logos/*.ext filenames) plus one optional brand-new upload —
// always the full replacement set, not a diff, since checkboxes already
// represent "everything that should be checked" as a whole.
function admin_build_sponsors($checkedLogos, $newLogoFilename) {
    $sponsors = [];
    foreach ((array) $checkedLogos as $filename) {
        $filename = basename($filename);
        if (!is_file(LOGOS_DIR . '/' . $filename)) continue;
        $sponsors[] = ['src' => 'logos/' . $filename, 'alt' => admin_logo_label($filename)];
    }
    if ($newLogoFilename) {
        $sponsors[] = ['src' => 'logos/' . $newLogoFilename, 'alt' => admin_logo_label($newLogoFilename)];
    }
    return $sponsors;
}

$message = '';
$messageType = '';
$data = admin_load_obras();

$translationsRaw = file_exists(TRANSLATIONS_FILE) ? file_get_contents(TRANSLATIONS_FILE) : '{}';
$translations = json_decode($translationsRaw, true);
if (!is_array($translations)) $translations = [];
if (!isset($translations['es']) || !is_array($translations['es'])) $translations['es'] = [];
$sectionTitle = $translations['es']['portfolio']['title'] ?? 'Obras teatrales';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (admin_post_too_large()) {
        $message = 'Lo que subiste pesa demasiado para el servidor. Probá con un archivo más liviano.';
        $messageType = 'error';
    } elseif (!admin_csrf_check()) {
        $message = 'La sesión expiró, volvé a cargar la página e intentá de nuevo.';
        $messageType = 'error';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'save_title') {
            $translations['es']['portfolio']['title'] = trim(preg_replace('/\s+/', ' ', $_POST['title'] ?? ''));
            $json = json_encode($translations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($json !== false && file_put_contents(TRANSLATIONS_FILE, $json, LOCK_EX) !== false) {
                $sectionTitle = $translations['es']['portfolio']['title'];
                $message = 'Título guardado.';
                $messageType = 'success';
            } else {
                $message = 'No se pudo guardar el título.';
                $messageType = 'error';
            }
        } elseif ($action === 'save_play') {
            $playId = $_POST['play_id'] ?? '';
            $index = null;
            foreach ($data['plays'] as $i => $p) {
                if ($p['id'] === $playId) { $index = $i; break; }
            }
            if ($index === null) {
                $message = 'No se encontró esa obra.';
                $messageType = 'error';
            } else {
                $play = $data['plays'][$index];

                $title = trim(preg_replace('/\s+/', ' ', $_POST['title'] ?? ''));
                if ($title !== '') $play['title'] = $title;

                $paragraph1 = trim(str_replace(["\r\n", "\r"], "\n", $_POST['paragraph1'] ?? ''));
                $paragraph2 = trim(str_replace(["\r\n", "\r"], "\n", $_POST['paragraph2'] ?? ''));
                $paragraphs = [];
                if ($paragraph1 !== '') $paragraphs[] = $paragraph1;
                if ($paragraph2 !== '') $paragraphs[] = $paragraph2;
                if ($paragraphs) $play['paragraphs'] = $paragraphs;

                $play['ficha'] = trim(str_replace(["\r\n", "\r"], "\n", $_POST['ficha'] ?? ''));

                // Deletions first, so a photo can't be removed and
                // re-counted as "still there" against the min-1 check.
                $toDelete = $_POST['delete_images'] ?? [];
                if (is_array($toDelete) && $toDelete) {
                    $play['images'] = array_values(array_diff($play['images'], $toDelete));
                }

                $imgError = '';
                if (!empty($_FILES['new_images'])) {
                    $files = $_FILES['new_images'];
                    $count = is_array($files['tmp_name']) ? count($files['tmp_name']) : 0;
                    for ($i = 0; $i < $count; $i++) {
                        admin_save_play_image(
                            $files['error'][$i], $files['tmp_name'][$i], $files['size'][$i], $files['name'][$i],
                            $play['id'], $play['images'], $imgError
                        );
                        if ($imgError) break;
                    }
                }

                // Video: a new upload always wins (same filename every
                // time, so it just overwrites); otherwise "Borrar video"
                // clears it. Untouched if neither happened.
                $videoError = '';
                $newVideoPath = null;
                if (!empty($_FILES['new_video']) && $_FILES['new_video']['error'] !== UPLOAD_ERR_NO_FILE) {
                    $newVideoPath = admin_save_play_video(
                        $_FILES['new_video']['error'], $_FILES['new_video']['tmp_name'], $_FILES['new_video']['size'], $_FILES['new_video']['name'],
                        $play['id'], $videoError
                    );
                }
                if ($videoError === '') {
                    if ($newVideoPath) {
                        $play['video'] = $newVideoPath;
                    } elseif (!empty($_POST['delete_video']) && !empty($play['video'])) {
                        $oldPath = IMAGES_DIR . '/' . $play['video'];
                        if (is_file($oldPath)) @unlink($oldPath);
                        $play['video'] = '';
                    }
                }

                // Sponsors: the checkbox list is the full replacement set
                // (not a diff), plus one optional brand-new logo upload.
                $logoError = '';
                $newLogoFilename = null;
                if (!empty($_FILES['new_sponsor_logo']) && $_FILES['new_sponsor_logo']['error'] !== UPLOAD_ERR_NO_FILE) {
                    $newLogoFilename = admin_save_new_logo(
                        $_FILES['new_sponsor_logo']['error'], $_FILES['new_sponsor_logo']['tmp_name'], $_FILES['new_sponsor_logo']['size'], $_FILES['new_sponsor_logo']['name'],
                        trim($_POST['new_sponsor_label'] ?? ''), $logoError
                    );
                }
                if ($logoError === '') {
                    $play['sponsors'] = admin_build_sponsors($_POST['sponsors'] ?? [], $newLogoFilename);
                }

                $firstError = $imgError ?: ($videoError ?: $logoError);
                if ($firstError) {
                    $message = $firstError;
                    $messageType = 'error';
                } elseif (empty($play['images'])) {
                    $message = 'Una obra necesita al menos una foto — subí una nueva antes de borrar la última.';
                    $messageType = 'error';
                } else {
                    // Actually delete the files for images removed above,
                    // now that we know the save as a whole will go through.
                    if (is_array($toDelete)) {
                        foreach ($toDelete as $img) {
                            $path = IMAGES_DIR . '/' . basename($img);
                            if (is_file($path)) @unlink($path);
                        }
                    }
                    $data['plays'][$index] = $play;
                    if (admin_save_obras($data)) {
                        $message = '"' . $play['title'] . '" actualizada. Ya se ve en la página.';
                        $messageType = 'success';
                    } else {
                        $message = 'No se pudieron guardar los cambios.';
                        $messageType = 'error';
                    }
                }
            }
        } elseif ($action === 'delete_play') {
            $playId = $_POST['play_id'] ?? '';
            $index = null;
            foreach ($data['plays'] as $i => $p) {
                if ($p['id'] === $playId) { $index = $i; break; }
            }
            if ($index === null) {
                $message = 'No se encontró esa obra.';
                $messageType = 'error';
            } else {
                $removed = $data['plays'][$index];
                array_splice($data['plays'], $index, 1);
                if (admin_save_obras($data)) {
                    // Photos and video are this play's own files (unique
                    // per play, never shared) — sponsor logos are left
                    // alone since other plays may still be using them.
                    foreach ($removed['images'] as $img) {
                        $path = IMAGES_DIR . '/' . basename($img);
                        if (is_file($path)) @unlink($path);
                    }
                    if (!empty($removed['video'])) {
                        $path = IMAGES_DIR . '/' . $removed['video'];
                        if (is_file($path)) @unlink($path);
                    }
                    $message = '"' . $removed['title'] . '" borrada.';
                    $messageType = 'success';
                } else {
                    $message = 'No se pudo borrar la obra.';
                    $messageType = 'error';
                }
            }
        } elseif ($action === 'add_play') {
            $title = trim(preg_replace('/\s+/', ' ', $_POST['title'] ?? ''));
            $description = trim(str_replace(["\r\n", "\r"], "\n", $_POST['description'] ?? ''));
            $ficha = trim(str_replace(["\r\n", "\r"], "\n", $_POST['ficha'] ?? ''));

            if ($title === '' || $description === '') {
                $message = 'Completá al menos el título y la descripción.';
                $messageType = 'error';
            } else {
                $existingIds = array_map(function ($p) { return $p['id']; }, $data['plays']);
                $id = admin_unique_slug(admin_slugify($title), $existingIds);

                $images = [];
                $imgError = '';
                if (!empty($_FILES['new_images'])) {
                    $files = $_FILES['new_images'];
                    $count = is_array($files['tmp_name']) ? count($files['tmp_name']) : 0;
                    for ($i = 0; $i < $count; $i++) {
                        admin_save_play_image(
                            $files['error'][$i], $files['tmp_name'][$i], $files['size'][$i], $files['name'][$i],
                            $id, $images, $imgError
                        );
                        if ($imgError) break;
                    }
                }

                $video = '';
                $videoError = '';
                if (!empty($_FILES['new_video']) && $_FILES['new_video']['error'] !== UPLOAD_ERR_NO_FILE) {
                    $videoPath = admin_save_play_video(
                        $_FILES['new_video']['error'], $_FILES['new_video']['tmp_name'], $_FILES['new_video']['size'], $_FILES['new_video']['name'],
                        $id, $videoError
                    );
                    if ($videoPath) $video = $videoPath;
                }

                $sponsors = [];
                $logoError = '';
                if ($imgError === '' && $videoError === '') {
                    $newLogoFilename = null;
                    if (!empty($_FILES['new_sponsor_logo']) && $_FILES['new_sponsor_logo']['error'] !== UPLOAD_ERR_NO_FILE) {
                        $newLogoFilename = admin_save_new_logo(
                            $_FILES['new_sponsor_logo']['error'], $_FILES['new_sponsor_logo']['tmp_name'], $_FILES['new_sponsor_logo']['size'], $_FILES['new_sponsor_logo']['name'],
                            trim($_POST['new_sponsor_label'] ?? ''), $logoError
                        );
                    }
                    $sponsors = admin_build_sponsors($_POST['sponsors'] ?? [], $newLogoFilename);
                }

                $firstError = $imgError ?: ($videoError ?: $logoError);
                if ($firstError) {
                    // Roll back anything that did save before the failure.
                    foreach ($images as $img) {
                        $path = IMAGES_DIR . '/' . basename($img);
                        if (is_file($path)) @unlink($path);
                    }
                    if ($video) {
                        $path = IMAGES_DIR . '/' . $video;
                        if (is_file($path)) @unlink($path);
                    }
                    $message = $firstError;
                    $messageType = 'error';
                } elseif (empty($images)) {
                    $message = 'Subí al menos una foto para la obra nueva.';
                    $messageType = 'error';
                } else {
                    $data['plays'][] = [
                        'id' => $id,
                        'title' => $title,
                        'paragraphs' => [$description],
                        'video' => $video,
                        'images' => $images,
                        'ficha' => $ficha,
                        'sponsors' => $sponsors,
                        'notes' => '',
                    ];
                    if (admin_save_obras($data)) {
                        $message = '"' . $title . '" agregada. Ya se ve en la página.';
                        $messageType = 'success';
                    } else {
                        foreach ($images as $img) {
                            $path = IMAGES_DIR . '/' . basename($img);
                            if (is_file($path)) @unlink($path);
                        }
                        if ($video) {
                            $path = IMAGES_DIR . '/' . $video;
                            if (is_file($path)) @unlink($path);
                        }
                        $message = 'No se pudo guardar la obra nueva.';
                        $messageType = 'error';
                    }
                }
            }
        }
    }
    // Reload so the form below always reflects what's actually on disk,
    // including after a failed partial operation.
    $data = admin_load_obras();
}

$csrf = admin_csrf_token();
$cacheBust = time();
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Obras teatrales — Admin ATP</title>
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
        <h1 class="admin-title">Obras teatrales</h1>
        <p class="admin-subtitle">Agregá, editá o borrá obras — cada una aparece como una tarjeta en la sección y abre su propia ficha. La grilla se acomoda sola sin importar cuántas obras haya.</p>

        <?php if ($message): ?>
            <div class="admin-alert <?= htmlspecialchars($messageType) ?>"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <fieldset class="admin-section">
            <legend>Título de la sección</legend>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                <input type="hidden" name="action" value="save_title">
                <div class="admin-field">
                    <label for="section_title">Título</label>
                    <input type="text" id="section_title" name="title" value="<?= htmlspecialchars($sectionTitle) ?>">
                </div>
                <button type="submit" class="admin-btn admin-btn-small">Guardar</button>
            </form>
        </fieldset>

        <?php foreach ($data['plays'] as $play): ?>
            <details class="admin-section">
                <summary><?= htmlspecialchars($play['title']) ?></summary>
                <div class="admin-section-body">
                    <form method="post" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                        <input type="hidden" name="action" value="save_play">
                        <input type="hidden" name="play_id" value="<?= htmlspecialchars($play['id']) ?>">

                        <div class="admin-field">
                            <label for="title_<?= htmlspecialchars($play['id']) ?>">Título</label>
                            <input type="text" id="title_<?= htmlspecialchars($play['id']) ?>" name="title" value="<?= htmlspecialchars($play['title']) ?>">
                        </div>
                        <div class="admin-field">
                            <label for="p1_<?= htmlspecialchars($play['id']) ?>">Descripción</label>
                            <textarea id="p1_<?= htmlspecialchars($play['id']) ?>" name="paragraph1"><?= htmlspecialchars($play['paragraphs'][0] ?? '') ?></textarea>
                        </div>
                        <div class="admin-field">
                            <label for="p2_<?= htmlspecialchars($play['id']) ?>">Párrafo 2 (opcional)</label>
                            <textarea id="p2_<?= htmlspecialchars($play['id']) ?>" name="paragraph2"><?= htmlspecialchars($play['paragraphs'][1] ?? '') ?></textarea>
                        </div>
                        <div class="admin-field">
                            <label for="ficha_<?= htmlspecialchars($play['id']) ?>">Ficha técnico-artística (opcional, una por línea, formato "Rol: Nombre")</label>
                            <textarea id="ficha_<?= htmlspecialchars($play['id']) ?>" name="ficha"><?= htmlspecialchars($play['ficha'] ?? '') ?></textarea>
                        </div>

                        <div class="admin-field">
                            <label>Fotos</label>
                            <div class="admin-image-grid">
                                <?php foreach ($play['images'] as $img): ?>
                                    <div class="admin-image-card">
                                        <div class="admin-image-card-thumb">
                                            <img src="../assets/imgs/<?= htmlspecialchars($img) ?>?v=<?= $cacheBust ?>" alt="" loading="lazy">
                                        </div>
                                        <label class="admin-image-card-delete">
                                            <input type="checkbox" name="delete_images[]" value="<?= htmlspecialchars($img) ?>"> Borrar
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="admin-field">
                            <label for="new_images_<?= htmlspecialchars($play['id']) ?>">Agregar fotos nuevas</label>
                            <input type="file" id="new_images_<?= htmlspecialchars($play['id']) ?>" name="new_images[]" accept="image/*" multiple>
                        </div>

                        <div class="admin-field">
                            <label>Video (opcional — es el que se ve grande al abrir la ficha)</label>
                            <?php if (!empty($play['video'])): ?>
                                <video class="admin-video-preview" src="../assets/imgs/<?= htmlspecialchars($play['video']) ?>?v=<?= $cacheBust ?>" controls preload="metadata"></video>
                                <label class="admin-image-card-delete">
                                    <input type="checkbox" name="delete_video" value="1"> Borrar video
                                </label>
                            <?php endif; ?>
                            <input type="file" name="new_video" accept="video/mp4">
                            <p class="admin-hint">Subir uno nuevo reemplaza el actual. .mp4, hasta 60MB.</p>
                        </div>

                        <div class="admin-field">
                            <label>Auspiciantes (opcional — se muestran al final de la ficha, con "Realizada con el apoyo de:")</label>
                            <div class="admin-logo-grid">
                                <?php foreach (admin_list_logos() as $logo): ?>
                                    <?php $isChecked = in_array('logos/' . $logo, array_column($play['sponsors'] ?? [], 'src'), true); ?>
                                    <label class="admin-logo-check">
                                        <img src="../assets/imgs/logos/<?= htmlspecialchars($logo) ?>" alt="">
                                        <input type="checkbox" name="sponsors[]" value="<?= htmlspecialchars($logo) ?>" <?= $isChecked ? 'checked' : '' ?>>
                                        <span><?= htmlspecialchars(admin_logo_label($logo)) ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            <p class="admin-hint">¿Auspiciante nuevo, que todavía no está en la lista? Subí su logo:</p>
                            <input type="file" name="new_sponsor_logo" accept="image/*">
                            <input type="text" name="new_sponsor_label" placeholder="Nombre del auspiciante (para el logo nuevo)">
                        </div>

                        <button type="submit" class="admin-btn admin-btn-small">Guardar cambios</button>
                    </form>

                    <form method="post" class="admin-delete-form" onsubmit="return confirm('¿Borrar &quot;<?= htmlspecialchars(addslashes($play['title'])) ?>&quot;? Se borra la obra y todas sus fotos. No se puede deshacer.');">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                        <input type="hidden" name="action" value="delete_play">
                        <input type="hidden" name="play_id" value="<?= htmlspecialchars($play['id']) ?>">
                        <button type="submit" class="admin-btn admin-btn-small admin-btn-danger">Borrar esta obra</button>
                    </form>
                </div>
            </details>
        <?php endforeach; ?>

        <fieldset class="admin-section admin-section-add">
            <legend>Agregar obra nueva</legend>
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                <input type="hidden" name="action" value="add_play">
                <div class="admin-field">
                    <label for="new_title">Título</label>
                    <input type="text" id="new_title" name="title" required>
                </div>
                <div class="admin-field">
                    <label for="new_description">Descripción</label>
                    <textarea id="new_description" name="description" required></textarea>
                </div>
                <div class="admin-field">
                    <label for="new_ficha">Ficha técnico-artística (opcional, una por línea, formato "Rol: Nombre")</label>
                    <textarea id="new_ficha" name="ficha"></textarea>
                </div>
                <div class="admin-field">
                    <label for="new_images_add">Fotos (al menos una)</label>
                    <input type="file" id="new_images_add" name="new_images[]" accept="image/*" multiple required>
                </div>
                <div class="admin-field">
                    <label>Video (opcional — es el que se ve grande al abrir la ficha)</label>
                    <input type="file" name="new_video" accept="video/mp4">
                    <p class="admin-hint">.mp4, hasta 60MB.</p>
                </div>
                <div class="admin-field">
                    <label>Auspiciantes (opcional)</label>
                    <div class="admin-logo-grid">
                        <?php foreach (admin_list_logos() as $logo): ?>
                            <label class="admin-logo-check">
                                <img src="../assets/imgs/logos/<?= htmlspecialchars($logo) ?>" alt="">
                                <input type="checkbox" name="sponsors[]" value="<?= htmlspecialchars($logo) ?>">
                                <span><?= htmlspecialchars(admin_logo_label($logo)) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <p class="admin-hint">¿Auspiciante nuevo, que todavía no está en la lista? Subí su logo:</p>
                    <input type="file" name="new_sponsor_logo" accept="image/*">
                    <input type="text" name="new_sponsor_label" placeholder="Nombre del auspiciante (para el logo nuevo)">
                </div>
                <button type="submit" class="admin-btn">Agregar obra</button>
            </form>
        </fieldset>
    </div>
</body>
</html>
