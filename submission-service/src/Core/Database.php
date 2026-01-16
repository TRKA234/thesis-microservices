<?php

namespace App\Core;

use PDO;
use PDOException;

class Database
{
    private static ?Database $instance = null;
    private ?PDO $connection = null;

    private function __construct()
    {
        $this->connect();
    }

    public static function getInstance(): Database
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function connect(): void
    {
        $host = $_ENV['DB_HOST'];
        $port = $_ENV['DB_PORT'];
        $dbname = $_ENV['DB_NAME'];
        $username = $_ENV['DB_USER'];
        $password = $_ENV['DB_PASSWORD'];

        $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";

        $maxRetries = 30;
        $attempt = 0;

        while ($attempt < $maxRetries) {
            try {
                $this->connection = new PDO($dsn, $username, $password, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]);

                error_log("Successfully connected to database");
                $this->migrate();
                return;
            } catch (PDOException $e) {
                $attempt++;
                error_log("Failed to connect to database (attempt {$attempt}/{$maxRetries}): " . $e->getMessage());

                if ($attempt >= $maxRetries) {
                    throw new PDOException("Could not connect to database after {$maxRetries} attempts");
                }

                sleep(1);
            }
        }
    }

    public function getConnection(): PDO
    {
        return $this->connection;
    }

    private function migrate(): void
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS submissions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                ticket_number VARCHAR(50) UNIQUE NOT NULL,
                identity_number VARCHAR(50) NOT NULL,
                title VARCHAR(255) NOT NULL,
                abstract TEXT,
                status ENUM('pengajuan', 'bimbingan', 'revisi', 'sidang', 'lulus') DEFAULT 'pengajuan',
                total_progress INT DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_identity (identity_number),
                INDEX idx_ticket (ticket_number)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

            CREATE TABLE IF NOT EXISTS milestones (
                id INT AUTO_INCREMENT PRIMARY KEY,
                submission_id INT NOT NULL,
                milestone_name VARCHAR(100) NOT NULL,
                status ENUM('pending', 'progress', 'acc', 'revision') DEFAULT 'pending',
                notes TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (submission_id) REFERENCES submissions(id) ON DELETE CASCADE,
                INDEX idx_submission (submission_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";

        $this->connection->exec($sql);
        error_log("Database migration completed");
    }
}
