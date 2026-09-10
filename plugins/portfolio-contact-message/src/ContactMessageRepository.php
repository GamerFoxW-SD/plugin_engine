<?php

declare(strict_types=1);

namespace PortfolioContactMessage;

final class ContactMessageRepository
{
    public function __construct(
        private \PDO $pdo
    ) {
    }

    public function ensureTable(): void
    {
        /*
         * Nincs FK szándékosan: a plugin nem feltételezheti,
         * hogy a Portfolio user tábla neve/sémája változatlan.
         */
        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS uzenet (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                recipient_user_id BIGINT UNSIGNED NOT NULL,
                name VARCHAR(120) NOT NULL,
                email VARCHAR(190) NOT NULL,
                phone VARCHAR(50) NULL,
                subject VARCHAR(255) NOT NULL,
                message TEXT NOT NULL,
                is_read TINYINT(1) NOT NULL DEFAULT 0,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                INDEX idx_uzenet_recipient (recipient_user_id),
                INDEX idx_uzenet_recipient_read (recipient_user_id, is_read),
                INDEX idx_uzenet_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
              COLLATE=utf8mb4_unicode_ci"
        );
    }

    public function create(
        int $recipientUserId,
        string $name,
        string $email,
        ?string $phone,
        string $subject,
        string $message
    ): int {
        $stmt = $this->pdo->prepare(
            "INSERT INTO uzenet
                (recipient_user_id, name, email, phone, subject, message)
             VALUES
                (:recipient_user_id, :name, :email, :phone, :subject, :message)"
        );

        $stmt->execute([
            'recipient_user_id' => $recipientUserId,
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'subject' => $subject,
            'message' => $message,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function forUser(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id, name, email, phone, subject, message,
                    is_read, created_at, updated_at
             FROM uzenet
             WHERE recipient_user_id = :user_id
             ORDER BY created_at DESC, id DESC"
        );

        $stmt->execute(['user_id' => $userId]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function findForUser(int $messageId, int $userId): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id, name, email, phone, subject, message,
                    is_read, created_at, updated_at
             FROM uzenet
             WHERE id = :id
               AND recipient_user_id = :user_id
             LIMIT 1"
        );

        $stmt->execute([
            'id' => $messageId,
            'user_id' => $userId,
        ]);

        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function markAsRead(int $messageId, int $userId): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE uzenet
             SET is_read = 1, updated_at = CURRENT_TIMESTAMP
             WHERE id = :id
               AND recipient_user_id = :user_id"
        );

        $stmt->execute([
            'id' => $messageId,
            'user_id' => $userId,
        ]);
    }

    public function countForUser(int $userId): int
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*)
             FROM uzenet
             WHERE recipient_user_id = :user_id"
        );

        $stmt->execute(['user_id' => $userId]);

        return (int) $stmt->fetchColumn();
    }

    public function countUnreadForUser(int $userId): int
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*)
             FROM uzenet
             WHERE recipient_user_id = :user_id
               AND is_read = 0"
        );

        $stmt->execute(['user_id' => $userId]);

        return (int) $stmt->fetchColumn();
    }
}
