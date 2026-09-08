<?php
declare(strict_types=1);
namespace PortfolioDatabase\Models;
final class Skill { public function __construct(private \PDO $db) {} public function all(): array { return $this->db->query('SELECT * FROM skills ORDER BY name')->fetchAll(); } public function findOrCreate(string $name): int { $s=$this->db->prepare('SELECT id FROM skills WHERE name=?'); $s->execute([trim($name)]); $id=$s->fetchColumn(); if($id!==false)return (int)$id; $s=$this->db->prepare('INSERT INTO skills(name) VALUES(?)'); $s->execute([trim($name)]); return (int)$this->db->lastInsertId(); } public function delete(int $id): void { $s=$this->db->prepare('DELETE FROM skills WHERE id=?'); $s->execute([$id]); } }
