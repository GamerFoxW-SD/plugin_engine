<?php
declare(strict_types=1);

namespace PortfolioProjectSkill;

use Engine\Contracts\PluginInterface;
use Engine\Core\PluginContext;
use Engine\Rendering\RenderEvent;
use PortfolioDatabase\PortfolioDatabasePlugin;

final class PortfolioProjectSkillPlugin implements PluginInterface
{
    public function getName(): string { return 'portfolio-project-skill'; }
    public function getVersion(): string { return '1.0.0'; }

    public function register(PluginContext $context): void
    {
        require_once __DIR__ . '/ProjectSkill.php';
        $context->listen(RenderEvent::class, function (RenderEvent $event) use ($context): void {
            $db = $context->plugins()->get('portfolio-database')->instance ?? null;
            if (!$db instanceof PortfolioDatabasePlugin) return;

            $this->ensureTable($db->pdo());
            $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
            $base = $this->basePath();
            $relative = '/' . ltrim(substr($path, strlen($base)), '/');

            if (preg_match('#^/admin/projects/project-skills/?$#', $relative)) {
                $event->title('Projekt skillek');
                $event->addSection($this->adminPage($db, $base));
                return;
            }

            if (preg_match('#^/project/(\d+)/?$#', $relative, $m)) {
                $projectId = (int)$m[1];
                $skills = (new ProjectSkill($db->pdo()))->forProject($projectId);
                if (!$skills) return;
                $e = static fn($v): string => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
                $html = '<section class="portfolio-wrap"><h2>Használt skillek</h2><div class="portfolio-grid">';
                foreach ($skills as $skill) $html .= '<span class="portfolio-card">'.$e($skill['name']).'</span>';
                $html .= '</div></section>';
                $event->addSection($html);
            }
        });
    }

    public function boot(PluginContext $context): void
    {
        $db = $context->plugins()->get('portfolio-database')->instance ?? null;
        if ($db instanceof PortfolioDatabasePlugin) $this->ensureTable($db->pdo());
    }

    public function shutdown(PluginContext $context): void {}

    private function ensureTable(\PDO $pdo): void
    {
        $pdo->exec("CREATE TABLE IF NOT EXISTS project_skills (
            project_id BIGINT UNSIGNED NOT NULL,
            skill_id BIGINT UNSIGNED NOT NULL,
            PRIMARY KEY (project_id, skill_id),
            CONSTRAINT fk_project_skills_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
            CONSTRAINT fk_project_skills_skill FOREIGN KEY (skill_id) REFERENCES skills(id) ON DELETE CASCADE,
            INDEX idx_project_skills_skill (skill_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    private function adminPage(PortfolioDatabasePlugin $db, string $base): string
    {
        $uid = (int)($_SESSION['portfolio_user_id'] ?? 0);
        if ($uid <= 0) return '<section class="admin"><h1>Bejelentkezés szükséges</h1><p><a href="'.$base.'/admin">Bejelentkezés</a></p></section>';

        $model = new ProjectSkill($db->pdo());
        $projectId = (int)($_GET['project_id'] ?? 0);
        $project = $projectId > 0 ? $db->projects()->find($projectId) : null;
        if (!$project || (int)$project['user_id'] !== $uid) {
            $project = null;
            $projectId = 0;
        }

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && $project) {
            $skillId = (int)($_POST['skill_id'] ?? 0);
            $userSkillIds = array_map('intval', array_column($db->userSkills()->forUser($uid), 'id'));
            if ($skillId > 0 && in_array($skillId, $userSkillIds, true)) $model->add($projectId, $skillId);
        }
        if (isset($_GET['remove']) && $project) {
            $skillId = (int)$_GET['remove'];
            $userSkillIds = array_map('intval', array_column($db->userSkills()->forUser($uid), 'id'));
            if (in_array($skillId, $userSkillIds, true)) $model->remove($projectId, $skillId);
        }

        $e = static fn($v): string => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
        $projects = $db->projects()->forUser($uid);
        $projectOptions = '';
        foreach ($projects as $p) {
            $selected = $projectId === (int)$p['id'] ? ' selected' : '';
            $projectOptions .= '<option value="'.$p['id'].'"'.$selected.'>'.$e($p['title']).'</option>';
        }
        if (!$project && $projects) $projectId = (int)$projects[0]['id'];
        if (!$project && $projectId > 0) $project = $db->projects()->find($projectId);

        $skillOptions = '';
        foreach ($db->userSkills()->forUser($uid) as $skill) {
            if ($project && $model->has($projectId, (int)$skill['id'])) continue;
            $skillOptions .= '<option value="'.$skill['id'].'">'.$e($skill['name']).'</option>';
        }

        $assigned = $project ? $model->forProject($projectId) : [];
        $assignedHtml = '';
        foreach ($assigned as $skill) {
            $assignedHtml .= '<li><b>'.$e($skill['name']).'</b> <a href="'.$base.'/admin/projects/project-skills?project_id='.$projectId.'&remove='.$skill['id'].'">Eltávolítás</a></li>';
        }

        return '<section class="admin"><h1>Projekt skillek</h1>'
            .'<p>Csak a saját projektedhez és csak a saját skilljeid közül rendelhetsz.</p>'
            .'<form method="get"><label>Projekt<select name="project_id" onchange="this.form.submit()" required>'.$projectOptions.'</select></label></form>'
            .($project ? '<h2>'.$e($project['title']).'</h2><form method="post"><select name="skill_id" required><option value="">Skill kiválasztása</option>'.$skillOptions.'</select><button>Hozzárendelés</button></form><ul>'.$assignedHtml.'</ul>' : '<p>Előbb hozz létre egy projektet.</p>')
            .'</section>';
    }

    private function basePath(): string
    {
        return rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    }
}
