<?php
declare(strict_types=1);

namespace Gallery;

use Engine\Contracts\PluginInterface;
use Engine\Core\PluginContext;
use Engine\Rendering\RenderEvent;

require_once __DIR__ . '/GalleryService.php';

final class GalleryPlugin implements PluginInterface
{
    public function getName(): string
    {
        return 'gallery';
    }

    public function getVersion(): string
    {
        return '1.1.0';
    }

    public function register(PluginContext $context): void
    {
        $context->listen(
            RenderEvent::class,
            function (RenderEvent $event) use ($context): void {
                // A galéria kizárólag az admin felületen jelenik meg.
                if (!$this->isAdminPage()) {
                    return;
                }

                $userId = $this->userId();
                if ($userId === 0) {
                    return;
                }

                $basePath = $this->applicationBasePath();
                $pluginRoot = dirname(__DIR__);

                $gallery = new GalleryService(
                    $pluginRoot . '/storage',
                    $basePath . '/plugins/gallery/image.php'
                );

                $event->addStyle($basePath . '/plugins/gallery/assets/gallery.css');
                $event->addSection($gallery->render($userId));
            }
        );
    }

    public function boot(PluginContext $context): void
    {
        // Nincs bootkori alkalmazásállapot-módosítás.
    }

    public function shutdown(PluginContext $context): void
    {
        // A listener eltávolítását az Engine végzi.
    }

    private function userId(): int
    {
        return (int) ($_SESSION['portfolio_user_id'] ?? 0);
    }

    private function isAdminPage(): bool
    {
        $path = (string) (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/');
        $path = '/' . trim($path, '/');

        return $path === '/admin' ;
    }

    private function applicationBasePath(): string
    {
        $scriptName = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
        $base = str_replace('\\', '/', dirname($scriptName));

        if ($base === '/' || $base === '\\' || $base === '.') {
            return '';
        }

        return '/' . trim($base, '/');
    }
}
