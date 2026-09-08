<?php
declare(strict_types=1);

namespace PortfolioProject;

use Engine\Contracts\PluginInterface;
use Engine\Core\PluginContext;
use Engine\Rendering\RenderEvent;
use PortfolioDatabase\PortfolioDatabasePlugin;

final class PortfolioProjectPlugin implements PluginInterface
{
    public function getName(): string { return 'portfolio-project'; }
    public function getVersion(): string { return '1.1.0'; }

    public function register(PluginContext $context): void
    {
        $context->listen(RenderEvent::class, function (RenderEvent $event) use ($context): void {
            $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
            $base = $this->basePath();
            $relative = '/' . ltrim(substr($path, strlen($base)), '/');
            if (!preg_match('#^/project/(\d+)/?$#', $relative, $m)) {
                return;
            }

            $id = (int) $m[1];
            $db = $context->plugins()->get('portfolio-database')->instance;
            if (!$db instanceof PortfolioDatabasePlugin) {
                return;
            }

            $p = $db->projects()->find($id);
            if (!$p) {
                http_response_code(404);
                $event->title('404');
                $event->addSection('<nav class="portfolio-nav"><a href="'.$base.'/">Főoldal</a></nav><section class="portfolio-wrap"><h1>Projekt nem található.</h1></section>');
                return;
            }

            $e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
            $links = '';
            foreach ($db->links()->forProject($id) as $l) {
                $links .= '<a class="portfolio-button" target="_blank" rel="noopener" href="'.$e($l['url']).'">'
                    .$e($l['title']).' ('.$e($l['type']).')</a>';
            }

            $event->title($p['title'].' — Projekt');
            $event->addSection(
                '<nav class="portfolio-nav">'
                .'<a href="'.$base.'/">Főoldal</a>'
                .'<a href="'.$base.'/u/'.$p['user_id'].'">← Portfolio</a>'
                .'<a href="'.$base.'/admin">Admin</a>'
                .'</nav>'
                .'<section class="portfolio-wrap">'
                .'<p><a href="'.$base.'/u/'.$p['user_id'].'">← Vissza a portfoliohoz</a></p>'
                .'<h1>'.$e($p['title']).'</h1>'
                .'<div class="portfolio-card"><p>'.nl2br($e($p['description'])).'</p><div class="portfolio-links">'.$links.'</div></div>'
                .'</section>'
            );
        });
    }

    public function boot(PluginContext $context): void {}
    public function shutdown(PluginContext $context): void {}

    private function basePath(): string
    {
        return rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    }
}
