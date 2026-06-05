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

# Bind-mounted /workspace is often not writable by www-data on Windows/macOS Docker Desktop.
# php-fpm refuses root; entrypoint (root) chmods project trees in the background so FPM can start immediately.
if [ "${BOSSKU_WORKSPACE_WRITABLE:-true}" = "true" ] && [ -d /workspace ]; then
  (
    for proj in /workspace/*/; do
      [ -d "$proj" ] || continue
      find "$proj" -maxdepth 12 \
        \( -path '*/node_modules' -o -path '*/node_modules/*' \
        -o -path '*/vendor' -o -path '*/vendor/*' \
        -o -path '*/.git' -o -path '*/.git/*' \) -prune \
        -o -exec chmod a+rwX {} + 2>/dev/null || true
    done
    echo "[bossku] /workspace permission sync complete"
  ) &
  echo "[bossku] Syncing /workspace permissions for www-data (background)..."
fi

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

  # Wait for Postgres to be ready (up to 60s); fail fast so migrate/seed do not run on a dead DB
  echo "[bossku] Waiting for database..."
  ready=0
  for i in $(seq 1 60); do
    if pg_isready -h postgres -U bossku -d bossku -q 2>/dev/null; then
      ready=1
      break
    fi
    sleep 1
  done
  if [ "$ready" != "1" ]; then
    echo "[bossku] ERROR: postgres is not reachable on the Docker network. Start postgres: docker compose up -d postgres"
    exit 1
  fi

  # Run migrations every boot. This is idempotent and NON-destructive: it applies only the
  # *pending* migrations and never drops, resets, or empties tables. It is `migrate`, never
  # `migrate:fresh`/`migrate:refresh`, so existing runs, conversations and memory are kept.
  echo "[bossku] Running migrations..."
  php artisan migrate --force --no-interaction

  # First-boot initialization: import markdown knowledge, seed spec/demo data, sync agent
  # personas, and bootstrap the soul. On an already-initialized database these steps are
  # SKIPPED so a normal restart never re-seeds or re-imports — your existing data is left
  # untouched. Re-run them explicitly (e.g. after editing skills/rules/personas markdown)
  # by setting BOSSKU_FORCE_REINIT=true.
  skills_exist="$(PGPASSWORD="${DB_PASSWORD:-bossku}" psql -h "${DB_HOST:-postgres}" -U "${DB_USERNAME:-bossku}" -d "${DB_DATABASE:-bossku}" -tAc 'SELECT EXISTS (SELECT 1 FROM bossku_ai_skills)' 2>/dev/null | tr -d '[:space:]')"
  if [ "${BOSSKU_FORCE_REINIT:-false}" = "true" ] || [ "$skills_exist" != "t" ]; then
    echo "[bossku] Initializing knowledge base (first boot or BOSSKU_FORCE_REINIT=true)..."

    # Import all skills, rules, playbooks, checklists from /repo
    echo "[bossku] Importing BosskuAI knowledge (skills, rules, playbooks, checklists)..."
    php artisan bosskuai:import-knowledge --no-interaction || echo "[bossku] Knowledge import skipped or partial (non-fatal)"

    # Seed spec demo data (idempotent — errors logged to storage/logs/laravel.log)
    echo "[bossku] Seeding spec data..."
    php artisan db:seed --class=BosskuAiSpecSeeder --force --no-interaction

    # Sync pipeline agent personas from agents/*.md → DB.
    # Safe by default: migrates legacy/stale rows and pulls in .md changes, but preserves
    # personas a user edited in the UI. Set BOSSKU_FORCE_PERSONA_SYNC=true to overwrite those too.
    echo "[bossku] Syncing agent personas from agents/*.md..."
    if [ "${BOSSKU_FORCE_PERSONA_SYNC:-false}" = "true" ]; then
      php artisan bosskuai:sync-personas --force --no-interaction || echo "[bossku] Persona sync skipped (non-fatal)"
    else
      php artisan bosskuai:sync-personas --no-interaction || echo "[bossku] Persona sync skipped (non-fatal)"
    fi

    # Bootstrap soul.md into soul_versions table
    echo "[bossku] Bootstrapping soul..."
    php artisan bosskuai:soul-bootstrap --no-interaction || echo "[bossku] Soul bootstrap skipped (already exists)"
  else
    echo "[bossku] Existing data detected — skipping knowledge import / seed / persona sync (set BOSSKU_FORCE_REINIT=true to refresh)."
  fi

  # Cache config + routes for performance
  echo "[bossku] Caching config and routes..."
  php artisan config:cache --no-interaction || true
  php artisan route:cache --no-interaction || true

  echo "[bossku] Bootstrap complete. Starting php-fpm..."
  ;;
esac

exec "$@"
