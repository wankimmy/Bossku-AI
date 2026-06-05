'use strict'

const fs = require('node:fs')
const path = require('node:path')
const { spawn } = require('node:child_process')

const { PORTS, runtimePaths } = require('./config')

/** @type {import('node:child_process').ChildProcess | null} */
let apiChild = null
/** @type {import('node:child_process').ChildProcess | null} */
let webChild = null

function pipeChild(child, label, onLog) {
  const push = (chunk) => {
    const text = chunk.toString()
    for (const line of text.split(/\r?\n/)) {
      if (line.trim() !== '' && onLog) onLog(`[${label}] ${line}`)
    }
  }
  child.stdout?.on('data', push)
  child.stderr?.on('data', push)
  child.on('error', (err) => {
    if (onLog) onLog(`[${label}] ${err.message}`)
  })
}

/**
 * @param {string} stackDir
 */
function buildNativeEnv(stackDir) {
  const rt = runtimePaths()
  const pathPrefix = [path.dirname(rt.php), path.dirname(rt.node)].join(path.delimiter)
  return {
    ...process.env,
    PATH: `${pathPrefix}${path.delimiter}${process.env.PATH || ''}`,
    BOSSKU_REPO_PATH: stackDir,
  }
}

/**
 * @param {string} stackDir
 * @param {(line: string) => void} [onLog]
 */
async function startNative(stackDir, onLog) {
  const rt = runtimePaths()
  if (!fs.existsSync(rt.php)) {
    throw new Error(`PHP runtime not found at ${rt.php}. Run first-time setup.`)
  }
  if (!fs.existsSync(rt.node)) {
    throw new Error(`Node runtime not found at ${rt.node}. Run first-time setup.`)
  }

  const appDir = path.join(stackDir, 'app')
  const webDir = path.join(stackDir, 'web')
  const serverJs = path.join(webDir, '.output', 'server', 'index.mjs')
  if (!fs.existsSync(serverJs)) {
    throw new Error('Web build missing (.output/server). Complete first-time setup.')
  }

  await stopNative(onLog)

  const baseEnv = buildNativeEnv(stackDir)

  apiChild = spawn(
    rt.php,
    ['artisan', 'serve', `--host=127.0.0.1`, `--port=${PORTS.api}`],
    { cwd: appDir, env: baseEnv, windowsHide: true },
  )
  pipeChild(apiChild, 'api', onLog)

  webChild = spawn(rt.node, [serverJs], {
    cwd: webDir,
    env: {
      ...baseEnv,
      PORT: String(PORTS.web),
      HOST: '0.0.0.0',
      NUXT_PUBLIC_API_BASE: '',
      NUXT_API_PROXY_UPSTREAM: `http://127.0.0.1:${PORTS.api}/api`,
      NODE_ENV: 'production',
    },
    windowsHide: true,
  })
  pipeChild(webChild, 'web', onLog)

  if (onLog) onLog('Native API and web servers started.')
}

/**
 * @param {(line: string) => void} [onLog]
 */
async function stopNative(onLog) {
  for (const [label, child] of [
    ['api', apiChild],
    ['web', webChild],
  ]) {
    if (child && !child.killed) {
      try {
        child.kill()
        if (onLog) onLog(`Stopped ${label} server.`)
      } catch {
        //
      }
    }
  }
  apiChild = null
  webChild = null
}

function nativeRunning() {
  return apiChild !== null && webChild !== null && apiChild.exitCode === null && webChild.exitCode === null
}

async function restartNative(stackDir, onLog) {
  await stopNative(onLog)
  await startNative(stackDir, onLog)
}

module.exports = {
  startNative,
  stopNative,
  restartNative,
  nativeRunning,
}
