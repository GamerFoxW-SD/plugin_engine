<?php
declare(strict_types=1);
namespace PortfolioProjectSkill;

final class ProjectSkill
{
    public function __construct(private \PDO $db) {}
    public function forProject(int $projectId): array
    {
        $s = $this->db->prepare('SELECT s.* FROM skills s JOIN project_skills ps ON ps.skill_id=s.id WHERE ps.project_id=? ORDER BY s.name');
        $s->execute([$projectId]);
        return $s->fetchAll();
    }
    public function has(int $projectId, int $skillId): bool
    {
        $s = $this->db->prepare('SELECT 1 FROM project_skills WHERE project_id=? AND skill_id=?');
        $s->execute([$projectId, $skillId]);
        return (bool)$s->fetchColumn();
    }
    public function add(int $projectId, int $skillId): void
    {
        $s = $this->db->prepare('INSERT IGNORE INTO project_skills(project_id,skill_id) VALUES(?,?)');
        $s->execute([$projectId, $skillId]);
    }
    public function remove(int $projectId, int $skillId): void
    {
        $s = $this->db->prepare('DELETE FROM project_skills WHERE project_id=? AND skill_id=?');
        $s->execute([$projectId, $skillId]);
    }
}
