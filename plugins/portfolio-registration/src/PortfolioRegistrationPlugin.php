<?php
declare(strict_types=1);
namespace PortfolioRegistration;
use Engine\Contracts\PluginInterface;
use Engine\Core\PluginContext;
use Engine\Rendering\RenderEvent;
use PortfolioDatabase\PortfolioDatabasePlugin;
final class PortfolioRegistrationPlugin implements PluginInterface {
    public function getName(): string { return 'portfolio-registration'; }
    public function getVersion(): string { return '1.0.0'; }
    public function register(PluginContext $context): void {
        foreach(glob(__DIR__.'/Services/*.php')?:[] as $f) require_once $f;
        foreach(glob(__DIR__.'/Controllers/*.php')?:[] as $f) require_once $f;
        $context->listen(RenderEvent::class,function(RenderEvent $event) use($context): void {
            if (parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH)!=='/register' && !str_ends_with(parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH)??'','/register')) return;
            $db=$context->plugins()->get('portfolio-database')->instance;
            if(!$db instanceof PortfolioDatabasePlugin) return;
            $result=(new Controllers\RegistrationController($db))->handle($_POST);
            $event->title('Regisztráció');
            $event->addSection($result['html']);
        });
    }
    public function boot(PluginContext $context): void { if(session_status()===PHP_SESSION_NONE) @session_start(); }
    public function shutdown(PluginContext $context): void {}
}
