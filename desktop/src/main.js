'use strict'

const path = require('node:path')
const { app, BrowserWindow, Tray, Menu, shell, nativeImage, ipcMain, dialog } = require('electron')

const {
  WEB_URL,
  HEALTH_TIMEOUT_MS,
  bundledStackDir,
  userStackDir,
} = require('./config')
const {
  dockerStatus,
  composeUp,
  composeStop,
  composeRestart,
  composeExec,
  waitForHttp,
} = require('./docker')
const { syncStack } = require('./stackSync')
const { isBootstrapped, ensureEnv, runBootstrap } = require('./bootstrap')
const { resolveRuntimeMode } = require('./runtimeMode')
const { startNative, stopNative, restartNative, runMigrations } = require('./nativeRuntime')
const { ensureNativeBootstrap } = require('./bootstrapNative')
const { initAutoUpdater, checkForUpdatesNow } = require('./updater')

// 1x1 emerald pixel — a valid tray image without shipping a binary asset.
const TRAY_PNG =
  'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAAC0lEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='

let splashWindow = null
let mainWindow = null
let tray = null
let isQuitting = false
let booting = false
let stackReady = false
/** @type {'native' | 'docker'} */
let runtimeMode = 'native'

function createSplash() {
  splashWindow = new BrowserWindow({
    width: 560,
    height: 460,
    resizable: false,
    fullscreenable: false,
    maximizable: false,
    title: 'BosskuAI',
    backgroundColor: '#0a0a0a',
    webPreferences: {
      preload: path.join(__dirname, 'preload.js'),
      contextIsolation: true,
      nodeIntegration: false,
    },
  })
  splashWindow.removeMenu()
  splashWindow.loadFile(path.join(__dirname, 'splash.html'))
  splashWindow.on('closed', () => {
    splashWindow = null
    if (!stackReady && !isQuitting) {
      isQuitting = true
      app.quit()
    }
  })
}

function sendStatus(state, message, hint = '') {
  if (splashWindow && !splashWindow.isDestroyed()) {
    splashWindow.webContents.send('status', { state, message, hint })
  }
}

function sendLog(line) {
  if (splashWindow && !splashWindow.isDestroyed()) {
    splashWindow.webContents.send('log', line)
  }
  console.log('[bossku]', line)
}

function createMainWindow() {
  mainWindow = new BrowserWindow({
    width: 1440,
    height: 920,
    minWidth: 980,
    minHeight: 640,
    title: 'BosskuAI',
    backgroundColor: '#0a0a0a',
    show: false,
    webPreferences: {
      preload: path.join(__dirname, 'preload.js'),
      contextIsolation: true,
      nodeIntegration: false,
    },
  })
  mainWindow.removeMenu()
  mainWindow.loadURL(WEB_URL)

  mainWindow.once('ready-to-show', () => {
    mainWindow.show()
    if (splashWindow && !splashWindow.isDestroyed()) splashWindow.close()
  })

  mainWindow.webContents.setWindowOpenHandler(({ url }) => {
    if (!url.startsWith(WEB_URL)) {
      shell.openExternal(url)
      return { action: 'deny' }
    }
    return { action: 'allow' }
  })

  mainWindow.on('close', (e) => {
    if (!isQuitting) {
      e.preventDefault()
      mainWindow.hide()
    }
  })
  mainWindow.on('closed', () => {
    mainWindow = null
  })
}

function showMainWindow() {
  if (mainWindow) {
    if (mainWindow.isMinimized()) mainWindow.restore()
    mainWindow.show()
    mainWindow.focus()
  } else if (stackReady) {
    createMainWindow()
  }
}

function buildTray() {
  if (tray) return
  const image = nativeImage.createFromDataURL(`data:image/png;base64,${TRAY_PNG}`)
  tray = new Tray(image)
  tray.setToolTip('BosskuAI')
  refreshTrayMenu()
  tray.on('double-click', showMainWindow)
}

