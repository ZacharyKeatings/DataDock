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
# Allow the web installer to write database configuration.
chown www-data:www-data /var/www/html/config
chmod 750 /var/www/html/config

for file in /var/www/html/config/db.php /var/www/html/config/.db-runtime.php; do
  if [ -f "$file" ]; then
    chown www-data:www-data "$file"
    chmod 640 "$file"
  fi
done

# Published image (DATADOCK_IMAGE_MODE=1, no .git): keep settings and uploads on
# DATADOCK_DATA_DIR, and delete install.php once setup has finished. A new container
# does not bring the installer back. Source checkouts set DATADOCK_IMAGE_MODE=0
# or bind-mount a tree that contains .git; those keep install.php in the project.
if [ "${DATADOCK_IMAGE_MODE:-}" = "0" ] || [ -e /var/www/html/.git ]; then
  rm -f /var/www/html/config/.docker.php
  echo "DataDock: source tree is mounted; install.php is left in the project directory."
else
  DATADOCK_DATA_DIR="${DATADOCK_DATA_DIR:-/var/lib/datadock}"
  export DATADOCK_DATA_DIR
  php /var/www/html/scripts/docker-bootstrap.php
  chown www-data:www-data \
    "$DATADOCK_DATA_DIR" \
    "$DATADOCK_DATA_DIR/uploads" \
    "$DATADOCK_DATA_DIR/uploads/avatars" \
    "$DATADOCK_DATA_DIR/thumbnails" \
    /var/www/html/config/.docker.php
  chmod 0750 \
    "$DATADOCK_DATA_DIR" \
    "$DATADOCK_DATA_DIR/uploads" \
    "$DATADOCK_DATA_DIR/uploads/avatars" \
    "$DATADOCK_DATA_DIR/thumbnails"
  chmod 640 /var/www/html/config/.docker.php
  if [ -e "$DATADOCK_DATA_DIR/settings.php" ]; then
    chown www-data:www-data "$DATADOCK_DATA_DIR/settings.php"
    chmod 640 "$DATADOCK_DATA_DIR/settings.php"
  fi
  if [ -L /var/www/html/config/settings.php ]; then
    chown -h www-data:www-data /var/www/html/config/settings.php
  fi
fi
exec "$@"