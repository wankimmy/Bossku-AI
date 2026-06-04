'use strict'

const path = require('node:path')
const { app } = require('electron')

// Host ports must match docker-compose.yml (WEB 28470, API 28480). Overridable via env.
const PORTS = {
  web: Number(process.env.BOSSKU_PORT_WEB || 28470),
  api: Number(process.env.BOSSKU_PORT_API || 28480),
}

const WEB_URL = `http://localhost:${PORTS.web}`
const API_URL = `http://localhost:${PORTS.api}`

// Compose can take a long time on first run because it builds images and the
// frontend container builds Nuxt on startup. Be generous before giving up.
const HEALTH_TIMEOUT_MS = Number(process.env.BOSSKU_HEALTH_TIMEOUT_MS || 20 * 60 * 1000)

/**
 * Where the bundled stack ships.
 * - Packaged: copied via electron-builder extraResources into resources/stack.
 * - Dev: the Bossku-AI repo root (two levels up from desktop/src).
 */
function bundledStackDir() {
  if (app.isPackaged) {
    return path.join(process.resourcesPath, 'stack')
  }
  return path.join(__dirname, '..', '..')
}

/**
 * Writable working copy of the stack.
 * - Packaged: under the OS user-data dir so Program Files stays read-only.
 * - Dev: operate directly in the repo (no copy).
 */
function userStackDir() {
  if (app.isPackaged) {
    return path.join(app.getPath('userData'), 'stack')
  }
  return bundledStackDir()
}

function bootstrapMarkerPath() {
  return path.join(app.getPath('userData'), '.bossku-desktop-bootstrapped')
}

function versionStampPath() {
  return path.join(app.getPath('userData'), '.bossku-desktop-version')
}

module.exports = {
  PORTS,
  WEB_URL,
  API_URL,
  HEALTH_TIMEOUT_MS,
  bundledStackDir,
  userStackDir,
  bootstrapMarkerPath,
  versionStampPath,
}
