<?php

declare(strict_types=1);

namespace PortfolioContactMessage;

final class ContactMessageController
{
    private const MAX_NAME = 120;
    private const MAX_EMAIL = 190;
    private const MAX_PHONE = 50;
    private const MAX_SUBJECT = 255;
    private const MAX_MESSAGE = 10000;

    public function __construct(
        private ContactMessageRepository $repository,
        private string $basePath
    ) {
    }

    public function submit(int $recipientUserId, array $post): array
    {
        $errors = [];

        if (!$this->verifyCsrf((string) ($post['csrf_token'] ?? ''))) {
            $errors[] = 'Érvénytelen biztonsági token. Próbáld újra.';
        }

        if (trim((string) ($post['website'] ?? '')) !== '') {
            $errors[] = 'A küldés sikertelen.';
        }

        $name = trim((string) ($post['name'] ?? ''));
        $email = trim((string) ($post['email'] ?? ''));
        $phone = trim((string) ($post['phone'] ?? ''));
        $subject = trim((string) ($post['subject'] ?? ''));
        $message = trim((string) ($post['message'] ?? ''));

        if ($name === '') {
            $errors[] = 'A név megadása kötelező.';
        } elseif (mb_strlen($name) > self::MAX_NAME) {
            $errors[] = 'A név túl hosszú.';
        }

        if ($email === '') {
            $errors[] = 'Az email cím megadása kötelező.';
        } elseif (mb_strlen($email) > self::MAX_EMAIL) {
            $errors[] = 'Az email cím túl hosszú.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Érvénytelen email cím.';
        }

        if (mb_strlen($phone) > self::MAX_PHONE) {
            $errors[] = 'A telefonszám túl hosszú.';
        }

        if ($subject === '') {
            $errors[] = 'A tárgy megadása kötelező.';
        } elseif (mb_strlen($subject) > self::MAX_SUBJECT) {
            $errors[] = 'A tárgy túl hosszú.';
        }

        if ($message === '') {
            $errors[] = 'Az üzenet megadása kötelező.';
        } elseif (mb_strlen($message) > self::MAX_MESSAGE) {
            $errors[] = 'Az üzenet túl hosszú.';
        }

        $old = [
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'subject' => $subject,
            'message' => $message,
        ];

        if ($errors !== []) {
            return [
                'success' => false,
                'errors' => $errors,
                'old' => $old,
            ];
        }

        $this->repository->create(
            $recipientUserId,
            $name,
            $email,
            $phone !== '' ? $phone : null,
            $subject,
            $message
        );

        $this->generateCsrfToken();

        return [
            'success' => true,
            'errors' => [],
            'old' => [],
        ];
    }

    public function renderForm(
        array $old = [],
        array $errors = []
    ): string {
        $errorHtml = '';

        if ($errors !== []) {
            $errorHtml .= '<div class="contact-message-errors">'
                . '<strong>Az üzenetet nem sikerült elküldeni:</strong>'
                . '<ul>';

            foreach ($errors as $error) {
                $errorHtml .= '<li>' . $this->e((string) $error) . '</li>';
            }

            $errorHtml .= '</ul></div>';
        }

        return '<section class="contact-message-section">'
            . '<div class="contact-message-card">'
            . '<h2>Üzenet küldése</h2>'
            . '<p class="contact-message-intro">'
            . 'Vedd fel velem a kapcsolatot az alábbi űrlapon keresztül.'
            . '</p>'
            . $errorHtml
            . '<form class="contact-message-form" method="post" action="">'

            . '<input type="hidden" name="csrf_token" value="'
            . $this->e($this->csrfToken())
            . '">'

            . '<div class="contact-message-honeypot" aria-hidden="true">'
            . '<label>Weboldal'
            . '<input type="text" name="website" tabindex="-1" autocomplete="off">'
            . '</label>'
            . '</div>'

            . $this->input(
                'name',
                'Név *',
                (string) ($old['name'] ?? ''),
                self::MAX_NAME,
                'text',
                true,
                'name'
            )

            . $this->input(
                'email',
                'Email *',
                (string) ($old['email'] ?? ''),
                self::MAX_EMAIL,
                'email',
                true,
                'email'
            )

            . $this->input(
                'phone',
                'Telefon (opcionális)',
                (string) ($old['phone'] ?? ''),
                self::MAX_PHONE,
                'tel',
                false,
                'tel'
            )

            . $this->input(
                'subject',
                'Tárgy *',
                (string) ($old['subject'] ?? ''),
                self::MAX_SUBJECT,
                'text',
                true
            )

            . '<label>'
            . '<span>Üzenet *</span>'
            . '<textarea name="message" maxlength="' . self::MAX_MESSAGE
            . '" rows="7" required>'
            . $this->e((string) ($old['message'] ?? ''))
            . '</textarea>'
            . '</label>'

            . '<button type="submit" class="contact-message-submit">'
            . 'Üzenet küldése'
            . '</button>'

            . '</form>'
            . '</div>'
            . '</section>';
    }

