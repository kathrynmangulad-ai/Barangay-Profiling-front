#!/usr/bin/env bash
# One-command local launcher for the Barangay system on this Mac
# (no XAMPP here: MariaDB runs in Docker, PHP's built-in server serves the app).
#
#   run.sh            start whatever is not already running, then open the browser
#   run.sh --no-open  same, without opening the browser
#   run.sh stop       stop the PHP servers and the database container (data is kept)
#   run.sh status     show what is running
#
# Safe to re-run: every step is skipped when it is already done.

set -u
ROOT="$(cd "$(dirname "$0")/../../.." && pwd)"
PORT=8000
DB_CONTAINER=brgy-db
DB_VOLUME=brgy-db-data
DB_IMAGE=mariadb:10.11
LOG_DIR="${TMPDIR:-/tmp}/brgy-run"
# Docker Desktop's credential helper lives here and is not on PATH by default.
export PATH="/Applications/Docker.app/Contents/Resources/bin:/opt/homebrew/bin:$PATH"

sql()      { docker exec -i "$DB_CONTAINER" mariadb -uroot -h127.0.0.1 "$@"; }
db_ready() { sql -e 'select 1' >/dev/null 2>&1; }
http_up()  { curl -g -s -o /dev/null --max-time 2 "http://$1:$PORT/auth/login.php"; }
php_pids() { lsof -nP -a -c php -iTCP:$PORT -sTCP:LISTEN -t 2>/dev/null; }

wait_for() { # wait_for <seconds> <command...>
  local limit=$1; shift
  local start=$SECONDS
  until "$@"; do
    [ $((SECONDS - start)) -ge "$limit" ] && return 1
    sleep 0.5
  done
}

case "${1:-}" in
  stop)
    pids=$(php_pids); [ -n "$pids" ] && kill $pids && echo "Stopped PHP server(s): $pids"
    docker stop "$DB_CONTAINER" >/dev/null 2>&1 && echo "Stopped database container (data kept)."
    exit 0 ;;
  status)
    echo "PHP listeners on :$PORT -> $(php_pids | tr '\n' ' ')"
    docker ps -a --filter "name=^${DB_CONTAINER}$" --format 'Database: {{.Names}} {{.Status}}' 2>/dev/null \
      || echo "Database: Docker is not running"
    exit 0 ;;
esac

# 1. Docker daemon -----------------------------------------------------------
if ! docker info >/dev/null 2>&1; then
  echo "Starting Docker Desktop..."
  open -ga Docker
  wait_for 120 docker info >/dev/null 2>&1 || { echo "ERROR: Docker did not start."; exit 1; }
fi

# 2. Database container ------------------------------------------------------
if docker inspect "$DB_CONTAINER" >/dev/null 2>&1; then
  docker start "$DB_CONTAINER" >/dev/null
else
  echo "Creating database container..."
  docker run -d --name "$DB_CONTAINER" -p 127.0.0.1:3306:3306 \
    -v "$DB_VOLUME":/var/lib/mysql \
    -e MARIADB_ALLOW_EMPTY_ROOT_PASSWORD=1 -e MARIADB_DATABASE=brgy_system \
    "$DB_IMAGE" >/dev/null || { echo "ERROR: could not create the container."; exit 1; }
fi
# Checked over TCP on purpose: the socket answers during first-boot init, then
# the server restarts, so a socket check reports "ready" too early.
wait_for 90 db_ready || { echo "ERROR: database did not become ready."; exit 1; }

# 3. Schema + seed, only on an empty database --------------------------------
has_users=$(sql -N -e "select count(*) from information_schema.tables where table_schema='brgy_system' and table_name='users'" 2>/dev/null)
if [ "${has_users:-0}" = "0" ]; then
  echo "Importing schema and seed data..."
  sql -e "CREATE DATABASE IF NOT EXISTS brgy_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci" &&
  sql brgy_system < "$ROOT/database/schema.sql" &&
  sql brgy_system < "$ROOT/database/seed.sql" || { echo "ERROR: import failed."; exit 1; }
fi

# 4. PHP servers -------------------------------------------------------------
# Two listeners: `php -S localhost` binds IPv6 only, and a browser that resolves
# localhost to 127.0.0.1 then gets "connection refused".
# APP_BASE_URL is passed as a real env var so the app serves from "/" without
# touching config/.env (real env vars win over the .env file).
mkdir -p "$LOG_DIR"
for addr in 127.0.0.1 '[::1]'; do
  if ! http_up "$addr"; then
    ( cd "$ROOT" && APP_BASE_URL="http://localhost:$PORT" DB_HOST=127.0.0.1 \
        nohup php -S "$addr:$PORT" -t . >"$LOG_DIR/php-${addr//[^0-9a-z.]/}.log" 2>&1 & )
  fi
done
wait_for 15 http_up 127.0.0.1 || { echo "ERROR: PHP server did not start. See $LOG_DIR"; exit 1; }
wait_for 5 http_up '[::1]' || echo "Note: IPv6 listener is not up; use http://127.0.0.1:$PORT/"

echo "Running: http://127.0.0.1:$PORT/   (login: admin / admin123)"
echo "Logs: $LOG_DIR    Stop: $0 stop"
[ "${1:-}" = "--no-open" ] || open "http://127.0.0.1:$PORT/"
