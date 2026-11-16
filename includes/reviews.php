<?php
declare(strict_types=1);
// Real customer feedback. No reviews are ever prepopulated or automatically approved.
if (realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) { http_response_code(403); exit; }
require_once __DIR__.'/bootstrap.php';
function opp_review_table(PDO $pdo): void {
    if ((string)config('DB_DRIVER','sqlite')==='mysql') {
        $pdo->exec("CREATE TABLE IF NOT EXISTS customer_reviews (
           id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
           booking_code VARCHAR(32) NULL UNIQUE,
           display_name VARCHAR(55) NOT NULL,
           city VARCHAR(100) NOT NULL,
           rating TINYINT UNSIGNED NOT NULL,
           review_text TEXT NOT NULL,
           status VARCHAR(15) NOT NULL DEFAULT 'pending',
           source VARCHAR(24) NOT NULL DEFAULT 'booking',
           created_at VARCHAR(40) NOT NULL,
           updated_at VARCHAR(40) NOT NULL,
           INDEX customer_review_status_idx (status, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } else {
        $pdo->exec("CREATE TABLE IF NOT EXISTS customer_reviews (
           id INTEGER PRIMARY KEY AUTOINCREMENT,
           booking_code TEXT UNIQUE,
           display_name TEXT NOT NULL,
           city TEXT NOT NULL,
           rating INTEGER NOT NULL,
           review_text TEXT NOT NULL,
           status TEXT NOT NULL DEFAULT 'pending',
           source TEXT NOT NULL DEFAULT 'booking',
           created_at TEXT NOT NULL,
           updated_at TEXT NOT NULL
        )");
        $pdo->exec('CREATE INDEX IF NOT EXISTS customer_review_status_idx ON customer_reviews(status,created_at)');
    }
}