    public function renderAdmin(int $userId): string
    {
        $selectedId = (int) ($_GET['message_id'] ?? 0);

        if ($selectedId > 0) {
            return $this->renderMessage($userId, $selectedId);
        }

        $messages = $this->repository->forUser($userId);
        $unread = $this->repository->countUnreadForUser($userId);

        $html = '<section class="contact-message-admin">'
            . '<h1>Kapott üzenetek</h1>'
            . '<p>Összesen: <strong>' . count($messages)
            . '</strong> · Olvasatlan: <strong>' . $unread . '</strong></p>';

        if ($messages === []) {
            return $html
                . '<div class="contact-message-empty">'
                . 'Még nem érkezett üzeneted.'
                . '</div></section>';
        }

        $html .= '<div class="contact-message-list">';

        foreach ($messages as $item) {
            $id = (int) $item['id'];
            $unreadClass = ((int) $item['is_read'] === 0)
                ? ' contact-message-unread'
                : '';

            $html .= '<article class="contact-message-item' . $unreadClass . '">'
                . '<div class="contact-message-item-header">'
                . '<div>'
                . '<h2>' . $this->e((string) $item['subject']) . '</h2>'
                . '<p>' . $this->e((string) $item['name'])
                . ' · ' . $this->e((string) $item['email']) . '</p>'
                . '</div>'
                . '<time>' . $this->e((string) $item['created_at']) . '</time>'
                . '</div>'
                . '<p class="contact-message-preview">'
                . $this->e($this->preview((string) $item['message']))
                . '</p>'
                . '<a class="contact-message-button" href="'
                . $this->e($this->basePath . '/admin/messages?message_id=' . $id)
                . '">Megnyitás</a>'
                . '</article>';
        }

        return $html . '</div></section>';
    }

    private function renderMessage(int $userId, int $messageId): string
    {
        $item = $this->repository->findForUser($messageId, $userId);

        if (!$item) {
            return '<section class="contact-message-admin">'
                . '<h1>Üzenet nem található</h1>'
                . '<p><a href="' . $this->e($this->basePath . '/admin/messages')
                . '">← Vissza az üzenetekhez</a></p>'
                . '</section>';
        }

        $this->repository->markAsRead($messageId, $userId);

        $html = '<section class="contact-message-admin">'
            . '<p><a href="' . $this->e($this->basePath . '/admin/messages')
            . '">← Vissza az üzenetekhez</a></p>'
            . '<article class="contact-message-detail">'
            . '<h1>' . $this->e((string) $item['subject']) . '</h1>'
            . '<dl>'
            . '<dt>Feladó</dt><dd>' . $this->e((string) $item['name']) . '</dd>'
            . '<dt>Email</dt><dd><a href="mailto:' . $this->e((string) $item['email'])
            . '">' . $this->e((string) $item['email']) . '</a></dd>';

        if (!empty($item['phone'])) {
            $html .= '<dt>Telefon</dt><dd>'
                . $this->e((string) $item['phone'])
                . '</dd>';
        }

        $html .= '<dt>Dátum</dt><dd>'
            . $this->e((string) $item['created_at'])
            . '</dd>'
            . '</dl>'
            . '<div class="contact-message-body">'
            . nl2br($this->e((string) $item['message']))
            . '</div>'
            . '</article></section>';

        return $html;
    }

    private function input(
        string $name,
        string $label,
        string $value,
        int $maxlength,
        string $type,
        bool $required,
        string $autocomplete = ''
    ): string {
        return '<label>'
            . '<span>' . $this->e($label) . '</span>'
            . '<input type="' . $this->e($type)
            . '" name="' . $this->e($name)
            . '" maxlength="' . $maxlength . '"'
            . ($required ? ' required' : '')
            . ($autocomplete !== ''
                ? ' autocomplete="' . $this->e($autocomplete) . '"'
                : '')
            . ' value="' . $this->e($value) . '">'
            . '</label>';
    }

    private function csrfToken(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        if (empty($_SESSION['portfolio_contact_message_csrf'])) {
            $this->generateCsrfToken();
        }

        return (string) $_SESSION['portfolio_contact_message_csrf'];
    }

    private function generateCsrfToken(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        $_SESSION['portfolio_contact_message_csrf'] =
            bin2hex(random_bytes(32));

        return $_SESSION['portfolio_contact_message_csrf'];
    }

    private function verifyCsrf(string $token): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        $stored = (string) (
            $_SESSION['portfolio_contact_message_csrf'] ?? ''
        );

        return $stored !== ''
            && $token !== ''
            && hash_equals($stored, $token);
    }

    private function preview(string $message): string
    {
        $message = preg_replace('/\s+/', ' ', trim($message)) ?? '';

        return mb_strlen($message) <= 180
            ? $message
            : mb_substr($message, 0, 177) . '...';
    }

    private function e(string $value): string
    {
        return htmlspecialchars(
            $value,
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );
    }
}
