<?php
declare(strict_types=1);

namespace PortfolioProjectRating;

use Engine\Contracts\PluginInterface;
use Engine\Core\PluginContext;
use Engine\Rendering\RenderEvent;
use PortfolioDatabase\PortfolioDatabasePlugin;

final class PortfolioProjectRatingPlugin implements PluginInterface
{
    private ?ProjectRatingRepository $repository = null;

    public function getName(): string
    {
        return 'portfolio-project-rating';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function register(PluginContext $context): void
    {
        require_once __DIR__ . '/ProjectRatingRepository.php';

        $context->listen(RenderEvent::class, function (RenderEvent $event) use ($context): void {
            $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
            $base = $this->basePath();

            $relative = '/' . ltrim(
                substr($path, strlen($base)),
                '/'
            );

            if (!preg_match('#^/project/(\d+)/?$#', $relative, $m)) {
                return;
            }

            $projectId = (int) $m[1];
            if ($projectId < 1) {
                return;
            }

            $db = $context->plugins()->get('portfolio-database')->instance;
            if (!$db instanceof PortfolioDatabasePlugin) {
                return;
            }

            $project = $db->projects()->find($projectId);
            if (!$project) {
                return;
            }

            $this->repository ??= new ProjectRatingRepository($db->pdo());
            $this->repository->ensureTable();

            if (session_status() !== PHP_SESSION_ACTIVE) {
                @session_start();
            }

            $loggedInUserId = (int) ($_SESSION['portfolio_user_id'] ?? 0);
            $ownerUserId = (int) ($project['user_id'] ?? 0);

            $error = null;
            $success = false;

            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['project_rating_submit'])) {
                if ($loggedInUserId < 1) {
                    $error = 'Az értékeléshez be kell jelentkezned.';
                } elseif ($ownerUserId === $loggedInUserId) {
                    $error = 'A saját projektedet nem értékelheted.';
                } elseif (!hash_equals(
                    (string) ($_SESSION['portfolio_project_rating_csrf'] ?? ''),
                    (string) ($_POST['csrf_token'] ?? '')
                )) {
                    $error = 'Érvénytelen vagy lejárt űrlap.';
                } elseif ($this->repository->hasRated($projectId, $loggedInUserId)) {
                    $error = 'Ezt a projektet már értékelted.';
                } else {
                    $rating = filter_var($_POST['rating'] ?? null, FILTER_VALIDATE_INT);
                    $comment = trim((string) ($_POST['comment'] ?? ''));

                    if ($rating === false || $rating < 1 || $rating > 5) {
                        $error = 'Kérlek, adj 1 és 5 közötti értékelést.';
                    } elseif (mb_strlen($comment) > 2000) {
                        $error = 'Az értékelés szövege legfeljebb 2000 karakter lehet.';
                    } else {
                        try {
                            $this->repository->create(
                                $projectId,
                                $loggedInUserId,
                                (int) $rating,
                                $comment
                            );

                            $success = true;
                        } catch (\PDOException $e) {
                            // A UNIQUE(project_id, user_id) védelem akkor is megakadályozza
                            // a dupla értékelést, ha két kérés egyszerre érkezik.
                            if ((string) $e->getCode() === '23000') {
                                $error = 'Ezt a projektet már értékelted.';
                            } else {
                                $error = 'Az értékelést nem sikerült elmenteni. Próbáld újra.';
                            }
                        }
                    }
                }
            }

            $summary = $this->repository->summary($projectId);
            $ratings = $this->repository->forProject($projectId);

            $event->addSection(
                $this->render(
                    $base,
                    $projectId,
                    $ownerUserId,
                    $loggedInUserId,
                    $summary,
                    $ratings,
                    $error,
                    $success
                )
            );
        });
    }

    public function boot(PluginContext $context): void {}

    public function shutdown(PluginContext $context): void {}

    private function render(
        string $base,
        int $projectId,
        int $ownerUserId,
        int $loggedInUserId,
        array $summary,
        array $ratings,
        ?string $error,
        bool $success
    ): string {
        $e = static fn ($value): string => htmlspecialchars(
            (string) $value,
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );

        $average = (float) $summary['average'];
        $total = (int) $summary['total'];
        $averageText = $total > 0 ? number_format($average, 1, ',', '') : '–';

        $stars = '';
        for ($i = 1; $i <= 5; $i++) {
            $stars .= $i <= round($average) ? '★' : '☆';
        }

        $html = '<section class="portfolio-project-rating">'
            . '<h2>Projekt értékelése</h2>'
            . '<div class="project-rating-summary">'
            . '<span class="project-rating-stars" aria-label="Átlagos értékelés: ' . $e($averageText) . ' az 5-ből">'
            . $stars
            . '</span>'
            . '<strong>' . $e($averageText) . '/5</strong>'
            . '<span>(' . $e($total) . ' értékelés)</span>'
            . '</div>';

        if ($success) {
            $html .= '<div class="project-rating-success">Köszönjük! Az értékelésed sikeresen mentve.</div>';
        }

        if ($error !== null) {
            $html .= '<div class="project-rating-error">' . $e($error) . '</div>';
        }

        if ($loggedInUserId > 0 && $ownerUserId !== $loggedInUserId) {
            $alreadyRated = $this->repository?->hasRated($projectId, $loggedInUserId) ?? false;

            if (!$alreadyRated && !$success) {
                $token = $this->csrfToken();

                $html .= '<form method="post" class="project-rating-form">'
                    . '<input type="hidden" name="csrf_token" value="' . $e($token) . '">'
                    . '<input type="hidden" name="project_rating_submit" value="1">'
                    . '<label for="project-rating-value">Értékelés</label>'
                    . '<select id="project-rating-value" name="rating" required>'
                    . '<option value="">Válassz…</option>'
                    . '<option value="5">★★★★★ – Kiváló</option>'
                    . '<option value="4">★★★★☆ – Nagyon jó</option>'
                    . '<option value="3">★★★☆☆ – Jó</option>'
                    . '<option value="2">★★☆☆☆ – Gyenge</option>'
                    . '<option value="1">★☆☆☆☆ – Nagyon gyenge</option>'
                    . '</select>'
                    . '<label for="project-rating-comment">Megjegyzés <span>(opcionális)</span></label>'
                    . '<textarea id="project-rating-comment" name="comment" maxlength="2000" rows="5" placeholder="Írd le röviden a véleményed…"></textarea>'
                    . '<button type="submit">Értékelés elküldése</button>'
                    . '</form>';
            } elseif ($alreadyRated || $success) {
                $html .= '<p class="project-rating-info">Ezt a projektet már értékelted.</p>';
            }
        } elseif ($loggedInUserId === $ownerUserId && $loggedInUserId > 0) {
            $html .= '<p class="project-rating-info">A saját projektedet nem értékelheted.</p>';
        } elseif ($loggedInUserId < 1) {
            $html .= '<p class="project-rating-info">Az értékeléshez be kell jelentkezned.</p>';
        }

        if ($ratings !== []) {
            $html .= '<div class="project-rating-list"><h3>Értékelések</h3>';

            foreach ($ratings as $rating) {
                $value = (int) $rating['rating'];
                $userName = trim((string) ($rating['user_name'] ?? 'Felhasználó')) ?: 'Felhasználó';
                $comment = trim((string) ($rating['comment'] ?? ''));
                $date = (string) ($rating['created_at'] ?? '');

                $itemStars = str_repeat('★', $value) . str_repeat('☆', 5 - $value);

                $html .= '<article class="project-rating-item">'
                    . '<div class="project-rating-item-head">'
                    . '<strong>' . $e($userName) . '</strong>'
                    . '<span class="project-rating-stars">' . $itemStars . '</span>'
                    . '</div>';

                if ($comment !== '') {
                    $html .= '<p>' . nl2br($e($comment)) . '</p>';
                }

                if ($date !== '') {
                    $html .= '<small>' . $e($date) . '</small>';
                }

                $html .= '</article>';
            }

            $html .= '</div>';
        }

        return $html . '</section>';
    }

    private function csrfToken(): string
    {
        if (empty($_SESSION['portfolio_project_rating_csrf'])) {
            $_SESSION['portfolio_project_rating_csrf'] = bin2hex(random_bytes(32));
        }

        return (string) $_SESSION['portfolio_project_rating_csrf'];
    }

    private function basePath(): string
    {
        return rtrim(
            str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')),
            '/'
        );
    }
}
