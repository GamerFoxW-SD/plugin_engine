<?php

ob_start();

session_start();


require dirname(__DIR__, 2) . '/autoload.php';
require_once __DIR__ . '/src/GalleryService.php';

use Gallery\GalleryService;

$id = isset($_GET['id']) ? (string) $_GET['id'] : '';
$userId = (int) ($_GET['uid'] ?? 0);

$pluginRoot = __DIR__;

$service = new GalleryService(
    $pluginRoot . '/storage',
    ''
);

$image = $service->resolveImageForUser($userId, $id);

if ($image === null) {
    ob_end_clean();

    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Kép nem található.';
    exit;
}

$mime = $image['mime'];

if (!in_array($mime, [
    'image/jpeg',
    'image/png',
    'image/gif',
    'image/webp'
], true)) {
    ob_end_clean();

    http_response_code(404);
    exit;
}

if (!is_file($image['path']) || !is_readable($image['path'])) {
    ob_end_clean();

    http_response_code(404);
    exit;
}

/*
 * Minden, az autoloadból vagy más PHP fájlból érkező
 * esetleges output törlése.
 */
ob_end_clean();

header('Content-Type: ' . $mime);
header('Content-Length: ' . (string) filesize($image['path']));
header('Cache-Control: private, max-age=3600');
header('X-Content-Type-Options: nosniff');

readfile($image['path']);
exit;