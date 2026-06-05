'use strict'

const fs = require('node:fs')
const fsp = require('node:fs/promises')
const path = require('node:path')

const { composeExec } = require('./docker')
const { bootstrapMarkerPath } = require('./config')

function isBootstrapped() {
  return fs.existsSync(bootstrapMarkerPath())
}

async function markBootstrapped() {
  await fsp.writeFile(bootstrapMarkerPath(), new Date().toISOString(), 'utf8')
}

/**
 * Ensure app/.env exists (copied from app/.env.example on first run).
 */
async function ensureEnv(stackDir, onLog) {
  const envPath = path.join(stackDir, 'app', '.env')
  const examplePath = path.join(stackDir, 'app', '.env.example')
  if (fs.existsSync(envPath)) return
  if (!fs.existsSync(examplePath)) {
    throw new Error(`Missing ${examplePath} — cannot create app/.env`)
  }
  await fsp.copyFile(examplePath, envPath)
  if (onLog) onLog('Created app/.env from app/.env.example')
}

/**
 * First-run database + app bootstrap, mirroring docs/installation.md.
 * Runs inside the backend container; safe to re-run (idempotent-ish).
 */
async function runBootstrap(stackDir, onLog) {
  const steps = [
    { label: 'Installing PHP dependencies', cmd: ['composer', 'install', '--no-interaction', '--no-progress'] },
    { label: 'Generating app key', cmd: ['php', 'artisan', 'key:generate', '--force'] },
    { label: 'Running database migrations', cmd: ['php', 'artisan', 'migrate', '--force'] },
    { label: 'Seeding database', cmd: ['php', 'artisan', 'db:seed', '--force'] },
    { label: 'Importing knowledge base', cmd: ['php', 'artisan', 'bosskuai:import-knowledge', '--fresh'] },
  ]

  for (const step of steps) {
    if (onLog) onLog(`${step.label}...`)
    const { code } = await composeExec(stackDir, 'backend', step.cmd, onLog)
    if (code !== 0) {
      throw new Error(`Bootstrap step failed (${step.label}); exit code ${code}`)
    }
  }

  await markBootstrapped()
  if (onLog) onLog('Bootstrap complete.')
}

module.exports = { isBootstrapped, ensureEnv, runBootstrap }
