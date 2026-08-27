<?php
require __DIR__ . '/config.php';
admin_require_login();

// Every field here maps 1:1 to a [data-section][data-translate] element
// in ../index.html that atp.js actually populates from translations.json
// at page load — see fetchTranslations()/updateTranslations() there.
// Only the "es" locale is edited here: it's the language the site shows
// by default, and en/pt are left untouched in the file.
$fieldMap = [
    'menu' => [
        'label' => 'Menú de navegación',
        'fields' => [
            'home' => ['label' => 'Home', 'type' => 'text'],
            'about' => ['label' => 'Quienes somos', 'type' => 'text'],
            'institutional' => ['label' => 'Institucional', 'type' => 'text'],
            'plays' => ['label' => 'Obras teatrales', 'type' => 'text'],
            'portfolio' => ['label' => 'Otros proyectos', 'type' => 'text'],
            'services' => ['label' => 'Servicios', 'type' => 'text'],
            'contact' => ['label' => 'Contacto', 'type' => 'text'],
        ],
    ],
    'header' => [
        'label' => 'Portada (Home)',
        'fields' => [
            'eyebrow' => ['label' => 'Frase animada ("diversidad de posibilidades")', 'type' => 'text'],
            'description' => ['label' => 'Texto de presentación de la ONG', 'type' => 'textarea'],
        ],
    ],
    'about' => [
        'label' => 'Quienes somos — mensaje de bienvenida',
        'fields' => [
            'name' => ['label' => 'Nombre', 'type' => 'text'],
            'paragraph1' => ['label' => 'Párrafo 1', 'type' => 'textarea'],
            'paragraph2' => ['label' => 'Párrafo 2', 'type' => 'textarea'],
        ],
    ],
    'portfolio' => [
        'label' => 'Obras teatrales',
        'fields' => [
            'title' => ['label' => 'Título de la sección', 'type' => 'text'],
        ],
    ],
    'our-services' => [
        'label' => 'Servicios',
        'fields' => [
            'title' => ['label' => 'Título de la sección', 'type' => 'text'],
        ],
    ],
    'contact' => [
        'label' => 'Contacto',
        'fields' => [
            'title' => ['label' => 'Título', 'type' => 'text'],
            'subtitle' => ['label' => 'Subtítulo', 'type' => 'text'],
            'name' => ['label' => 'Placeholder del campo "Nombre"', 'type' => 'text'],
            'message' => ['label' => 'Placeholder del campo "Mensaje"', 'type' => 'text'],
            'button' => ['label' => 'Texto del botón de envío', 'type' => 'text'],
        ],
    ],
];

$message = '';
$messageType = '';

$raw = file_exists(TRANSLATIONS_FILE) ? file_get_contents(TRANSLATIONS_FILE) : '{}';
$translations = json_decode($raw, true);
if (!is_array($translations)) {
    $translations = [];
}
if (!isset($translations['es']) || !is_array($translations['es'])) {
    $translations['es'] = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!admin_csrf_check()) {
        $message = 'La sesión expiró, volvé a cargar la página e intentá de nuevo.';
        $messageType = 'error';
    } else {
        $posted = $_POST['field'] ?? [];

        foreach ($fieldMap as $section => $sectionDef) {
            if (!isset($translations['es'][$section]) || !is_array($translations['es'][$section])) {
                $translations['es'][$section] = [];
            }
            foreach ($sectionDef['fields'] as $key => $fieldDef) {
                if (isset($posted[$section][$key])) {
                    // Textareas keep line breaks; plain text fields are
                    // collapsed to a single line since they render as one.
                    $value = trim(str_replace(["\r\n", "\r"], "\n", $posted[$section][$key]));
                    if ($fieldDef['type'] !== 'textarea') {
                        $value = preg_replace('/\s+/', ' ', $value);
                    }
                    $translations['es'][$section][$key] = $value;
                }
            }
        }

        $json = json_encode($translations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($json === false) {
            $message = 'No se pudieron guardar los cambios (error al generar el archivo).';
            $messageType = 'error';
        } elseif (!is_writable(dirname(TRANSLATIONS_FILE)) && !is_writable(TRANSLATIONS_FILE)) {
            $message = 'No se pudieron guardar los cambios: el servidor no tiene permiso de escritura sobre translations.json.';
            $messageType = 'error';
        } else {
            $written = file_put_contents(TRANSLATIONS_FILE, $json, LOCK_EX);
            if ($written === false) {
                $message = 'No se pudieron guardar los cambios (falló la escritura del archivo).';
                $messageType = 'error';
            } else {
                $message = 'Cambios guardados. Ya se ven en la página.';
                $messageType = 'success';
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
    <title>Editar textos — Admin ATP</title>
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
        <h1 class="admin-title">Editar textos</h1>
        <p class="admin-subtitle">Estos son los textos que ya se cargan dinámicamente en la página. Guardar acá los actualiza al instante para todas las visitas.</p>

        <?php if ($message): ?>
            <div class="admin-alert <?= htmlspecialchars($messageType) ?>"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">

            <?php foreach ($fieldMap as $section => $sectionDef): ?>
                <fieldset class="admin-section">
                    <legend><?= htmlspecialchars($sectionDef['label']) ?></legend>
                    <?php foreach ($sectionDef['fields'] as $key => $fieldDef): ?>
                        <?php
                        $current = $translations['es'][$section][$key] ?? '';
                        $inputId = 'field_' . $section . '_' . $key;
                        $inputName = "field[$section][$key]";
                        ?>
                        <div class="admin-field">
                            <label for="<?= htmlspecialchars($inputId) ?>"><?= htmlspecialchars($fieldDef['label']) ?></label>
                            <?php if ($fieldDef['type'] === 'textarea'): ?>
                                <textarea id="<?= htmlspecialchars($inputId) ?>" name="<?= htmlspecialchars($inputName) ?>"><?= htmlspecialchars($current) ?></textarea>
                            <?php else: ?>
                                <input type="text" id="<?= htmlspecialchars($inputId) ?>" name="<?= htmlspecialchars($inputName) ?>" value="<?= htmlspecialchars($current) ?>">
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </fieldset>
            <?php endforeach; ?>

            <button type="submit" class="admin-btn">Guardar cambios</button>
        </form>
    </div>
</body>
</html>