function refreshTrayMenu() {
  if (!tray) return
  const stackDir = userStackDir()
  const isDocker = runtimeMode === 'docker'

  tray.setContextMenu(
    Menu.buildFromTemplate([
      { label: 'Open BosskuAI', click: showMainWindow, enabled: stackReady },
      { type: 'separator' },
      { label: 'Check for updates…', click: () => checkForUpdatesNow(sendLog) },
      { type: 'separator' },
      {
        label: isDocker ? 'Restart stack' : 'Restart servers',
        enabled: stackReady,
        click: async () => {
          if (isDocker) {
            await composeRestart(stackDir, sendLog)
          } else {
            await restartNative(stackDir, sendLog)
          }
        },
      },
      {
        label: isDocker ? 'Stop stack (free resources)' : 'Stop servers',
        enabled: stackReady,
        click: async () => {
          if (isDocker) {
            await composeStop(stackDir, sendLog)
          } else {
            await stopNative(sendLog)
            stackReady = false
            refreshTrayMenu()
          }
        },
      },
      { type: 'separator' },
      {
        label: 'Quit',
        click: () => {
          isQuitting = true
          app.quit()
        },
      },
      {
        label: isDocker ? 'Stop containers and quit' : 'Stop servers and quit',
        click: async () => {
          isQuitting = true
          try {
            if (isDocker) {
              await composeStop(stackDir, sendLog)
            } else {
              await stopNative(sendLog)
            }
          } finally {
            app.quit()
          }
        },
      },
    ]),
  )
}

async function waitForBackendDocker(stackDir) {
  const deadline = Date.now() + HEALTH_TIMEOUT_MS
  let attempt = 0
  while (Date.now() < deadline) {
    attempt += 1
    const { code } = await composeExec(stackDir, 'backend', ['php', '-v'])
    if (code === 0) return true
    if (attempt % 5 === 0) sendLog('Waiting for backend container...')
    await new Promise((r) => setTimeout(r, 2000))
  }
  return false
}

async function bootDocker() {
  sendStatus('busy', 'Checking Docker...')
  const docker = await dockerStatus()
  if (!docker.ok) {
    const hint =
      docker.reason === 'missing'
        ? 'Docker Desktop was not found. Install it from docker.com, or use the native desktop app without Docker.'
        : 'Docker Desktop is installed but not running. Start it, wait for it to be ready, then click Retry.'
    sendStatus('error', 'Docker is not available', hint)
    return false
  }
  sendLog(`Docker engine ${docker.version} detected.`)

  const stackDir = userStackDir()
  if (app.isPackaged) {
    sendStatus('busy', 'Preparing application files...')
    await syncStack(bundledStackDir(), stackDir, sendLog)
  }

  sendStatus('busy', 'Configuring environment...')
  await ensureEnv(stackDir, sendLog)

  sendStatus('busy', 'Starting containers (first run builds images; this can take several minutes)...')
  const up = await composeUp(stackDir, sendLog)
  if (up.code !== 0) {
    sendStatus('error', 'Failed to start containers', 'See the log above. Ensure Docker Desktop has enough resources, then click Retry.')
    return false
  }

  sendStatus('busy', 'Waiting for the backend...')
  if (!(await waitForBackendDocker(stackDir))) {
    sendStatus('error', 'Backend did not become ready in time', 'Click Retry, or check Docker Desktop container logs.')
    return false
  }

  if (!isBootstrapped()) {
    sendStatus('busy', 'Initializing database (first run only)...')
    await runBootstrap(stackDir, sendLog)
  }

  sendStatus('busy', 'Waiting for the dashboard to build...')
  const webOk = await waitForHttp(WEB_URL, { timeoutMs: HEALTH_TIMEOUT_MS, onLog: sendLog })
  if (!webOk) {
    sendStatus('error', 'The web dashboard did not respond in time', 'The Nuxt build may still be running. Click Retry to keep waiting.')
    return false
  }

  return true
}

