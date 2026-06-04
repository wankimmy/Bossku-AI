'use strict'

const fs = require('node:fs')
const path = require('node:path')

const { run } = require('./docker')
const { bosskuHomeDir, installScriptPath } = require('./config')
const { isBootstrapped, markBootstrapped } = require('./bootstrap')

function stackLooksReady(stackDir) {
  const vendor = path.join(stackDir, 'app', 'vendor', 'autoload.php')
  const nuxt = path.join(stackDir, 'web', '.output', 'server', 'index.mjs')
  const envFile = path.join(stackDir, 'app', '.env')

  return fs.existsSync(vendor) && fs.existsSync(nuxt) && fs.existsSync(envFile)
}

/**
 * Hermes-style first-run: install.ps1 provisions PHP/Node/Git and builds the stack.
 *
 * @param {string} stackDir
 * @param {(line: string) => void} [onLog]
 */
async function runNativeBootstrap(stackDir, onLog) {
  const script = installScriptPath()
  if (!fs.existsSync(script)) {
    throw new Error(`Native install script not found: ${script}`)
  }

  const home = bosskuHomeDir()
  const args = [
    '-NoProfile',
    '-ExecutionPolicy',
    'Bypass',
    '-File',
    script,
    '-StackDir',
    stackDir,
    '-BosskuHome',
    home,
  ]

  if (onLog) onLog('Running native setup (may download PHP, Node, and Git)...')

  const { code } = await run('powershell.exe', args, {
    onLog: (line) => {
      if (line.startsWith('BOSSKU: ')) {
        if (onLog) onLog(line.slice(8))
      } else if (onLog) {
        onLog(line)
      }
    },
  })

  if (code !== 0) {
    throw new Error(`Native setup failed (exit ${code}). See log above.`)
  }

  await markBootstrapped()
  if (onLog) onLog('Native setup complete.')
}

/**
 * @param {string} stackDir
 * @param {(line: string) => void} [onLog]
 */
async function ensureNativeBootstrap(stackDir, onLog) {
  if (isBootstrapped() && stackLooksReady(stackDir)) {
    return
  }
  await runNativeBootstrap(stackDir, onLog)
}

module.exports = { ensureNativeBootstrap, runNativeBootstrap, stackLooksReady }
