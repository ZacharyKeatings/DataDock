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

    // v1.8.0: login_attempts.ip_address for per-IP adaptive cooldown
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM login_attempts LIKE 'ip_address'");
        if ($stmt->rowCount() === 0) {
            $pdo->exec("ALTER TABLE login_attempts ADD COLUMN ip_address VARCHAR(45) DEFAULT NULL AFTER anon_id");
            $pdo->exec("CREATE INDEX idx_login_attempts_ip ON login_attempts(ip_address, attempted_at)");
        }
    } catch (PDOException $e) {}

    // v1.8.0: upload_rate_log for per-IP and per-user upload throttling
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS upload_rate_log (
                id INT AUTO_INCREMENT PRIMARY KEY,
                ip_address VARCHAR(45) NOT NULL,
                user_id INT DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_upload_rate_ip (ip_address, created_at),
                INDEX idx_upload_rate_user (user_id, created_at)
            )
        ");
    } catch (PDOException $e) {}

    // v1.8.0: files.quarantine_status (pending = invisible until admin approval)
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM files LIKE 'quarantine_status'");
        if ($stmt->rowCount() === 0) {
            $pdo->exec("ALTER TABLE files ADD COLUMN quarantine_status ENUM('pending','approved') NOT NULL DEFAULT 'approved' AFTER checksum_sha256");
        }
    } catch (PDOException $e) {}

    // v1.8.0: files.mime_anomaly (extension vs MIME mismatch flag for admin review)
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM files LIKE 'mime_anomaly'");
        if ($stmt->rowCount() === 0) {
            $pdo->exec("ALTER TABLE files ADD COLUMN mime_anomaly TINYINT(1) NOT NULL DEFAULT 0 AFTER quarantine_status");
        }
    } catch (PDOException $e) {}

    // v1.9.0: files.description for user-editable metadata
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM files LIKE 'description'");
        if ($stmt->rowCount() === 0) {
            $pdo->exec("ALTER TABLE files ADD COLUMN description VARCHAR(500) DEFAULT NULL AFTER original_name");
        }
    } catch (PDOException $e) {}

    // v1.9.0: files.deleted_at for soft delete / trash
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM files LIKE 'deleted_at'");
        if ($stmt->rowCount() === 0) {
            $pdo->exec("ALTER TABLE files ADD COLUMN deleted_at DATETIME DEFAULT NULL AFTER mime_anomaly");
            $pdo->exec("CREATE INDEX idx_files_deleted_at ON files(deleted_at)");
        }
    } catch (PDOException $e) {}

    // v2.0.0: storage partitions
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS storage_partitions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                root_path VARCHAR(500) NOT NULL DEFAULT '',
                is_default TINYINT(1) NOT NULL DEFAULT 0,
                sort_order INT NOT NULL DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");
    } catch (PDOException $e) {}

    try {
        $n = (int) $pdo->query('SELECT COUNT(*) FROM storage_partitions')->fetchColumn();
        if ($n === 0) {
            $pdo->exec("INSERT INTO storage_partitions (id, name, root_path, is_default, sort_order) VALUES (1, 'Default', '', 1, 0)");
        }
    } catch (PDOException $e) {}

    // v2.0.0: users.storage_partition_id
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'storage_partition_id'");
        if ($stmt->rowCount() === 0) {
            $pdo->exec('ALTER TABLE users ADD COLUMN storage_partition_id INT DEFAULT NULL AFTER role');
            $pdo->exec('ALTER TABLE users ADD CONSTRAINT fk_users_storage_partition FOREIGN KEY (storage_partition_id) REFERENCES storage_partitions(id) ON DELETE SET NULL');
        }
    } catch (PDOException $e) {}

    // v2.0.0: folders (before files FK)
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS folders (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                parent_id INT NOT NULL DEFAULT 0,
                name VARCHAR(255) NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                UNIQUE KEY uq_folder (user_id, parent_id, name),
                INDEX idx_folder_parent (user_id, parent_id)
            )
        ");
    } catch (PDOException $e) {}

    // v2.0.0: deduplicated storage objects
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS storage_objects (
                id INT AUTO_INCREMENT PRIMARY KEY,
                storage_partition_id INT NOT NULL,
                sha256 CHAR(64) NOT NULL,
                stored_filename VARCHAR(255) NOT NULL,
                byte_size INT NOT NULL DEFAULT 0,
                ref_count INT NOT NULL DEFAULT 0,
                FOREIGN KEY (storage_partition_id) REFERENCES storage_partitions(id),
                UNIQUE KEY uq_part_sha (storage_partition_id, sha256),
                INDEX idx_so_part (storage_partition_id)
            )
        ");
    } catch (PDOException $e) {}

    // v2.0.0: files.storage_partition_id, folder_id, storage_object_id
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM files LIKE 'storage_partition_id'");
        if ($stmt->rowCount() === 0) {
            $pdo->exec('ALTER TABLE files ADD COLUMN storage_partition_id INT NOT NULL DEFAULT 1 AFTER user_id');
            $pdo->exec('UPDATE files SET storage_partition_id = 1 WHERE storage_partition_id IS NULL OR storage_partition_id = 0');
            $pdo->exec('ALTER TABLE files ADD CONSTRAINT fk_files_storage_partition FOREIGN KEY (storage_partition_id) REFERENCES storage_partitions(id)');
            $pdo->exec('CREATE INDEX idx_files_partition ON files(storage_partition_id)');
        }
    } catch (PDOException $e) {}

    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM files LIKE 'folder_id'");
        if ($stmt->rowCount() === 0) {
            $pdo->exec('ALTER TABLE files ADD COLUMN folder_id INT DEFAULT NULL AFTER storage_partition_id');
            $pdo->exec('ALTER TABLE files ADD CONSTRAINT fk_files_folder FOREIGN KEY (folder_id) REFERENCES folders(id) ON DELETE SET NULL');
            $pdo->exec('CREATE INDEX idx_files_folder ON files(folder_id)');
        }
    } catch (PDOException $e) {}

    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM files LIKE 'storage_object_id'");
        if ($stmt->rowCount() === 0) {
            $pdo->exec('ALTER TABLE files ADD COLUMN storage_object_id INT DEFAULT NULL AFTER filename');
            $pdo->exec('ALTER TABLE files ADD CONSTRAINT fk_files_storage_object FOREIGN KEY (storage_object_id) REFERENCES storage_objects(id)');
            $pdo->exec('CREATE INDEX idx_files_storage_object ON files(storage_object_id)');
        }
    } catch (PDOException $e) {}

    // v2.0.0: tags
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS tags (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                name VARCHAR(100) NOT NULL,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                UNIQUE KEY uq_tag (user_id, name)
            )
        ");
    } catch (PDOException $e) {}

    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS file_tags (
                file_id INT NOT NULL,
                tag_id INT NOT NULL,
                PRIMARY KEY (file_id, tag_id),
                FOREIGN KEY (file_id) REFERENCES files(id) ON DELETE CASCADE,
                FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE
            )
        ");
    } catch (PDOException $e) {}

    // v2.0.1: off-site referer logging (hotlink / embedding awareness)
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS hotlink_log (
                id INT AUTO_INCREMENT PRIMARY KEY,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                resource VARCHAR(32) NOT NULL,
                file_id INT NULL,
                target_user_id INT NULL,
                referer VARCHAR(2048) NOT NULL DEFAULT '',
                referer_host VARCHAR(255) NOT NULL DEFAULT '',
                ip_address VARCHAR(45) NOT NULL DEFAULT '',
                user_agent VARCHAR(512) NOT NULL DEFAULT '',
                INDEX idx_hotlink_created (created_at),
                INDEX idx_hotlink_file (file_id),
                INDEX idx_hotlink_refhost (referer_host)
            )
        ");
    } catch (PDOException $e) {}

    // v2.1.0: activity_log for admin audit trail
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS activity_log (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                action VARCHAR(64) NOT NULL,
                actor_user_id INT DEFAULT NULL,
                actor_guest_id VARCHAR(64) DEFAULT NULL,
                file_id INT DEFAULT NULL,
                related_user_id INT DEFAULT NULL,
                detail_json TEXT DEFAULT NULL,
                ip_address VARCHAR(45) DEFAULT NULL,
                INDEX idx_activity_created (created_at),
                INDEX idx_activity_action (action),
                INDEX idx_activity_actor (actor_user_id),
                INDEX idx_activity_file (file_id)
            )
        ");
    } catch (PDOException $e) {}

    // v2.2.0: file_reports for user reporting and moderation
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS file_reports (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                file_id INT NOT NULL,
                reporter_user_id INT NOT NULL,
                reason VARCHAR(32) NOT NULL,
                details VARCHAR(1000) DEFAULT NULL,
                status ENUM('open','dismissed','actioned') NOT NULL DEFAULT 'open',
                reviewed_by_user_id INT DEFAULT NULL,
                reviewed_at DATETIME DEFAULT NULL,
                action_taken VARCHAR(64) DEFAULT NULL,
                review_note VARCHAR(1000) DEFAULT NULL,
                FOREIGN KEY (file_id) REFERENCES files(id) ON DELETE CASCADE,
                FOREIGN KEY (reporter_user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (reviewed_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
                INDEX idx_reports_created (created_at),
                INDEX idx_reports_file (file_id),
                INDEX idx_reports_reporter (reporter_user_id),
                INDEX idx_reports_status (status)
            )
        ");
    } catch (PDOException $e) {}

    // v2.3.0: stronger access control & user trust
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM files LIKE 'access_password_hash'");
        if ($stmt->rowCount() === 0) {
            $pdo->exec("ALTER TABLE files ADD COLUMN access_password_hash VARCHAR(255) DEFAULT NULL AFTER description");
        }
    } catch (PDOException $e) {}
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM files LIKE 'ip_allowlist'");
        if ($stmt->rowCount() === 0) {
            $pdo->exec("ALTER TABLE files ADD COLUMN ip_allowlist TEXT DEFAULT NULL AFTER access_password_hash");
        }
    } catch (PDOException $e) {}

    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM download_tokens LIKE 'expires_at'");
        if ($stmt->rowCount() === 0) {
            $pdo->exec("ALTER TABLE download_tokens ADD COLUMN expires_at DATETIME DEFAULT NULL AFTER created_at");
            $pdo->exec("ALTER TABLE download_tokens ADD COLUMN max_uses INT NOT NULL DEFAULT 1 AFTER expires_at");
            $pdo->exec("ALTER TABLE download_tokens ADD COLUMN use_count INT NOT NULL DEFAULT 0 AFTER max_uses");
        }
    } catch (PDOException $e) {}

    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS app_secrets (
                secret_key VARCHAR(64) NOT NULL PRIMARY KEY,
                secret_value VARCHAR(255) NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");
    } catch (PDOException $e) {}

    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS file_download_events (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                file_id INT NOT NULL,
                downloaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                ip_address VARCHAR(45) NOT NULL DEFAULT '',
                country_code CHAR(2) DEFAULT NULL,
                INDEX idx_fde_file (file_id),
                INDEX idx_fde_time (downloaded_at),
                FOREIGN KEY (file_id) REFERENCES files(id) ON DELETE CASCADE
            )
        ");
    } catch (PDOException $e) {}

    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS user_storage_snapshots (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                bytes_total BIGINT NOT NULL DEFAULT 0,
                recorded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_uss_user_time (user_id, recorded_at),
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )
        ");
    } catch (PDOException $e) {}

    // v2.4.0: temporary share folders (one link, many files, optional recipient note per file)
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS share_folders (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                token VARCHAR(64) NOT NULL UNIQUE,
                title VARCHAR(200) DEFAULT NULL,
                expires_at DATETIME DEFAULT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                INDEX idx_share_folders_token (token),
                INDEX idx_share_folders_expires (expires_at)
            )
        ");
    } catch (PDOException $e) {}

    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS share_folder_files (
                share_folder_id INT NOT NULL,
                file_id INT NOT NULL,
                recipient_note VARCHAR(500) DEFAULT NULL,
                sort_order INT NOT NULL DEFAULT 0,
                PRIMARY KEY (share_folder_id, file_id),
                FOREIGN KEY (share_folder_id) REFERENCES share_folders(id) ON DELETE CASCADE,
                FOREIGN KEY (file_id) REFERENCES files(id) ON DELETE CASCADE
            )
        ");
    } catch (PDOException $e) {}
}
