'use strict'

const fs = require('node:fs')
const fsp = require('node:fs/promises')
const path = require('node:path')

// Never copied from the bundle (heavy or rebuilt at runtime).
const SKIP = [
  '.git',
  'desktop',
  'data',
  'app/vendor',
  'web/.nuxt',
  'web/.output',
]

// Preserved across upgrades: if these already exist in the user copy, the
// bundled version must not overwrite them (DB data, secrets, runtime state).
const PRESERVE = [
  'data',
  'app/.env',
  'app/storage',
]

function rel(root, p) {
  return path.relative(root, p).split(path.sep).join('/')
}

function isUnder(relPath, base) {
  return relPath === base || relPath.startsWith(base + '/')
}

/**
 * Copy the bundled stack into the writable user directory, preserving existing
 * data/.env/storage and skipping heavy or rebuilt directories.
 */
async function syncStack(srcRoot, destRoot, onLog) {
  await fsp.mkdir(destRoot, { recursive: true })

  await fsp.cp(srcRoot, destRoot, {
    recursive: true,
    force: true,
    filter: (src) => {
      const r = rel(srcRoot, src)
      if (r === '') return true

      const parts = r.split('/')
      if (parts.includes('node_modules')) return false

      for (const s of SKIP) {
        if (isUnder(r, s)) return false
      }

      for (const p of PRESERVE) {
        if (isUnder(r, p) && fs.existsSync(path.join(destRoot, p))) {
          return false
        }
      }
      return true
    },
  })

  if (onLog) onLog(`Stack synced to ${destRoot}`)
}

module.exports = { syncStack }
