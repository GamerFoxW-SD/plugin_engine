<?php
declare(strict_types=1);

namespace PortfolioProjectRating;

final class ProjectRatingRepository
{
    public function __construct(private \PDO $db) {}

    public function ensureTable(): void
    {
        $this->db->exec(<<<SQL
CREATE TABLE IF NOT EXISTS project_ratings (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    project_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    rating TINYINT UNSIGNED NOT NULL,
    comment TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_project_rating_user (project_id, user_id),
    INDEX idx_project_rating_project (project_id),
    INDEX idx_project_rating_user (user_id),
    CONSTRAINT chk_project_rating_value CHECK (rating BETWEEN 1 AND 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function hasRated(int $projectId, int $userId): bool
    {
        $s = $this->db->prepare(
            'SELECT 1 FROM project_ratings WHERE project_id = ? AND user_id = ? LIMIT 1'
        );
        $s->execute([$projectId, $userId]);
        return $s->fetchColumn() !== false;
    }

    public function create(int $projectId, int $userId, int $rating, ?string $comment): int
    {
        $s = $this->db->prepare(
            'INSERT INTO project_ratings (project_id, user_id, rating, comment) VALUES (?, ?, ?, ?)'
        );
        $s->execute([
            $projectId,
            $userId,
            $rating,
            $comment !== null && trim($comment) !== '' ? trim($comment) : null,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function summary(int $projectId): array
    {
        $s = $this->db->prepare(
            'SELECT COUNT(*) AS total, COALESCE(AVG(rating), 0) AS average FROM project_ratings WHERE project_id = ?'
        );
        $s->execute([$projectId]);
        $row = $s->fetch() ?: [];

        return [
            'total' => (int) ($row['total'] ?? 0),
            'average' => (float) ($row['average'] ?? 0),
        ];
    }

    public function forProject(int $projectId): array
    {
        $s = $this->db->prepare(
            'SELECT pr.*, u.name AS user_name
             FROM project_ratings pr
             LEFT JOIN users u ON u.id = pr.user_id
             WHERE pr.project_id = ?
             ORDER BY pr.created_at DESC, pr.id DESC'
        );
        $s->execute([$projectId]);
        return $s->fetchAll() ?: [];
    }
}
