<?php
declare(strict_types=1);
namespace PortfolioDatabase;
use Engine\Contracts\PluginInterface;
use Engine\Core\PluginContext;
final class PortfolioDatabasePlugin implements PluginInterface {
    private ?Database\Connection $connection = null;
    public function getName(): string { return 'portfolio-database'; }
    public function getVersion(): string { return '1.2.0'; }
    public function register(PluginContext $context): void {
        foreach (glob(__DIR__.'/Database/*.php') ?: [] as $f) require_once $f;
        foreach (glob(__DIR__.'/Models/*.php') ?: [] as $f) require_once $f;
        foreach (glob(__DIR__.'/Controllers/*.php') ?: [] as $f) require_once $f;
    }
    public function boot(PluginContext $context): void {
        $this->connection = new Database\Connection(require dirname(__DIR__).'/config.php');
        Database\Schema::ensure($this->connection->pdo());
    }
    public function shutdown(PluginContext $context): void { $this->connection = null; }
    public function pdo(): \PDO {
        if (!$this->connection) throw new \RuntimeException('Portfolio database is not active.');
        return $this->connection->pdo();
    }
    public function users(): Models\User { return new Models\User($this->pdo()); }
    public function skills(): Models\Skill { return new Models\Skill($this->pdo()); }
    public function userSkills(): Models\UserSkill { return new Models\UserSkill($this->pdo()); }
    public function projects(): Models\Project { return new Models\Project($this->pdo()); }
    public function links(): Models\ProjectLink { return new Models\ProjectLink($this->pdo()); }
}
