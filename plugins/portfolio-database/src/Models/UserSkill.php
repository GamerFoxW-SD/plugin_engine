<?php
declare(strict_types=1);
namespace PortfolioDatabase\Models;
final class UserSkill { public function __construct(private \PDO $db) {} public function forUser(int $userId): array { $s=$this->db->prepare('SELECT s.* FROM skills s JOIN user_skills us ON us.skill_id=s.id WHERE us.user_id=? ORDER BY s.name'); $s->execute([$userId]); return $s->fetchAll(); } public function add(int $userId,int $skillId): void { $s=$this->db->prepare('INSERT IGNORE INTO user_skills(user_id,skill_id) VALUES(?,?)'); $s->execute([$userId,$skillId]); } public function remove(int $userId,int $skillId): void { $s=$this->db->prepare('DELETE FROM user_skills WHERE user_id=? AND skill_id=?'); $s->execute([$userId,$skillId]); } }
