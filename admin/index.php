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
        'label' => 'Malena Sánchez Olmos',
        'fields' => [
            'name' => ['label' => 'Nombre', 'type' => 'text'],
            'paragraph1' => ['label' => 'Párrafo 1', 'type' => 'textarea'],
            'paragraph2' => ['label' => 'Párrafo 2', 'type' => 'textarea'],
        ],
    ],
    'about-sol' => [
        'label' => 'Sol Grunschlager',
        'fields' => [
            'name' => ['label' => 'Nombre', 'type' => 'text'],
            'paragraph1' => ['label' => 'Párrafo 1', 'type' => 'textarea'],
            'paragraph2' => ['label' => 'Párrafo 2', 'type' => 'textarea'],
        ],
    ],
    'about-pau' => [
        'label' => 'Paula Ciruzzi',
        'fields' => [
            'name' => ['label' => 'Nombre', 'type' => 'text'],
            'paragraph1' => ['label' => 'Párrafo 1', 'type' => 'textarea'],
            'paragraph2' => ['label' => 'Párrafo 2', 'type' => 'textarea'],
        ],
    ],
    'about-tatiana' => [
        'label' => 'Tatiana Marconi',
        'fields' => [
            'name' => ['label' => 'Nombre', 'type' => 'text'],
            'paragraph1' => ['label' => 'Párrafo 1', 'type' => 'textarea'],
            'paragraph2' => ['label' => 'Párrafo 2', 'type' => 'textarea'],
        ],
    ],
    'about-florencea' => [
        'label' => 'Florencea Fernández',
        'fields' => [
            'name' => ['label' => 'Nombre', 'type' => 'text'],
            'paragraph1' => ['label' => 'Párrafo 1', 'type' => 'textarea'],
            'paragraph2' => ['label' => 'Párrafo 2', 'type' => 'textarea'],
        ],
    ],
    'our-services' => [
        'label' => 'Título de la sección',
        'fields' => [
            'title' => ['label' => 'Título de la sección', 'type' => 'text'],
        ],
    ],
    'servicios-formacion' => [
        'label' => 'Formación en introducción a la accesibilidad cultural',
        'fields' => [
            'title' => ['label' => 'Título', 'type' => 'text'],
            'paragraph1' => ['label' => 'Descripción', 'type' => 'textarea'],
        ],
    ],
    'servicios-asesorias' => [
        'label' => 'Asesorías - Accesibilizá tu proyecto',
        'fields' => [
            'title' => ['label' => 'Título', 'type' => 'text'],
            'paragraph1' => ['label' => 'Párrafo 1', 'type' => 'textarea'],
            'paragraph2' => ['label' => 'Párrafo 2', 'type' => 'textarea'],
            'credit' => ['label' => 'Crédito de la foto del flyer', 'type' => 'text'],
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

// Purely for how the form is grouped/collapsed on screen — the save
// logic above still walks $fieldMap section by section, unaffected by
// this. Each group becomes one collapsible <details>; a group listing
// more than one section (just "Quienes somos" today) gets its sections
// nested as their own inner <details> so the whole page isn't one
// giant scroll of every organizadora's bio at once.
$renderGroups = [
    ['label' => 'Menú de navegación', 'sections' => ['menu'], 'nested' => []],
    ['label' => 'Portada (Home)', 'sections' => ['header'], 'nested' => []],
    ['label' => 'Quienes somos', 'sections' => [], 'nested' => ['about', 'about-sol', 'about-pau', 'about-tatiana', 'about-florencea']],
    ['label' => 'Servicios', 'sections' => ['our-services'], 'nested' => ['servicios-formacion', 'servicios-asesorias']],
    ['label' => 'Contacto', 'sections' => ['contact'], 'nested' => []],
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
            <a href="obras.php">Obras teatrales</a>
            <a href="images.php">Imágenes</a>
            <a href="videos.php">Videos</a>
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

            <?php
            // Renders one section's fields (used both directly inside a
            // single-section group and nested inside a multi-section one).
            function admin_render_fields($section, $sectionDef, $translations) {
                foreach ($sectionDef['fields'] as $key => $fieldDef):
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
                <?php endforeach;
            }
            ?>

            <?php foreach ($renderGroups as $group): ?>
                <details class="admin-section">
                    <summary><?= htmlspecialchars($group['label']) ?></summary>
                    <div class="admin-section-body">
                        <?php foreach ($group['sections'] as $section): ?>
                            <?php admin_render_fields($section, $fieldMap[$section], $translations); ?>
                        <?php endforeach; ?>
                        <?php foreach ($group['nested'] as $section): ?>
                            <details class="admin-subsection">
                                <summary><?= htmlspecialchars($fieldMap[$section]['label']) ?></summary>
                                <div class="admin-section-body">
                                    <?php admin_render_fields($section, $fieldMap[$section], $translations); ?>
                                </div>
                            </details>
                        <?php endforeach; ?>
                    </div>
                </details>
            <?php endforeach; ?>

            <button type="submit" class="admin-btn">Guardar cambios</button>
        </form>
    </div>
</body>
</html>
