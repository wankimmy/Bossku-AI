'use strict'

const path = require('node:path')
const { app, BrowserWindow, Tray, Menu, shell, nativeImage, ipcMain } = require('electron')

const {
  WEB_URL,
  API_URL,
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

// 1x1 emerald pixel — a valid tray image without shipping a binary asset.
const TRAY_PNG =
  'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAAC0lEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='

let splashWindow = null
let mainWindow = null
let tray = null
let isQuitting = false
let booting = false
let stackReady = false

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
      // User closed the splash before the app was ready.
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
  // Helpful when launched from a console during development.
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

  // Open external links in the system browser, keep app links in-app.
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
  tray.setContextMenu(
    Menu.buildFromTemplate([
      { label: 'Open BosskuAI', click: showMainWindow, enabled: stackReady },
      { type: 'separator' },
      {
        label: 'Restart stack',
        enabled: stackReady,
        click: async () => {
          sendLogToConsole('Restarting stack...')
          await composeRestart(stackDir, sendLogToConsole)
        },
      },
      {
        label: 'Stop stack (free resources)',
        enabled: stackReady,
        click: async () => {
          await composeStop(stackDir, sendLogToConsole)
        },
      },
      { type: 'separator' },
      {
        label: 'Quit (leave containers running)',
        click: () => {
          isQuitting = true
          app.quit()
        },
      },
      {
        label: 'Stop containers and quit',
        click: async () => {
          isQuitting = true
          try {
            await composeStop(stackDir, sendLogToConsole)
          } finally {
            app.quit()
          }
        },
      },
    ]),
  )
}

function sendLogToConsole(line) {
  console.log('[bossku]', line)
}

/**
 * Wait until `docker compose exec backend php -v` succeeds, which confirms the
 * backend container is running and ready to accept bootstrap commands.
 */
async function waitForBackend(stackDir) {
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

async function boot() {
  if (booting) return
  booting = true
  try {
    sendStatus('busy', 'Checking Docker...')
    const docker = await dockerStatus()
    if (!docker.ok) {
      const hint =
        docker.reason === 'missing'
          ? 'Docker Desktop was not found. Install it from docker.com, then click Retry.'
          : 'Docker Desktop is installed but not running. Start it, wait for it to be ready, then click Retry.'
      sendStatus('error', 'Docker is not available', hint)
      booting = false
      return
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
      booting = false
      return
    }

    sendStatus('busy', 'Waiting for the backend...')
    if (!(await waitForBackend(stackDir))) {
      sendStatus('error', 'Backend did not become ready in time', 'Click Retry, or check Docker Desktop container logs.')
      booting = false
      return
    }

    if (!isBootstrapped()) {
      sendStatus('busy', 'Initializing database (first run only)...')
      await runBootstrap(stackDir, sendLog)
    }

    sendStatus('busy', 'Waiting for the dashboard to build...')
    const webOk = await waitForHttp(WEB_URL, { timeoutMs: HEALTH_TIMEOUT_MS, onLog: sendLog })
    if (!webOk) {
      sendStatus('error', 'The web dashboard did not respond in time', 'The Nuxt build may still be running. Click Retry to keep waiting.')
      booting = false
      return
    }

    stackReady = true
    refreshTrayMenu()
    sendStatus('ok', 'Ready')
    createMainWindow()
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
    // Intentionally empty: keep running in the tray instead of quitting.
  })

  app.on('activate', showMainWindow)

  app.on('before-quit', () => {
    isQuitting = true
  })
}
