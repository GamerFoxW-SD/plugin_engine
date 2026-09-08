<?php
declare(strict_types=1);

namespace PortfolioUser;

use Engine\Contracts\PluginInterface;
use Engine\Core\PluginContext;
use Engine\Rendering\RenderEvent;
use PortfolioDatabase\PortfolioDatabasePlugin;

final class PortfolioUserPlugin implements PluginInterface
{
    public function getName(): string { return 'portfolio-user'; }
    public function getVersion(): string { return '1.1.0'; }

    public function register(PluginContext $context): void
    {
        $context->listen(RenderEvent::class, function (RenderEvent $event) use ($context): void {
            $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
            $base = $this->basePath();
            $relative = '/' . ltrim(substr($path, strlen($base)), '/');
            if (!preg_match('#^/u/(\d+)/?$#', $relative, $m)) {
                return;
            }

            $id = (int) $m[1];
            $db = $context->plugins()->get('portfolio-database')->instance;
            if (!$db instanceof PortfolioDatabasePlugin) {
                return;
            }

            $u = $db->users()->publicProfile($id);
            if (!$u) {
                http_response_code(404);
                $event->title('404');
                $event->addSection('<nav class="portfolio-nav"><a href="'.$base.'/">Főoldal</a></nav><section class="portfolio-wrap"><h1>Portfolio nem található.</h1></section>');
                return;
            }

            $e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
            $skills = $db->userSkills()->forUser($id);
            $skillHtml = '';
            foreach ($skills as $s) {
                $skillHtml .= '<span class="portfolio-card">'.$e($s['name']).'</span>';
            }

            $projects = $db->projects()->forUser($id);
            $phtml = '';
            foreach ($projects as $p) {
                $phtml .= '<article class="portfolio-card">'
                    .'<h3>'.$e($p['title']).'</h3>'
                    .'<p>'.nl2br($e($p['description'])).'</p>'
                    .'<a class="portfolio-button" href="'.$base.'/project/'.$p['id'].'">Projekt</a>'
                    .'</article>';
            }

            $avatar = $u['profile_image']
                ? '<img class="portfolio-avatar" src="'.$e($u['profile_image']).'" alt="'.$e($u['name']).'">'
                : '';

            $contact = '';
            if ($u['email'] || $u['phone']) {
                $contact = '<p>'
                    .($u['email'] ? '<a href="mailto:'.$e($u['email']).'">'.$e($u['email']).'</a> ' : '')
                    .($u['phone'] ? ' · '.$e($u['phone']) : '')
                    .'</p>';
            }

            $event->title($u['name'].' — Portfolio');
            $event->addSection(
                '<nav class="portfolio-nav">'
                .'<a href="'.$base.'/">Főoldal</a>'
                .'<a href="'.$base.'/admin">Admin</a>'
                .'</nav>'
                .'<section class="portfolio-wrap">'
                .$avatar
                .'<h1>'.$e($u['name']).'</h1>'
                .$contact
                .'<p class="portfolio-url">Portfolio URL: <code>'.$e($base.'/u/'.$id).'</code></p>'
                .'<h2>Skillek</h2><div class="portfolio-grid">'.$skillHtml.'</div>'
                .'<h2>Projektek</h2><div class="portfolio-grid">'.$phtml.'</div>'
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
