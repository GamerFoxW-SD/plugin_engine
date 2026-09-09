<?php
declare(strict_types=1);

namespace Gallery;

final class GalleryService
{
    private const MAX_FILE_SIZE = 10 * 1024 * 1024;
    private  string $rootURL = 'localhost/git/my-plugin-engine/';

    private const ALLOWED_MIMES = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
    ];

    public function __construct(
        private readonly string $storageRoot,
        private readonly string $imageEndpoint
    ) {
    }

    public function render(int $userId): string
    {
        if ($userId <= 0) {
            return '';
        }

        $this->ensureUserDirectory($userId);

        $message = '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $message = $this->handleUpload($userId);
        }

        $images = $this->listImages($userId);

        ob_start();
        ?>
        <section class="admin">
            <h2>Képtár</h2>

            <?php if ($message !== ''): ?>
                <div class="gallery-message">
                    <?= htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <form method="post" enctype="multipart/form-data" class="gallery-upload">
                <label for="gallery-image">Kép feltöltése</label>
                <input
                    id="gallery-image"
                    type="file"
                    name="gallery_image"
                    accept="image/jpeg,image/png,image/gif,image/webp"
                    required
                >
                <button type="submit">Feltöltés</button>
            </form>

            <?php if ($images === []): ?>
                <p>Még nincs feltöltött kép.</p>
            <?php else: ?>
                <div class="gallery-grid">
                    <?php foreach ($images as $image): 
                        $image['url'] = str_replace(
    $this->rootURL,
    '',
    $image['url']
);
                        ?>
                        <article class="gallery-item">
                            <a href="<?= htmlspecialchars($image['url'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" target="_blank" rel="noopener">
                                <img
                                    src="<?= htmlspecialchars($image['url'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
                                    alt="<?= htmlspecialchars((string) $image['id'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
                                    loading="lazy"
                                >
                            </a>
                            <div class="gallery-url">
                                <code><?= htmlspecialchars($this->rootURL . $image['url'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></code>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
        <?php

        return (string) ob_get_clean();
    }

    /** @return array<int, array{id:string,url:string}> */
    public function listImages(int $userId): array
    {
        $directory = $this->userDirectory($userId);
        $metadataFile = $directory . '/metadata.json';

        if (!is_file($metadataFile)) {
            return [];
        }

        $json = file_get_contents($metadataFile);
        if ($json === false || $json === '') {
            return [];
        }

        $metadata = json_decode($json, true);
        if (!is_array($metadata)) {
            return [];
        }

        $result = [];
        foreach ($metadata as $item) {
            if (!is_array($item) || !isset($item['id'], $item['filename'])) {
                continue;
            }

            $id = (string) $item['id'];
            if (!preg_match('/^[a-f0-9]{32}$/', $id)) {
                continue;
            }

            $filename = basename((string) $item['filename']);
            $file = $directory . '/' . $filename;

            if (!is_file($file)) {
                continue;
            }

            $result[] = [
                'id' => $id,
                'url' => $_SERVER['SERVER_NAME']. $this->imageEndpoint . '?id=' . rawurlencode($id). '&uid=' . rawurlencode((string) $userId),
            ];
        }

        return array_reverse($result);
    }

    /**
     * Az adott user saját képét oldja fel.
     * Az endpoint ezt használja, így egy user nem fér hozzá más user képeihez.
     */
    public function resolveImageForUser(int $userId, string $imageId): ?array
    {
        if ($userId <= 0 || !preg_match('/^[a-f0-9]{32}$/', $imageId)) {
            return null;
        }

        $directory = $this->userDirectory($userId);
        $metadataFile = $directory . '/metadata.json';

        if (!is_file($metadataFile)) {
            return null;
        }

        $json = file_get_contents($metadataFile);
        $metadata = is_string($json) ? json_decode($json, true) : null;

        if (!is_array($metadata)) {
            return null;
        }

        foreach ($metadata as $item) {
            if (!is_array($item) || (string) ($item['id'] ?? '') !== $imageId) {
                continue;
            }

            $filename = basename((string) ($item['filename'] ?? ''));
            $file = $directory . '/' . $filename;

            if (!is_file($file)) {
                return null;
            }

            return [
                'path' => $file,
                'mime' => (string) ($item['mime'] ?? 'application/octet-stream'),
                'size' => (int) ($item['size'] ?? filesize($file)),
            ];
        }

        return null;
    }

    private function handleUpload(int $userId): string
    {
        if (!isset($_FILES['gallery_image'])) {
            return 'Nem érkezett feltöltött kép.';
        }

        $file = $_FILES['gallery_image'];

        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return 'A feltöltés sikertelen.';
        }

        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0 || $size > self::MAX_FILE_SIZE) {
            return 'A kép mérete legfeljebb 10 MB lehet.';
        }

        $tmp = (string) ($file['tmp_name'] ?? '');
        if (!is_uploaded_file($tmp)) {
            return 'Érvénytelen feltöltés.';
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($tmp);

        if (!is_string($mime) || !in_array($mime, self::ALLOWED_MIMES, true)) {
            return 'Csak JPG, PNG, GIF vagy WebP kép tölthető fel.';
        }

        $extension = match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            default => null,
        };

        if ($extension === null) {
            return 'Nem támogatott képformátum.';
        }

        $this->ensureUserDirectory($userId);

        $id = bin2hex(random_bytes(16));
        $filename = $id . '.' . $extension;
        $destination = $this->userDirectory($userId) . '/' . $filename;

        if (!move_uploaded_file($tmp, $destination)) {
            return 'A kép mentése sikertelen.';
        }

        $metadataFile = $this->userDirectory($userId) . '/metadata.json';
        $metadata = [];

        if (is_file($metadataFile)) {
            $json = file_get_contents($metadataFile);
            $decoded = is_string($json) ? json_decode($json, true) : null;
            if (is_array($decoded)) {
                $metadata = $decoded;
            }
        }

        $metadata[] = [
            'id' => $id,
            'filename' => $filename,
            'mime' => $mime,
            'size' => $size,
            'created_at' => date(DATE_ATOM),
        ];

        $encoded = json_encode(
            $metadata,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );

        if ($encoded === false || file_put_contents($metadataFile, $encoded, LOCK_EX) === false) {
            @unlink($destination);
            return 'A kép metaadatainak mentése sikertelen.';
        }

        return 'A kép sikeresen feltöltve.';
    }

    private function ensureUserDirectory(int $userId): void
    {
        if ($userId <= 0) {
            throw new \InvalidArgumentException('Érvénytelen user ID.');
        }

        if (!is_dir($this->storageRoot) && !mkdir($this->storageRoot, 0755, true) && !is_dir($this->storageRoot)) {
            throw new \RuntimeException('A storage könyvtár nem hozható létre.');
        }

        $directory = $this->userDirectory($userId);
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new \RuntimeException('A user könyvtár nem hozható létre.');
        }
    }

    private function userDirectory(int $userId): string
    {
        return $this->storageRoot . '/' . $userId;
    }
}
