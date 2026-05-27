# Bossku pixel office

Vendored from [zep-us/zep-pixel-agents](https://github.com/zep-us/zep-pixel-agents) (MIT). React canvas office embedded in the Bossku Nuxt app via iframe.

## Zep assets (required for furniture sprites)

Furniture **positions** are in `public/assets/realistic-office-layout.json`. Furniture **sprites** require `furniture-catalog.json` and PNGs from the zep tileset export pipeline.

### Full pipeline

1. Clone [zep-pixel-agents](https://github.com/zep-us/zep-pixel-agents) and purchase/import the [Office Interior Tileset](https://donarg.itch.io/officetileset) (`npm run import-tileset` in zep).
2. From `web/` (Docker `/app`) or `web/pixel-office/`:

```bash
export ZEP_PIXEL_AGENTS_ROOT=/path/to/zep-pixel-agents
npm run export:zep-furniture
npm run sync:zep-assets
npm run build
```

From `web/`, `npm run prepare:pixel-office` runs the same three steps. Docker runs them on `docker compose up` via `docker/node/web-start.sh`.

`sync:zep-assets` copies `characters/`, `floors.png`, `walls.png`, and `furniture/` into `public/assets/` (run `export:zep-furniture` first). Build fails if `furniture-catalog.json` is missing (unless `BOSSKU_PIXEL_OFFICE_SKIP_ASSETS=1`).

### Vendor bundle (Docker-friendly)

Copy a pre-exported `furniture/` tree to `vendor/zep-furniture/` — see [vendor/zep-furniture/README.md](vendor/zep-furniture/README.md).

## Build

```bash
npm ci
npm run sync:zep-assets
npm run build
```

`prebuild` regenerates `default-layout.json` and exports furniture. `postbuild` merges binaries into `../public/pixel-office/assets/` and runs `verify:assets`.

Output: `../public/pixel-office/` (served at `/pixel-office/`).

## Dev

```bash
npm run dev
```

Point `PixelOfficePanel` iframe at the Vite dev URL when iterating on the canvas (optional).
