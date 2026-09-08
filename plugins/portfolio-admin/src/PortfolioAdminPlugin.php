<?php
declare(strict_types=1);
namespace PortfolioAdmin;
use Engine\Contracts\PluginInterface;
use Engine\Core\PluginContext;
use Engine\Rendering\RenderEvent;
use PortfolioDatabase\PortfolioDatabasePlugin;
use PortfolioTheme\PortfolioThemePlugin;
final class PortfolioAdminPlugin implements PluginInterface {
    public function getName(): string{return 'portfolio-admin';}
    public function getVersion(): string{return '1.2.0';}
    public function register(PluginContext $context): void {
        foreach(glob(__DIR__.'/Controllers/*.php')?:[] as $f) require_once $f;
        foreach(glob(__DIR__.'/Views/*.php')?:[] as $f) require_once $f;
        $context->listen(RenderEvent::class,function(RenderEvent $event) use($context):void{
            $path=parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH)??'/';
            if(!str_contains($path,'/admin')) return;
            $db=$context->plugins()->get('portfolio-database')->instance;
            $theme=$context->plugins()->get('portfolio-theme')->instance;
            if(!$db instanceof PortfolioDatabasePlugin || !$theme instanceof PortfolioThemePlugin)return;
            $c=new Controllers\AdminController($db,$theme);
            $event->title('Portfolio Admin');
            $event->addStyle('/plugins/portfolio-admin/assets/admin.css');
            $event->addSection($c->handle($path,$_POST,$_GET));
        });
    }
    public function boot(PluginContext $context):void{if(session_status()===PHP_SESSION_NONE)@session_start();}
    public function shutdown(PluginContext $context):void{}
}