async function bootNative() {
  const stackDir = userStackDir()

  if (app.isPackaged) {
    sendStatus('busy', 'Preparing application files...')
    await syncStack(bundledStackDir(), stackDir, sendLog)
  }

  const needsSetup = !isBootstrapped()
  if (needsSetup) {
    sendStatus('busy', 'Installing BosskuAI (first run — downloads PHP, Node, and Git)...')
    sendLog('This can take 5–15 minutes on first launch. Internet access is required.')
    try {
      await ensureNativeBootstrap(stackDir, sendLog)
    } catch (err) {
      sendStatus(
        'error',
        'Setup failed',
        String(err && err.message ? err.message : err),
      )
      return false
    }
  }

  sendStatus('busy', 'Running database migrations...')
  try {
    await runMigrations(stackDir, sendLog)
  } catch (err) {
    sendStatus('error', 'Database migration failed', String(err && err.message ? err.message : err))
    return false
  }

  sendStatus('busy', 'Starting BosskuAI servers...')
  try {
    await startNative(stackDir, sendLog)
  } catch (err) {
    if (!needsSetup) {
      sendStatus('busy', 'Repairing installation...')
      try {
        await ensureNativeBootstrap(stackDir, sendLog)
        await startNative(stackDir, sendLog)
      } catch (repairErr) {
        sendStatus('error', 'Could not start BosskuAI', String(repairErr && repairErr.message ? repairErr.message : repairErr))
        return false
      }
    } else {
      sendStatus('error', 'Could not start BosskuAI', String(err && err.message ? err.message : err))
      return false
    }
  }

  sendStatus('busy', 'Waiting for the dashboard...')
  const webOk = await waitForHttp(WEB_URL, { timeoutMs: HEALTH_TIMEOUT_MS, onLog: sendLog })
  if (!webOk) {
    sendStatus('error', 'The web dashboard did not respond in time', 'Click Retry to keep waiting.')
    return false
  }

  return true
}

async function boot() {
  if (booting) return
  booting = true
  runtimeMode = resolveRuntimeMode()
  sendLog(`Runtime mode: ${runtimeMode}`)

  try {
    const ok = runtimeMode === 'docker' ? await bootDocker() : await bootNative()
    if (!ok) {
      return
    }

    stackReady = true
    refreshTrayMenu()
    sendStatus('ok', 'Ready')
    createMainWindow()

    // Start watching GitHub Releases for new versions (no-op in dev / unpackaged).
    initAutoUpdater({
      getWindow: () => mainWindow || splashWindow,
      sendLog,
      prepareForQuit: () => {
        isQuitting = true
      },
    })
  } catch (err) {
    sendStatus('error', 'Startup failed', String(err && err.message ? err.message : err))
  } finally {
    booting = false
  }
}

function wireIpc() {
  ipcMain.on('retry', () => {
    if (!booting) boot()
  })
  ipcMain.on('quit', () => {
    isQuitting = true
    app.quit()
  })
  // Native folder picker for the dashboard (Project page "Open Folder").
  ipcMain.handle('dialog:openFolder', async () => {
    const parent = mainWindow ?? splashWindow ?? undefined
    const result = await dialog.showOpenDialog(parent, {
      title: 'Select project folder',
      properties: ['openDirectory', 'createDirectory'],
    })
    if (result.canceled || result.filePaths.length === 0) return null
    return result.filePaths[0]
  })
}

const gotLock = app.requestSingleInstanceLock()
if (!gotLock) {
  app.quit()
} else {
  app.on('second-instance', showMainWindow)

  app.whenReady().then(() => {
    wireIpc()
    buildTray()
    createSplash()
    splashWindow.webContents.once('did-finish-load', boot)
  })

  app.on('window-all-closed', () => {
    // Keep running in the tray.
  })

  app.on('activate', showMainWindow)

  app.on('before-quit', async () => {
    isQuitting = true
    if (runtimeMode === 'native') {
      await stopNative()
    }
  })
}
