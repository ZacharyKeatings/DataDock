<?php
/**
 * Shared database bootstrap for web install.php and CLI scripts/datadock-install.php.
 */

/**
 * Default values for the install form. Uses config/.db-runtime.php when present (Docker entrypoint),
 * since Apache mod_php often does not expose DATADOCK_DB_* from the environment.
 *
 * @return array{host: string, user: string, pass: string, name: string}
 */
function install_db_form_prefill(): array {
    $defaults = [
        'host' => 'localhost',
        'user' => '',
        'pass' => '',
        'name' => 'file_upload_site',
    ];

    $runtime = __DIR__ . '/../config/.db-runtime.php';
    if (is_readable($runtime)) {
        $host = null;
        $dbname = null;
        $user = null;
        $pass = null;
        require $runtime;

        return [
            'host' => $host ?? $defaults['host'],
            'user' => $user ?? $defaults['user'],
            'pass' => $pass ?? $defaults['pass'],
            'name' => $dbname ?? $defaults['name'],
        ];
    }

    if (($h = getenv('DATADOCK_DB_HOST')) !== false && $h !== '') {
        $defaults['host'] = $h;
    }
    if (($n = getenv('DATADOCK_DB_NAME')) !== false && $n !== '') {
        $defaults['name'] = $n;
    }
    if (($u = getenv('DATADOCK_DB_USER')) !== false && $u !== '') {
        $defaults['user'] = $u;
    }
    if (($p = getenv('DATADOCK_DB_PASSWORD')) !== false) {
        $defaults['pass'] = $p;
    }

    return $defaults;
}

function install_database($host, $user, $pass, $dbname) {
    try {
        $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `$dbname`");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(50) NOT NULL UNIQUE,
                email VARCHAR(100) NOT NULL,
                password_hash VARCHAR(255) NOT NULL,
                role ENUM('user', 'admin') DEFAULT 'user',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS files (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT DEFAULT NULL,
                guest_id VARCHAR(64) DEFAULT NULL,
                is_public TINYINT(1) NOT NULL DEFAULT 0,
                filename VARCHAR(255) NOT NULL,
                original_name VARCHAR(255),
                description VARCHAR(500) DEFAULT NULL,
                access_password_hash VARCHAR(255) DEFAULT NULL,
                ip_allowlist TEXT DEFAULT NULL,
                filetype VARCHAR(100),
                filesize INT,
                thumbnail_path VARCHAR(255),
                upload_date DATETIME DEFAULT CURRENT_TIMESTAMP,
                expiry_date DATETIME,
                download_count INT NOT NULL DEFAULT 0,
                checksum_md5 VARCHAR(32) DEFAULT NULL,
                checksum_sha256 VARCHAR(64) DEFAULT NULL,
                quarantine_status ENUM('pending','approved') NOT NULL DEFAULT 'approved',
                mime_anomaly TINYINT(1) NOT NULL DEFAULT 0,
                deleted_at DATETIME DEFAULT NULL,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                INDEX idx_files_deleted_at (deleted_at)
            )
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS download_tokens (
                id INT AUTO_INCREMENT PRIMARY KEY,
                file_id INT NOT NULL,
                token VARCHAR(64) NOT NULL UNIQUE,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                expires_at DATETIME DEFAULT NULL,
                max_uses INT NOT NULL DEFAULT 1,
                use_count INT NOT NULL DEFAULT 0,
                FOREIGN KEY (file_id) REFERENCES files(id) ON DELETE CASCADE,
                INDEX idx_token (token)
            )
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS app_secrets (
                secret_key VARCHAR(64) NOT NULL PRIMARY KEY,
                secret_value VARCHAR(255) NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");

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

        $pdo->exec("CREATE INDEX idx_guest_id ON files(guest_id)");

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

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS login_attempts (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT DEFAULT NULL,
                anon_id VARCHAR(64) DEFAULT NULL,
                ip_address VARCHAR(45) DEFAULT NULL,
                success TINYINT(1) NOT NULL DEFAULT 0,
                attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                INDEX idx_login_attempts_ip (ip_address, attempted_at)
            );
        ");
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

        return true;
    } catch (PDOException $e) {
        return $e->getMessage();
    }
}

function create_db_config_file($host, $user, $pass, $dbname) {
    $config = "<?php
\$host = '$host';
\$dbname = '$dbname';
\$user = '$user';
\$pass = '$pass';

try {
    \$pdo = new PDO(\"mysql:host=\$host;dbname=\$dbname;charset=utf8mb4\", \$user, \$pass);
    \$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException \$e) {
    die(\"Database connection failed: \" . \$e->getMessage());
}
?>";

    if (!is_dir(__DIR__ . '/../config')) {
        mkdir(__DIR__ . '/../config', 0755, true);
    }

    file_put_contents(__DIR__ . '/../config/db.php', $config);
}

function secure_config_folder() {
    $htaccessContent = "Order deny,allow\nDeny from all";
    file_put_contents(__DIR__ . '/../config/.htaccess', $htaccessContent);
}
