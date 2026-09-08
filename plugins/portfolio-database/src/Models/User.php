<?php
declare(strict_types=1);
namespace PortfolioDatabase\Models;
final class User {
    public function __construct(private \PDO $db) {}
    public function find(int $id): ?array { $s=$this->db->prepare("SELECT * FROM users WHERE id=?"); $s->execute([$id]); return $s->fetch() ?: null; }
    public function findByEmail(string $email): ?array { $s=$this->db->prepare("SELECT * FROM users WHERE email=?"); $s->execute([$email]); return $s->fetch() ?: null; }
    public function all(): array { return $this->db->query("SELECT * FROM users ORDER BY name")->fetchAll(); }
    public function create(array $d): int {
        $s=$this->db->prepare("INSERT INTO users(name,profile_image,email,phone,contact_visible,password_hash,is_admin) VALUES(?,?,?,?,?,?,?)");
        $s->execute([$d['name'],$d['profile_image']??null,$d['email'],$d['phone']??null,(int)($d['contact_visible']??1),$d['password_hash'],(int)($d['is_admin']??0)]);
        return (int)$this->db->lastInsertId();
    }
    public function update(int $id,array $d): void {
        $s=$this->db->prepare("UPDATE users SET name=?,profile_image=?,email=?,phone=?,contact_visible=?,is_admin=?".(isset($d['password_hash'])?",password_hash=?":"")." WHERE id=?");
        $a=[$d['name'],$d['profile_image']??null,$d['email'],$d['phone']??null,(int)($d['contact_visible']??1),(int)($d['is_admin']??0)];
        if(isset($d['password_hash'])) $a[]=$d['password_hash']; $a[]=$id; $s->execute($a);
    }
    public function delete(int $id): void { $s=$this->db->prepare("DELETE FROM users WHERE id=?"); $s->execute([$id]); }
    public function publicProfile(int $id): ?array {
        $u=$this->find($id); if(!$u)return null;
        unset($u['password_hash']);
        if(!(bool)$u['contact_visible']) { $u['email']=null; $u['phone']=null; }
        return $u;
    }
}
