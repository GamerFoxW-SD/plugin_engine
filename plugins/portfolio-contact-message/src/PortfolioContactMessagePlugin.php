<?php

declare(strict_types=1);

namespace PortfolioContactMessage;

use Engine\Contracts\PluginInterface;
use Engine\Core\PluginContext;
use Engine\Rendering\RenderEvent;

final class PortfolioContactMessagePlugin implements PluginInterface
{
    public function getName(): string
    {
        return 'portfolio-contact-message';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function register(PluginContext $context): void
    {
        require_once __DIR__ . '/ContactMessageRepository.php';
        require_once __DIR__ . '/ContactMessageController.php';

        $context->listen(RenderEvent::class, function (RenderEvent $event) use ($context): void {
            $this->handleRequest($event, $context);
        });
    }

    public function boot(PluginContext $context): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        $database = $context->plugins()->get('portfolio-database')->instance;

        if (!method_exists($database, 'pdo')) {
            throw new \RuntimeException(
                'portfolio-database plugin does not expose pdo().'
            );
        }

        $repository = new ContactMessageRepository($database->pdo());
        $repository->ensureTable();
    }

    public function shutdown(PluginContext $context): void
    {
    }

    private function handleRequest(
        RenderEvent $event,
        PluginContext $context
    ): void {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        $database = $context->plugins()->get('portfolio-database')->instance;

        if (!method_exists($database, 'pdo')) {
            return;
        }

        $repository = new ContactMessageRepository($database->pdo());
        $controller = new ContactMessageController(
            $repository,
            $this->basePath()
        );

        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
        $base = $this->basePath();
        $relative = '/' . ltrim(substr($path, strlen($base)), '/');

        /*
         * Admin messages.
         */
        if (preg_match('#^/admin/messages/?$#', $relative)) {
            $userId = (int) ($_SESSION['portfolio_user_id'] ?? 0);

            if ($userId <= 0) {
                return;
            }

            $event->addStyle(
                '/plugins/portfolio-contact-message/assets/contact-message.css'
            );

            $event->addSection($controller->renderAdmin($userId));
            return;
        }

        /*
         * Portfolio profile: /u/{id}
         */
        if (!preg_match('#^/u/(\d+)/?$#', $relative, $matches)) {
            return;
        }

        /*
         * Logged-in users must not see the public contact form.
         */
        if ((int) ($_SESSION['portfolio_user_id'] ?? 0) > 0) {
            return;
        }

        $recipientUserId = (int) $matches[1];

        $event->addStyle(
         '/plugins/portfolio-contact-message/assets/contact-message.css'
        );

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $result = $controller->submit(
                $recipientUserId,
                $_POST
            );

            if ($result['success'] === true) {
                header(
                    'Location: ' . $base . '/u/' . $recipientUserId . '?message=sent',
                    true,
                    303
                );
                exit;
            }

            $event->addSection(
                $controller->renderForm(
                    $result['old'],
                    $result['errors']
                )
            );

            return;
        }

        if (($_GET['message'] ?? '') === 'sent') {
            $event->addSection(
                '<section class="contact-message-success">'
                . '<h2>Üzenet elküldve</h2>'
                . '<p>Az üzeneted sikeresen elküldve.</p>'
                . '</section>'
            );
            return;
        }

        $event->addSection(
            $controller->renderForm()
        );
    }

    private function basePath(): string
    {
        $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/index.php');
        $directory = dirname($script);

        return $directory === '/' || $directory === '\\'
            ? ''
            : rtrim($directory, '/');
    }
}
