<?php
/**
 * Database connection bootstrap. Loads config and runs migrations.
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/migrate.php';
run_migrations($pdo);
