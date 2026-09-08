<?php
declare(strict_types=1);
namespace PortfolioDatabase\Database;
final class Schema {
    public static function ensure(\PDO $pdo): void {
        $sql = [
        "CREATE TABLE IF NOT EXISTS users (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(160) NOT NULL,
            profile_image VARCHAR(500) NULL,
            email VARCHAR(255) NOT NULL UNIQUE,
            phone VARCHAR(50) NULL,
            contact_visible TINYINT(1) NOT NULL DEFAULT 1,
            password_hash VARCHAR(255) NOT NULL,
            is_admin TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS skills (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(120) NOT NULL UNIQUE,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS user_skills (
            user_id BIGINT UNSIGNED NOT NULL,
            skill_id BIGINT UNSIGNED NOT NULL,
            PRIMARY KEY (user_id, skill_id),
            CONSTRAINT fk_user_skills_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT fk_user_skills_skill FOREIGN KEY (skill_id) REFERENCES skills(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS projects (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            title VARCHAR(200) NOT NULL,
            description TEXT NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT fk_projects_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_projects_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS project_links (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            project_id BIGINT UNSIGNED NOT NULL,
            title VARCHAR(160) NOT NULL,
            url VARCHAR(1000) NOT NULL,
            type VARCHAR(50) NOT NULL DEFAULT 'website',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_project_links_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
            INDEX idx_project_links_project (project_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS portfolio_settings (
            user_id BIGINT UNSIGNED NOT NULL,
            setting_key VARCHAR(100) NOT NULL,
            setting_value LONGTEXT NULL,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (user_id, setting_key),
            CONSTRAINT fk_portfolio_settings_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        ];
        foreach($sql as $statement) $pdo->exec($statement);
        $settingsColumns = $pdo->query("SHOW COLUMNS FROM portfolio_settings")->fetchAll();
        $hasUserId = false; foreach($settingsColumns as $c) if(($c['Field']??'')==='user_id') $hasUserId=true;
        if(!$hasUserId) {
            $pdo->exec("CREATE TABLE portfolio_settings_new (user_id BIGINT UNSIGNED NOT NULL, setting_key VARCHAR(100) NOT NULL, setting_value LONGTEXT NULL, updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, PRIMARY KEY(user_id, setting_key), CONSTRAINT fk_portfolio_settings_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            $pdo->exec("DROP TABLE portfolio_settings"); $pdo->exec("RENAME TABLE portfolio_settings_new TO portfolio_settings");
        }
        $columns = $pdo->query("SHOW COLUMNS FROM users LIKE 'is_admin'")->fetch();
        if (!$columns) $pdo->exec("ALTER TABLE users ADD COLUMN is_admin TINYINT(1) NOT NULL DEFAULT 0 AFTER password_hash");
    }
}
