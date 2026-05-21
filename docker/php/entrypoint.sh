#!/usr/bin/env sh
set -e

APP=/var/www/html

# ── Storage dirs ──────────────────────────────────────────────────────────────
mkdir -p "$APP/bootstrap/cache" \
         "$APP/storage/framework/cache/data" \
         "$APP/storage/framework/sessions" \
         "$APP/storage/framework/views" \
         "$APP/storage/framework/testing" \
         "$APP/storage/logs" \
         "$APP/storage/app/public" \
         "$APP/storage/app/private"
chmod -R 0777 "$APP/bootstrap/cache" "$APP/storage" 2>/dev/null || true

# ── Only run bootstrap steps when starting php-fpm (not artisan one-offs) ─────
case "$1" in php-fpm*|php*)

  cd "$APP"

  # Install Composer deps if vendor is missing (first boot or fresh clone)
  if [ ! -f vendor/autoload.php ]; then
    echo "[bossku] Installing Composer dependencies..."
    composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev
  fi

  # Generate app key if not set
  if [ -z "$APP_KEY" ] || [ "$APP_KEY" = "base64:" ]; then
    echo "[bossku] Generating application key..."
    php artisan key:generate --force
  fi

  # Wait for Postgres to be ready (up to 30s)
  echo "[bossku] Waiting for database..."
  for i in $(seq 1 30); do
    php artisan db:monitor --databases=pgsql 2>/dev/null && break || true
    sleep 1
  done

  # Run migrations (idempotent)
  echo "[bossku] Running migrations..."
  php artisan migrate --force --no-interaction

  # Import all skills, rules, playbooks, checklists from /repo
  echo "[bossku] Importing BosskuAI knowledge (skills, rules, playbooks, checklists)..."
  php artisan bosskuai:import-knowledge --no-interaction || echo "[bossku] Knowledge import skipped or partial (non-fatal)"

  # Seed spec demo data (skip if already seeded — seeder uses firstOrCreate)
  echo "[bossku] Seeding spec data..."
  php artisan db:seed --class=BosskuAiSpecSeeder --force --no-interaction || echo "[bossku] Seeder skipped (non-fatal)"

  # Bootstrap soul.md into soul_versions table
  echo "[bossku] Bootstrapping soul..."
  php artisan bosskuai:soul-bootstrap --no-interaction || echo "[bossku] Soul bootstrap skipped (already exists)"

  # Cache config + routes for performance
  echo "[bossku] Caching config and routes..."
  php artisan config:cache --no-interaction || true
  php artisan route:cache --no-interaction || true

  echo "[bossku] Bootstrap complete. Starting php-fpm..."
  ;;
esac

exec "$@"
