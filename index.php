<?php

declare(strict_types=1);

require __DIR__ . '/autoload.php';

use Engine\Core\Engine;
use Engine\Rendering\RenderEvent;

$basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');

$engine = new Engine(__DIR__ . '/plugins');
$engine->boot();

$pluginConfig = require __DIR__ . '/config/plugins.php';

foreach ($pluginConfig['active'] ?? [] as $pluginName) {
    $engine->plugins()->activate((string) $pluginName);
}

$render = new RenderEvent('home');

$engine->events()->dispatch($render);

$title = htmlspecialchars(
    $render->getTitle() ?? 'Plugin Engine',
    ENT_QUOTES,
    'UTF-8'
);

?>
<!doctype html>
<html lang="hu">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title><?= $title ?></title>

    <?php foreach ($render->styles() as $style): ?>
        <link
            rel="stylesheet"
            href="<?= htmlspecialchars($basePath . $style, ENT_QUOTES, 'UTF-8') ?>"
        >
    <?php endforeach; ?>
</head>

<body>

<main>
    <?php foreach ($render->sections() as $section): ?>
        <?= $section ?>
    <?php endforeach; ?>
</main>

<?php foreach ($render->scripts() as $script): ?>
    <script
        src="<?= htmlspecialchars($basePath . $script, ENT_QUOTES, 'UTF-8') ?>"
    ></script>
<?php endforeach; ?>

</body>
</html>