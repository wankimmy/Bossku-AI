#!/bin/sh
# Frontend container entry: deps, pixel-office assets (export -> sync -> build), then Nuxt dev or prod.
set -eu

cd /app

export ZEP_PIXEL_AGENTS_ROOT="${ZEP_PIXEL_AGENTS_ROOT:-/workspace/zep-pixel-agents}"
if [ -f /.dockerenv ] && [ -z "${BOSSKU_PIXEL_OFFICE_GRACEFUL:-}" ]; then
  export BOSSKU_PIXEL_OFFICE_GRACEFUL=1
fi

pixel_office_graceful() {
  if [ "${BOSSKU_PIXEL_OFFICE_STRICT:-0}" = "1" ]; then
    return 1
  fi
  if [ "${BOSSKU_PIXEL_OFFICE_SKIP_ASSETS:-0}" = "1" ]; then
    return 0
  fi
  if [ "${BOSSKU_PIXEL_OFFICE_GRACEFUL:-0}" = "1" ]; then
    return 0
  fi
  return 1
}

install_web_deps() {
  if [ ! -f node_modules/@nuxtjs/tailwindcss/package.json ]; then
    echo "[bossku-web] Installing dependencies (dev deps required for nuxt build)..."
    NPM_CONFIG_PRODUCTION=false npm ci 2>/dev/null || NPM_CONFIG_PRODUCTION=false npm install
  fi
}

needs_pixel_office_prepare() {
  if [ "${BOSSKU_AUTO_PIXEL_OFFICE:-true}" != "true" ]; then
    return 1
  fi

  stamp="public/pixel-office/.prepare-stamp"
  catalog="public/pixel-office/assets/furniture/furniture-catalog.json"
  # Require at least one PNG sprite, not catalog JSON alone
  catalog_ok=0
  if [ -f "$catalog" ]; then
    if find public/pixel-office/assets/furniture -name '*.png' 2>/dev/null | head -1 | grep -q .; then
      catalog_ok=1
    fi
  fi
  bundle="public/pixel-office/index.html"

  if [ ! -f "$bundle" ]; then
    return 0
  fi

  # Missing furniture catalog/sprites always triggers prepare (even when SKIP=1).
  if [ "$catalog_ok" != "1" ]; then
    if [ -f "$stamp" ]; then
      echo "[bossku-web] Removing stale prepare stamp (furniture catalog missing)."
      rm -f "$stamp"
    fi
    return 0
  fi

  if [ ! -f "$stamp" ]; then
    return 0
  fi

  for path in pixel-office/src pixel-office/scripts pixel-office/public/assets; do
    if [ -e "$path" ] && find "$path" -type f -newer "$stamp" 2>/dev/null | head -1 | grep -q .; then
      return 0
    fi
  done

  return 1
}

prepare_pixel_office() {
  catalog="public/pixel-office/assets/furniture/furniture-catalog.json"
  echo "[bossku-web] Pixel office: fetch -> export -> sync -> build (ZEP_PIXEL_AGENTS_ROOT=$ZEP_PIXEL_AGENTS_ROOT)"
  if [ "${BOSSKU_AUTO_FETCH_FURNITURE_BUNDLE:-1}" != "0" ]; then
    NPM_CONFIG_PRODUCTION=false npm run fetch:zep-furniture 2>/dev/null || true
  fi
  if ! NPM_CONFIG_PRODUCTION=false npm run export:zep-furniture; then
    if pixel_office_graceful; then
      echo "[bossku-web] export:zep-furniture skipped (graceful mode; no tileset or vendor furniture)."
    else
      echo "[bossku-web] export:zep-furniture failed. Set BOSSKU_PIXEL_OFFICE_SKIP_ASSETS=1 or BOSSKU_PIXEL_OFFICE_GRACEFUL=1."
      exit 1
    fi
  fi
  if ! NPM_CONFIG_PRODUCTION=false npm run sync:zep-assets; then
    if pixel_office_graceful; then
      echo "[bossku-web] sync:zep-assets completed with warnings (graceful mode)."
    else
      exit 1
    fi
  fi
  if ! NPM_CONFIG_PRODUCTION=false npm run build:pixel-office:bundle; then
    if pixel_office_graceful; then
      echo "[bossku-web] build:pixel-office:bundle failed (graceful mode; Nuxt will start without pixel-office bundle)."
    else
      echo "[bossku-web] build:pixel-office:bundle failed. Set BOSSKU_PIXEL_OFFICE_SKIP_ASSETS=1 or BOSSKU_PIXEL_OFFICE_GRACEFUL=1."
      exit 1
    fi
  fi
  mkdir -p public/pixel-office
  catalog_ok=0
  if [ -f "$catalog" ] && find public/pixel-office/assets/furniture -name '*.png' 2>/dev/null | head -1 | grep -q .; then
    catalog_ok=1
    touch public/pixel-office/.prepare-stamp
    export BOSSKU_SKIP_PIXEL_OFFICE_IN_NUXT_BUILD=1
    echo "[bossku-web] Pixel office bundle ready (furniture catalog + PNGs)."
  else
    rm -f public/pixel-office/.prepare-stamp
    echo "[bossku-web] Pixel office built but furniture sprites missing; prepare will retry on next start."
  fi
}

needs_nuxt_build() {
  stamp=".output/server/index.mjs"
  if [ ! -f "$stamp" ]; then
    return 0
  fi
  for path in pages components composables utils layouts middleware plugins assets public pixel-office app.vue nuxt.config.ts; do
    if [ -e "$path" ] && find "$path" -type f -newer "$stamp" 2>/dev/null | head -1 | grep -q .; then
      return 0
    fi
  done
  return 1
}

install_web_deps

if needs_pixel_office_prepare; then
  prepare_pixel_office
else
  echo "[bossku-web] Pixel office prepare skipped (up to date). Delete public/pixel-office/.prepare-stamp to force."
fi

if [ "${BOSSKU_WEB_DEV:-false}" = "true" ]; then
  echo "[bossku-web] Dev mode (nuxt dev) on http://0.0.0.0:${PORT:-3000}"
  exec npm run dev -- --host 0.0.0.0 --port "${PORT:-3000}"
fi

if needs_nuxt_build; then
  echo "[bossku-web] Building Nuxt (source changed or first start; may take a few minutes)..."
  echo "[bossku-web] API proxy target: ${NUXT_API_PROXY_TARGET:-http://nginx/api/**}"
  export NUXT_API_PROXY_TARGET="${NUXT_API_PROXY_TARGET:-http://nginx/api/**}"
  export NUXT_PUBLIC_API_BASE="${NUXT_PUBLIC_API_BASE:-}"
  NPM_CONFIG_PRODUCTION=false npm run build
fi

echo "[bossku-web] Serving production build on http://0.0.0.0:${PORT:-3000}"
exec node .output/server/index.mjs
