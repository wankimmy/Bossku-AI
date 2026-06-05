'use strict'

const { app } = require('electron')

/**
 * Resolve how the desktop app runs the Bossku stack.
 *
 * - native (default for packaged .exe): portable PHP + Node, no Docker
 * - docker: existing docker compose path (set BOSSKU_DESKTOP_RUNTIME=docker)
 *
 * @returns {'native' | 'docker'}
 */
function resolveRuntimeMode() {
  const forced = String(process.env.BOSSKU_DESKTOP_RUNTIME || '').trim().toLowerCase()
  if (forced === 'docker') {
    return 'docker'
  }
  if (forced === 'native') {
    return 'native'
  }
  // Packaged installer targets end users without Docker.
  if (app.isPackaged) {
    return 'native'
  }
  // Dev: native by default so `npm start` exercises the Hermes-style path.
  return 'native'
}

module.exports = { resolveRuntimeMode }
