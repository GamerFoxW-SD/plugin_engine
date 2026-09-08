<?php
declare(strict_types=1);
namespace PortfolioDatabase\Models;
final class Project {
    public function __construct(private \PDO $db) {}
    public function find(int $id): ?array { $s=$this->db->prepare("SELECT * FROM projects WHERE id=?"); $s->execute([$id]); return $s->fetch() ?: null; }
    public function forUser(int $userId): array { $s=$this->db->prepare("SELECT * FROM projects WHERE user_id=? ORDER BY updated_at DESC"); $s->execute([$userId]); return $s->fetchAll(); }
    public function all(): array { return $this->db->query("SELECT p.*,u.name AS user_name FROM projects p JOIN users u ON u.id=p.user_id ORDER BY p.updated_at DESC")->fetchAll(); }
    public function create(int $userId,string $title,string $description): int { $s=$this->db->prepare("INSERT INTO projects(user_id,title,description) VALUES(?,?,?)"); $s->execute([$userId,trim($title),trim($description)]); return (int)$this->db->lastInsertId(); }
    public function update(int $id,int $userId,string $title,string $description): void { $s=$this->db->prepare("UPDATE projects SET user_id=?,title=?,description=? WHERE id=?"); $s->execute([$userId,trim($title),trim($description),$id]); }
    public function delete(int $id): void { $s=$this->db->prepare("DELETE FROM projects WHERE id=?"); $s->execute([$id]); }
}
