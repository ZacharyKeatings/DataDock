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

    // v1.6.0: is_public on files
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM files LIKE 'is_public'");
        if ($stmt->rowCount() === 0) {
            $pdo->exec("ALTER TABLE files ADD COLUMN is_public TINYINT(1) NOT NULL DEFAULT 0 AFTER guest_id");
        }
    } catch (PDOException $e) {}

    // v1.6.0: file_shares for user-to-user sharing
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS file_shares (
                id INT AUTO_INCREMENT PRIMARY KEY,
                file_id INT NOT NULL,
                shared_with_user_id INT NOT NULL,
                shared_by_user_id INT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (file_id) REFERENCES files(id) ON DELETE CASCADE,
                FOREIGN KEY (shared_with_user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (shared_by_user_id) REFERENCES users(id) ON DELETE CASCADE,
                UNIQUE KEY unique_share (file_id, shared_with_user_id),
                INDEX idx_shared_with (shared_with_user_id)
            )
        ");
    } catch (PDOException $e) {
        // Ignore if exists
    }

    // v1.7.0: users.display_name for profile
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'display_name'");
        if ($stmt->rowCount() === 0) {
            $pdo->exec("ALTER TABLE users ADD COLUMN display_name VARCHAR(100) DEFAULT NULL AFTER email");
        }
    } catch (PDOException $e) {}

    // v1.7.0: signup_tokens for invite-only registration
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS signup_tokens (
                id INT AUTO_INCREMENT PRIMARY KEY,
                token VARCHAR(64) NOT NULL UNIQUE,
                created_by_user_id INT NOT NULL,
                expires_at DATETIME NOT NULL,
                used_at DATETIME DEFAULT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE CASCADE,
                INDEX idx_token (token),
                INDEX idx_expires (expires_at)
            )
        ");
    } catch (PDOException $e) {}

    // v1.7.0: users.avatar and users.bio for profile
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'avatar'");
        if ($stmt->rowCount() === 0) {
            $pdo->exec("ALTER TABLE users ADD COLUMN avatar VARCHAR(500) DEFAULT NULL AFTER display_name");
        }
    } catch (PDOException $e) {}
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'bio'");
        if ($stmt->rowCount() === 0) {
            $pdo->exec("ALTER TABLE users ADD COLUMN bio VARCHAR(500) DEFAULT NULL AFTER avatar");
        }
    } catch (PDOException $e) {}

    // v1.7.0: password_reset_tokens for password reset flow
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS password_reset_tokens (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                token VARCHAR(64) NOT NULL UNIQUE,
                expires_at DATETIME NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                INDEX idx_token (token),
                INDEX idx_expires (expires_at)
            )
        ");
    } catch (PDOException $e) {}
}
