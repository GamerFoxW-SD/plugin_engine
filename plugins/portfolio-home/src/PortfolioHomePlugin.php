<?php
declare(strict_types=1);

namespace PortfolioHome;

use Engine\Contracts\PluginInterface;
use Engine\Core\PluginContext;
use Engine\Rendering\RenderEvent;
use PortfolioDatabase\PortfolioDatabasePlugin;

final class PortfolioHomePlugin implements PluginInterface
{
    public function getName(): string { return 'portfolio-home'; }
    public function getVersion(): string { return '1.1.0'; }

    public function register(PluginContext $context): void
    {
        $context->listen(RenderEvent::class, function (RenderEvent $event) use ($context): void {
            $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
            $base = $this->basePath();
            $relative = '/' . ltrim(substr($path, strlen($base)), '/');
            if ($relative !== '/' && $relative !== '/index.php') {
                return;
            }

            $db = $context->plugins()->get('portfolio-database')->instance;
            if (!$db instanceof PortfolioDatabasePlugin) {
                return;
            }

            $e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
            $users = $db->users()->all();
            $projects = $db->projects()->all();

            $cards = '';
            foreach ($users as $u) {
                $avatar = $u['profile_image']
                    ? '<img class="portfolio-avatar portfolio-avatar-small" src="'.$e($u['profile_image']).'" alt="'.$e($u['name']).'">'
                    : '';
                $cards .= '<article class="portfolio-card">'
                    .$avatar
                    .'<h2>'.$e($u['name']).'</h2>'
                    .($u['contact_visible'] ? '<p>Elérhető kapcsolat</p>' : '')
                    .'<a class="portfolio-button" href="'.$base.'/u/'.$u['id'].'">Portfolio megtekintése</a>'
                    .'</article>';
            }

            $projectCards = '';
            foreach ($projects as $p) {
                $projectCards .= '<article class="portfolio-card">'
                    .'<h3>'.$e($p['title']).'</h3>'
                    .'<p>'.$e($p['user_name']).'</p>'
                    .'<a class="portfolio-button" href="'.$base.'/project/'.$p['id'].'">Projekt megtekintése</a>'
                    .'</article>';
            }

            $event->title('Portfolio');
            $event->addSection(
                '<nav class="portfolio-nav">'
                .'<a href="'.$base.'/">Főoldal</a>'
                .'<a href="'.$base.'/register">Regisztráció</a>'
                .'<a href="'.$base.'/admin">Admin</a>'
                .'</nav>'
                .'<section class="portfolio-wrap">'
                .'<h1>Portfolio</h1>'
                .'<p>Bemutatkozások és projektek egy helyen.</p>'
                .'<h2>Portfóliók</h2>'
                .'<div class="portfolio-grid">'.$cards.'</div>'
                .'<h2>Projektek</h2>'
                .'<div class="portfolio-grid">'.$projectCards.'</div>'
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
