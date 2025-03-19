<?php

namespace App\Database;

use PDO;
use PDOException;

class Connection {
    private static ?PDO $instance = null;

    public static function getInstance(): PDO {
        if (self::$instance === null) {
            try {
                $config = require __DIR__ . '/../../config/database.php';
                $dsn = "mysql:host={$config['host']};dbname={$config['dbname']};charset={$config['charset']}";
                self::$instance = new PDO($dsn, $config['username'], $config['password'], [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]);
                self::runMigrations();
            } catch (PDOException $e) {
                die("Connection failed: " . $e->getMessage());
            }
        }
        return self::$instance;
    }

    private static function runMigrations(): void {
        $migrationsDir = __DIR__ . '/../../migrations';
        if (!is_dir($migrationsDir)) {
            mkdir($migrationsDir, 0755, true);
        }

        // Create migrations table if it doesn't exist
        self::$instance->exec("
            CREATE TABLE IF NOT EXISTS migrations (
                id INT AUTO_INCREMENT PRIMARY KEY,
                migration_name VARCHAR(255) NOT NULL,
                executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");

        // Get list of executed migrations
        $executed = self::$instance->query("SELECT migration_name FROM migrations")
            ->fetchAll(PDO::FETCH_COLUMN);

        // Get all migration files
        $files = scandir($migrationsDir);
        sort($files); // Ensure migrations run in order

        foreach ($files as $file) {
            if ($file === '.' || $file === '..' || in_array($file, $executed)) {
                continue;
            }

            if (pathinfo($file, PATHINFO_EXTENSION) === 'sql') {
                $migration = file_get_contents($migrationsDir . '/' . $file);
                try {
                    self::$instance->exec($migration);
                    self::$instance->exec("INSERT INTO migrations (migration_name) VALUES ('$file')");
                    echo "✓ Executed migration: $file\n";
                } catch (PDOException $e) {
                    echo "✗ Error in migration $file: " . $e->getMessage() . "\n";
                }
            }
        }
    }
} 