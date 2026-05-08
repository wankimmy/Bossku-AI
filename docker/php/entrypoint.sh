#!/usr/bin/env sh
set -e
APP=/var/www/html
mkdir -p "$APP/bootstrap/cache" \
        "$APP/storage/framework/cache/data" \
        "$APP/storage/framework/sessions" \
        "$APP/storage/framework/views" \
        "$APP/storage/framework/testing" \
        "$APP/storage/logs" \
        "$APP/storage/app/public" \
        "$APP/storage/app/private"
chmod -R 0777 "$APP/bootstrap/cache" "$APP/storage" 2>/dev/null || true
exec "$@"
