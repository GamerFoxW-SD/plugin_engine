<?php
declare(strict_types=1);
namespace PortfolioRegistration\Controllers;
use PortfolioDatabase\PortfolioDatabasePlugin;
use PortfolioRegistration\Services\RegistrationService;
final class RegistrationController {
    public function __construct(private PortfolioDatabasePlugin $db) {}
    public function handle(array $post): array {
        $error='';
        if(($_SERVER['REQUEST_METHOD']??'GET')==='POST') {
            [$ok,$msg]=(new RegistrationService($this->db))->register($post);
            if($ok){ $base=rtrim(str_replace('\\','/',dirname($_SERVER['SCRIPT_NAME']??'/')),'/'); header('Location: '.$base.'/admin'); exit; } $error=$msg;
        }
        $e=fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
        $html='<section class="portfolio-auth"><h1>Regisztráció</h1>'.($error?'<p class="alert">'.$e($error).'</p>':'').'<form method="post"><label>Név<input name="name" required maxlength="160"></label><label>Email<input type="email" name="email" required></label><label>Jelszó<input type="password" name="password" minlength="8" required></label><label>Jelszó újra<input type="password" name="password_confirm" minlength="8" required></label><button>Regisztráció</button></form></section>';
        return ['html'=>$html];
    }
}
