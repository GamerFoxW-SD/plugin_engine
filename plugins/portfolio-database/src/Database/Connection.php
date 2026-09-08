<?php
declare(strict_types=1);
namespace PortfolioDatabase\Database;
final class Connection {
    private \PDO $pdo;
    public function __construct(array $config) {
        $dsn = sprintf('mysql:host=%s;port=%d;charset=%s',$config['host'],$config['port'],$config['charset']);
        $server = new \PDO($dsn,$config['username'],$config['password'],[
            \PDO::ATTR_ERRMODE=>\PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE=>\PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES=>false,
        ]);
        $db = str_replace('`','``',$config['database']);
        $server->exec("CREATE DATABASE IF NOT EXISTS `{$db}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $this->pdo = new \PDO($dsn . ';dbname=' . $db,$config['username'],$config['password'],[
            \PDO::ATTR_ERRMODE=>\PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE=>\PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES=>false,
        ]);
    }
    public function pdo(): \PDO { return $this->pdo; }
}
