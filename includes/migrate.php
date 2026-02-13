<?php
/**
 * Database migrations for schema upgrades.
 * Run after PDO connection is established.
 */
function run_migrations(PDO $pdo): void {
    // v1.4.1: download_count, checksum_md5, checksum_sha256 on files
    $cols = [];
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM files LIKE 'download_count'");
        if ($stmt->rowCount() === 0) {
            $pdo->exec("ALTER TABLE files ADD COLUMN download_count INT NOT NULL DEFAULT 0 AFTER expiry_date");
        }
    } catch (PDOException $e) {
        // Table might not exist yet (fresh install)
    }
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM files LIKE 'checksum_md5'");
        if ($stmt->rowCount() === 0) {
            $pdo->exec("ALTER TABLE files ADD COLUMN checksum_md5 VARCHAR(32) DEFAULT NULL AFTER download_count");
        }
    } catch (PDOException $e) {}
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM files LIKE 'checksum_sha256'");
        if ($stmt->rowCount() === 0) {
            $pdo->exec("ALTER TABLE files ADD COLUMN checksum_sha256 VARCHAR(64) DEFAULT NULL AFTER checksum_md5");
        }
    } catch (PDOException $e) {}

    // v1.4.1: download_tokens for one-time links
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS download_tokens (
                id INT AUTO_INCREMENT PRIMARY KEY,
                file_id INT NOT NULL,
                token VARCHAR(64) NOT NULL UNIQUE,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (file_id) REFERENCES files(id) ON DELETE CASCADE,
                INDEX idx_token (token)
            )
        ");
    } catch (PDOException $e) {
        // Ignore if exists
    }
}
