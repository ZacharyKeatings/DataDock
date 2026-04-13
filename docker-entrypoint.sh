#!/bin/sh
set -e
# config/db.php is gitignored; ensure a file exists so require_once does not fatal.
if [ ! -f /var/www/html/config/db.php ]; then
  if [ ! -f /var/www/html/config/db.php.example ]; then
    echo "DataDock FATAL: /var/www/html/config/db.php.example is missing (broken image or bind mount hiding config/)." >&2
    exit 1
  fi
  echo "DataDock: config/db.php missing — copying config/db.php.example (edit credentials and DB host for your environment)."
  cp /var/www/html/config/db.php.example /var/www/html/config/db.php
fi
# Old copies of db.php used getenv + localhost; Apache often does not see Compose env, so connection
# used a Unix socket and failed with SQLSTATE 2002. Refresh from the template when Docker DB env is set.
if [ -n "${DATADOCK_DB_HOST:-}" ] && ! grep -q '__dd_rt' /var/www/html/config/db.php 2>/dev/null; then
  if [ -f /var/www/html/config/db.php ]; then
    cp /var/www/html/config/db.php "/var/www/html/config/db.php.pre-docker-sync.$(date +%Y%m%d%H%M%S).bak"
  fi
  cp /var/www/html/config/db.php.example /var/www/html/config/db.php
  echo "DataDock: updated config/db.php from db.php.example (Docker DB env + .db-runtime.php). Check config/ for *.pre-docker-sync.*.bak if you had custom edits."
fi
php /var/www/html/scripts/generate-db-runtime.php
exec "$@"
