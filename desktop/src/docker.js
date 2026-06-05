'use strict'

const { spawn } = require('node:child_process')
const http = require('node:http')
const path = require('node:path')

/**
 * Run a command, streaming stdout/stderr to onLog. Resolves with the exit code
 * instead of rejecting so callers can decide how to react to non-zero codes.
 *
 * @returns {Promise<{ code: number, out: string }>}
 */
function run(command, args, { cwd, onLog } = {}) {
  return new Promise((resolve, reject) => {
    let child
    try {
      child = spawn(command, args, { cwd, windowsHide: true })
    } catch (err) {
      reject(err)
      return
    }

    let out = ''
    const push = (chunk) => {
      const text = chunk.toString()
      out += text
      if (onLog) {
        for (const line of text.split(/\r?\n/)) {
          if (line.trim() !== '') onLog(line)
        }
      }
    }

    child.stdout.on('data', push)
    child.stderr.on('data', push)
    child.on('error', reject)
    child.on('close', (code) => resolve({ code: code ?? -1, out }))
  })
}

/**
 * Detect a usable Docker engine. Returns { ok, reason }.
 * reason: 'missing' (CLI not found) | 'daemon' (CLI ok, daemon down) | null.
 */
async function dockerStatus() {
  try {
    const cli = await run('docker', ['version', '--format', '{{.Server.Version}}'])
    if (cli.code === 0 && cli.out.trim() !== '') {
      return { ok: true, reason: null, version: cli.out.trim() }
    }
    // CLI exists but server unreachable (daemon not started).
    return { ok: false, reason: 'daemon' }
  } catch (err) {
    if (err && err.code === 'ENOENT') {
      return { ok: false, reason: 'missing' }
    }
    return { ok: false, reason: 'daemon' }
  }
}

function composeFile(stackDir) {
  return path.join(stackDir, 'docker-compose.yml')
}

function composeBaseArgs(stackDir) {
  return ['compose', '-f', composeFile(stackDir)]
}

function composeUp(stackDir, onLog) {
  return run('docker', [...composeBaseArgs(stackDir), 'up', '-d', '--build'], {
    cwd: stackDir,
    onLog,
  })
}

function composeStop(stackDir, onLog) {
  return run('docker', [...composeBaseArgs(stackDir), 'stop'], { cwd: stackDir, onLog })
}

function composeRestart(stackDir, onLog) {
  return run('docker', [...composeBaseArgs(stackDir), 'restart'], { cwd: stackDir, onLog })
}

/**
 * Run a command inside a compose service (non-interactive).
 */
function composeExec(stackDir, service, cmd, onLog) {
  return run('docker', [...composeBaseArgs(stackDir), 'exec', '-T', service, ...cmd], {
    cwd: stackDir,
    onLog,
  })
}

function httpOk(url, timeoutMs = 4000) {
  return new Promise((resolve) => {
    const req = http.get(url, (res) => {
      res.resume()
      // Any HTTP response (even 404) means the server is up.
      resolve((res.statusCode ?? 500) < 500)
    })
    req.on('error', () => resolve(false))
    req.setTimeout(timeoutMs, () => {
      req.destroy()
      resolve(false)
    })
  })
}

/**
 * Poll an HTTP endpoint until it responds or the timeout elapses.
 */
async function waitForHttp(url, { timeoutMs, onLog } = {}) {
  const deadline = Date.now() + (timeoutMs ?? 120000)
  let attempt = 0
  while (Date.now() < deadline) {
    attempt += 1
    if (await httpOk(url)) return true
    if (onLog && attempt % 5 === 0) {
      onLog(`Still waiting for ${url} ...`)
    }
    await new Promise((r) => setTimeout(r, 2000))
  }
  return false
}

module.exports = {
  run,
  dockerStatus,
  composeUp,
  composeStop,
  composeRestart,
  composeExec,
  waitForHttp,
}
