<?php
declare(strict_types=1);
namespace PortfolioTheme;
use Engine\Contracts\PluginInterface;
use Engine\Core\PluginContext;
use Engine\Rendering\RenderEvent;
use PortfolioDatabase\PortfolioDatabasePlugin;
final class PortfolioThemePlugin implements PluginInterface {
    private ?PortfolioDatabasePlugin $db=null;
    public function getName():string{return 'portfolio-theme';}
    public function getVersion():string{return '1.2.0';}
    public function register(PluginContext $context):void{
        $context->listen(RenderEvent::class,function(RenderEvent $event)use($context):void{
            if(!$this->db)$this->db=$context->plugins()->get('portfolio-database')->instance;
            $event->addStyle('/plugins/portfolio-theme/assets/portfolio.css');
            $userId=$this->currentPortfolioUserId();
            $s=$userId>0 ? $this->settingsForUser($userId) : $this->defaults();
            $customCss=str_ireplace(['</style','<script','</script'],['','', ''],$s['custom_css']);
            $css=':root{--portfolio-primary:'.$this->safeCss($s['primary']).';--portfolio-secondary:'.$this->safeCss($s['secondary']).';--portfolio-background:'.$this->safeCss($s['background']).';--portfolio-text:'.$this->safeCss($s['text']).';--portfolio-card:'.$this->safeCss($s['card']).';--portfolio-radius:'.$this->safeCss($s['radius']).';}'.$customCss;
            $event->addSection('<style data-portfolio-theme>'. $css .'</style>');
        });
    }
    public function boot(PluginContext $context):void{$this->db=$context->plugins()->get('portfolio-database')->instance;}
    public function shutdown(PluginContext $context):void{$this->db=null;}
    private function safeCss(string $v):string{return preg_match('/^[#a-zA-Z0-9().,% _-]+$/',$v)?$v:'';}
    public function settings(): array { return $this->settingsForUser($this->currentPortfolioUserId()); }
    public function settingsForUser(int $userId): array {
        if(!$this->db || $userId<=0)return $this->defaults();
        $rows=$this->db->pdo()->prepare("SELECT setting_key,setting_value FROM portfolio_settings WHERE user_id=? AND setting_key LIKE 'theme_%'");
        $rows->execute([$userId]); $s=$this->defaults(); foreach($rows->fetchAll() as $r)$s[substr($r['setting_key'],6)]=$r['setting_value']??''; return $s;
    }
    private function defaults():array{return ['primary'=>'#6d5dfc','secondary'=>'#00b894','background'=>'#0f1220','text'=>'#f5f7ff','card'=>'#171b2e','radius'=>'18px','custom_css'=>''];}
    public function saveForUser(int $userId,array $data):void{
        if(!$this->db || $userId<=0)return; $s=$this->defaults(); foreach($s as $k=>$v)$s[$k]=isset($data[$k])?(string)$data[$k]:$v;
        $q=$this->db->pdo()->prepare("INSERT INTO portfolio_settings(user_id,setting_key,setting_value) VALUES(?,?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)");
        foreach($s as $k=>$v)$q->execute([$userId,'theme_'.$k,$v]);
    }
    private function currentPortfolioUserId(): int {
        $path=parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH)??'/'; $base=rtrim(str_replace('\\','/',dirname($_SERVER['SCRIPT_NAME']??'/')),'/');
        $relative='/'.ltrim(substr($path,strlen($base)),'/');
        if(preg_match('#^/u/(\d+)(?:/|$)#',$relative,$m))return (int)$m[1];
        if(preg_match('#^/project/(\d+)(?:/|$)#',$relative,$m) && $this->db){$p=$this->db->projects()->find((int)$m[1]); return $p?(int)$p['user_id']:0;}
        return 0;
    }

}
