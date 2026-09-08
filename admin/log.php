<?php
require __DIR__ . '/config.php';
admin_require_login();

// Read-only viewer for CHANGE_LOG_FILE (see admin_log_change() in
// config.php, which is what actually writes to it). Entries are already
// stored newest-first, so no sorting needed here.
$entries = [];
if (file_exists(CHANGE_LOG_FILE)) {
    $decoded = json_decode(file_get_contents(CHANGE_LOG_FILE), true);
    if (is_array($decoded)) {
        $entries = $decoded;
    }
}

// Small chip color per area, purely cosmetic, so a long list is easier
// to scan at a glance.
$areaClasses = [
    'Textos' => 'area-textos',
    'Obras teatrales' => 'area-obras',
    'Imágenes' => 'area-imagenes',
    'Videos' => 'area-videos',
];

// "cuando" ("when") is stored via PHP's date('c') (ISO 8601, includes
// an explicit offset) — reformatted here for reading, not editing.
function admin_format_when($iso) {
    $ts = strtotime($iso);
    if ($ts === false) return $iso;
    return date('d/m/Y H:i', $ts);
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Historial de cambios — Admin ATP</title>
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
            <a href="log.php">Historial</a>
            <a href="../index.html" target="_blank">Ver sitio ↗</a>
            <a href="logout.php">Salir</a>
        </nav>
    </div>

    <div class="admin-wrap admin-wrap-wide">
        <h1 class="admin-title">Historial de cambios</h1>
        <p class="admin-subtitle">Cada vez que se guarda un cambio desde este panel (Textos, Obras teatrales, Imágenes o Videos) queda registrado acá, con fecha y hora. Se guardan los últimos <?= CHANGE_LOG_MAX_ENTRIES ?> cambios.</p>

        <?php if (!$entries): ?>
            <p class="admin-hint">Todavía no hay cambios registrados. En cuanto guardes algo desde cualquiera de las otras secciones, va a aparecer acá.</p>
        <?php else: ?>
            <div class="admin-log-table-wrap">
                <table class="admin-log-table">
                    <thead>
                        <tr>
                            <th>Fecha y hora</th>
                            <th>Sección</th>
                            <th>Qué se hizo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($entries as $entry): ?>
                            <tr>
                                <td class="admin-log-when"><?= htmlspecialchars(admin_format_when($entry['when'] ?? '')) ?></td>
                                <td>
                                    <span class="admin-log-area <?= htmlspecialchars($areaClasses[$entry['area'] ?? ''] ?? '') ?>">
                                        <?= htmlspecialchars($entry['area'] ?? '') ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($entry['summary'] ?? '') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
