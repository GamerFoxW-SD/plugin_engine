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

        $path = parse_url(
            $_SERVER['REQUEST_URI'] ?? '/',
            PHP_URL_PATH
        ) ?? '/';

        $base = $this->basePath();

        $relative = '/' . ltrim(
            substr($path, strlen($base)),
            '/'
        );

        /*
         * Csak /project/{id} oldalakon fusson
         */
        if (!preg_match('#^/project/(\d+)/?$#', $relative, $m)) {
            return;
        }

        $id = (int)$m[1];


        /*
         * Adatbázis plugin
         */
        $db = $context
            ->plugins()
            ->get('portfolio-database')
            ->instance;

        if (!$db instanceof PortfolioDatabasePlugin) {
            return;
        }


        /*
         * Projekt lekérése
         */
        $p = $db->projects()->find($id);

        if (!$p) {
            http_response_code(404);

            $event->title('404');

            $event->addSection(
                '<nav class="portfolio-nav">'
                .'<a href="'.$base.'/">Főoldal</a>'
                .'</nav>'

                .'<section class="portfolio-wrap">'
                .'<h1>Projekt nem található.</h1>'
                .'</section>'
            );

            return;
        }


        /*
         * Escape helper
         */
        $e = static fn ($v): string =>
            htmlspecialchars(
                (string)$v,
                ENT_QUOTES | ENT_SUBSTITUTE,
                'UTF-8'
            );


        /*
         * =========================================================
         * PROJEKT MEGJELENÉSI BEÁLLÍTÁSOK
         * =========================================================
         *
         * Közös JSON fájl:
         *
         * data/project-settings.json
         *
         * Példa:
         *
         * {
         *     "1": {
         *         "icon": "fa-solid fa-code",
         *         "hatter": "/images/bg.jpg"
         *     }
         * }
         */

        $settingsFile = __DIR__ . '/../../portfolio-admin/data/project-settings.json';

        $projectSettings = [];

        if (file_exists($settingsFile)) {

            $allSettings = json_decode(
                file_get_contents($settingsFile),
                true
            );

            if (is_array($allSettings)) {
                $projectSettings = $allSettings[(string)$id] ?? [];
            }
        }


        /*
         * Ha nincs beállítva, üres marad
         */
        $icon = trim(
            (string)($projectSettings['icon'] ?? '')
        );

        $hatter = trim(
            (string)($projectSettings['hatter'] ?? '')
        );


        /*
         * =========================================================
         * LINK-EK
         * =========================================================
         */

        $links = '';

        foreach ($db->links()->forProject($id) as $l) {

            $type = (string)$l['type'];
            $url = $e($l['url']);
            $title = $e($l['title']);


            /*
             * KÉP
             */
            if ($type === 'img') {

                $links .=
                    '<div class="portfolio-image">'

                    .'<img
                        src="'.$url.'"
                        alt="'.$title.'"
                        style="max-width:1000px; max-height:1000px;"
                    >'

                    .'<a
                        class="portfolio-button"
                        target="_blank"
                        rel="noopener"
                        href="'.$url.'"
                    >'
                    .'🔍 '
                    .$title
                    .'</a>'

                    .'</div>';

            }


            /*
             * VIDEÓ
             */
            else if ($type === 'video') {

                $links .=
                    '<div class="portfolio-video">'

                    .'<iframe
                        src="'.$url.'"
                        style="max-width:1000px; max-height:1000px;"
                    ></iframe>'

                    .'<a
                        class="portfolio-button"
                        target="_blank"
                        rel="noopener"
                        href="'.$url.'"
                    >'
                    .'Link'
                    .'</a>'

                    .'</div>';
            }


            /*
             * GIT
             */
            else if ($type === 'git') {

                $links .=
                    '<a
                        class="portfolio-button"
                        target="_blank"
                        rel="noopener"
                        href="'.$url.'"
                    >'

                    .'<span>'
                    .$title
                    .'</span>'

                    .'<img
                        src="https://upload.wikimedia.org/wikipedia/commons/e/e0/Git-logo.svg"
                        alt="Git"
                        style="max-width:100px; max-height:100px;"
                    >'

                    .'</a>';
            }


            /*
             * EGYÉB LINK
             */
            else {

                $links .=
                    '<a
                        class="portfolio-button"
                        target="_blank"
                        rel="noopener"
                        href="'.$url.'"
                    >'
                    .$title
                    .' ('.$e($type).')'
                    .'</a>';
            }
        }


        /*
         * =========================================================
         * PROJEKT OLDAL
         * =========================================================
         */

        $event->title(
            $p['title'].' — Projekt'
        );


        /*
         * Háttér stílus
         *
         * Csak akkor kerül bele, ha van beállított háttér.
         */
        $backgroundStyle = '';

        if ($hatter !== '') {

            $backgroundStyle =
                ' style="'
                .'background-image:url(\''.$e($hatter).'\');'
                .'background-size:cover;'
                .'background-position:center;'
                .'background-repeat:no-repeat;'
                .' width: 100%;'
                .' height: 400px;'
                .' position: absolute;'
                .'z-index: -1;'
                .' left: 0;'
                .' top: 0;'
                .'"';
        }


        /*
         * Icon
         *
         * Csak akkor jelenik meg, ha van beállítva.
         */
        $iconHtml = '';

        if ($icon !== '') {

            $iconHtml =
                '<img src="'.$e($icon).'" class="portfolio-avatar-small" alt="Icon"> ';
        }


        /*
         * =========================================================
         * HTML
         * =========================================================
         */

        $event->addSection(

            /*
             * NAV
             */
            '<nav class="portfolio-nav">'

                .'<a href="'.$base.'/">'
                    .'Főoldal'
                .'</a>'

                .'<a href="'.$base.'/u/'.$p['user_id'].'">'
                    .'← Portfolio'
                .'</a>'

                .'<a href="'.$base.'/admin">'
                    .'Admin'
                .'</a>'

            .'</nav>'


            /*
             * PROJECT WRAPPER
             */
            .'<section class="portfolio-wrap">'


                /*
                 * Vissza
                 */
                .'<p>'
                    .'<a href="'.$base.'/u/'.$p['user_id'].'">'
                        .'← Vissza a portfoliohoz'
                    .'</a>'
                .'</p>'

                .'<div'
                .$backgroundStyle
            .'></div>'
                /*
                 * Projekt címe + icon
                 */
                .'<h1 '.($backgroundStyle=="" ? '' : 'class="h_tittle"').'>'
                    .$iconHtml
                    .$e($p['title'])
                .'</h1>'


                /*
                 * Leírás
                 */
                .'<div class="portfolio-card">'

                    .'<p>'
                        .nl2br(
                            $e($p['description'])
                        )
                    .'</p>'

                .'</div>'


                /*
                 * Linkek
                 */
                .'<div class="portfolio-links">'
                    .$links
                .'</div>'


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
