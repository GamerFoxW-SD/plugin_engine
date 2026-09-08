<?php
declare(strict_types=1);
namespace PortfolioRegistration\Services;
use PortfolioDatabase\PortfolioDatabasePlugin;
final class RegistrationService {
    public function __construct(private PortfolioDatabasePlugin $db) {}
    public function register(array $data): array {
        $name=trim((string)($data['name']??'')); $email=trim((string)($data['email']??'')); $pass=(string)($data['password']??''); $confirm=(string)($data['password_confirm']??'');
        if($name===''||!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($pass)<8||$pass!==$confirm) return [false,'Érvénytelen adatok. A jelszó legalább 8 karakter legyen és a két jelszó egyezzen.'];
        if($this->db->users()->findByEmail($email)) return [false,'Ez az email cím már regisztrálva van.'];
        $isFirst=count($this->db->users()->all())===0;
        $id=$this->db->users()->create(['name'=>$name,'email'=>$email,'password_hash'=>password_hash($pass,PASSWORD_DEFAULT),'contact_visible'=>1,'is_admin'=>$isFirst?1:0]);
        $_SESSION['portfolio_user_id']=$id;
        return [true,''];
    }
}
