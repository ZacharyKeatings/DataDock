<?php
/**
 * Full site archive: database dump plus each storage partition.
 */

function datadock_full_backup_create(PDO $pdo, string $output): void {
    if (!class_exists('PharData')) {
        throw new RuntimeException('PHP phar support is required to build a full backup.');
    }
    if (is_file($output)) {
        unlink($output);
    }
    ini_set('phar.readonly', '0');
    require_once __DIR__ . '/storage.php';
    $archive = new PharData($output);
    if (!function_exists('datadock_export_database_sql')) {
        require_once __DIR__ . '/backup_export.php';
    }
    $archive->addFromString('database.sql', datadock_export_database_sql($pdo));
    $parts = $pdo->query('SELECT id, name FROM storage_partitions ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    $manifest = ['created_at' => gmdate('c'), 'partitions' => []];
    foreach ($parts as $part) {
        $id = (int) $part['id'];
        $manifest['partitions'][] = ['id' => $id, 'name' => $part['name']];
        foreach (['uploads' => datadock_upload_dir($pdo, $id), 'thumbnails' => datadock_thumbnails_dir($pdo, $id)] as $label => $dir) {
            if (!is_dir($dir)) {
                continue;
            }
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
            );
            $root = rtrim($dir, '/');
            foreach ($iterator as $file) {
                if (!$file->isFile()) {
                    continue;
                }
                $rel = substr($file->getPathname(), strlen($root));
                $archive->addFile($file->getPathname(), 'partitions/' . $id . '/' . $label . $rel);
            }
        }
    }
    $archive->addFromString('manifest.json', json_encode($manifest, JSON_PRETTY_PRINT));
}

/**
 * Replace database rows from the archive and copy partition files back into place.
 */
function datadock_full_backup_restore(PDO $pdo, string $archivePath): void {
    if (!class_exists('PharData')) {
        throw new RuntimeException('PHP phar support is required to restore a full backup.');
    }
    if (!is_readable($archivePath)) {
        throw new RuntimeException('The backup file could not be read.');
    }
    ini_set('phar.readonly', '0');
    require_once __DIR__ . '/storage.php';
    $temp = sys_get_temp_dir() . '/datadock-restore-' . bin2hex(random_bytes(4));
    $copy = $temp . '.tar';
    if (!@mkdir($temp, 0700, true) || !@copy($archivePath, $copy)) {
        @rmdir($temp);
        @unlink($copy);
        throw new RuntimeException('Could not prepare a folder for the backup.');
    }
    try {
        $archive = new PharData($copy);
        $archive->extractTo($temp, null, true);
        $sqlPath = $temp . '/database.sql';
        if (!is_readable($sqlPath)) {
            throw new RuntimeException('That archive does not contain a DataDock database dump.');
        }
        $sql = (string) file_get_contents($sqlPath);
        foreach (datadock_sql_statements($sql) as $statement) {
            $pdo->exec($statement);
        }
        datadock_restore_partition_files($pdo, $temp . '/partitions');
    } finally {
        @unlink($copy);
        if (!function_exists('datadock_remove_tree')) {
            require_once __DIR__ . '/private_storage.php';
        }
        datadock_remove_tree($temp);
    }
}

/**
 * @return list<string>
 */
function datadock_sql_statements(string $sql): array {
    $statements = [];
    $buf = '';
    $quote = false;
    $escape = false;
    $len = strlen($sql);
    for ($i = 0; $i < $len; $i++) {
        $ch = $sql[$i];
        if ($quote) {
            $buf .= $ch;
            if ($escape) {
                $escape = false;
            } elseif ($ch === '\\') {
                $escape = true;
            } elseif ($ch === "'") {
                $quote = false;
            }
            continue;
        }
        if ($ch === "'") {
            $quote = true;
            $buf .= $ch;
            continue;
        }
        if ($ch === ';') {
            $trim = datadock_strip_sql_comments(trim($buf));
            if ($trim !== '') {
                $statements[] = $trim;
            }
            $buf = '';
            continue;
        }
        $buf .= $ch;
    }
    return $statements;
}

function datadock_strip_sql_comments(string $sql): string {
    $kept = [];
    foreach (preg_split("/\n/", $sql) ?: [] as $line) {
        if (preg_match('/^\s*--/', $line)) {
            continue;
        }
        $kept[] = $line;
    }
    return trim(implode("\n", $kept));
}

function datadock_restore_partition_files(PDO $pdo, string $partRoot): void {
    if (!is_dir($partRoot)) {
        return;
    }
    $partReal = realpath($partRoot);
    if ($partReal === false) {
        return;
    }
    foreach (scandir($partRoot) ?: [] as $idName) {
        if (!ctype_digit($idName)) {
            continue;
        }
        $id = (int) $idName;
        datadock_ensure_partition_dirs($pdo, $id);
        foreach (['uploads' => datadock_upload_dir($pdo, $id), 'thumbnails' => datadock_thumbnails_dir($pdo, $id)] as $label => $destDir) {
            $srcDir = $partRoot . '/' . $idName . '/' . $label;
            if (!is_dir($srcDir)) {
                continue;
            }
            $srcReal = realpath($srcDir);
            if ($srcReal === false || !str_starts_with($srcReal, $partReal)) {
                continue;
            }
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($srcDir, FilesystemIterator::SKIP_DOTS)
            );
            foreach ($iterator as $file) {
                if (!$file->isFile()) {
                    continue;
                }
                $fileReal = $file->getRealPath();
                if ($fileReal === false || !str_starts_with($fileReal, $srcReal)) {
                    continue;
                }
                $rel = substr($fileReal, strlen($srcReal));
                if (str_contains($rel, '..')) {
                    continue;
                }
                $dest = rtrim($destDir, '/') . $rel;
                $parent = dirname($dest);
                if (!is_dir($parent)) {
                    @mkdir($parent, 0755, true);
                }
                @copy($fileReal, $dest);
            }
        }
    }
}

