'use strict'

const { app, dialog } = require('electron')
const { autoUpdater } = require('electron-updater')

// How often to re-check the GitHub Releases feed after the first check.
const CHECK_INTERVAL_MS = Number(process.env.BOSSKU_UPDATE_INTERVAL_MS || 60 * 60 * 1000)
// Small delay after the app is ready before the first check, so startup isn't blocked.
const FIRST_CHECK_DELAY_MS = Number(process.env.BOSSKU_UPDATE_FIRST_CHECK_MS || 10 * 1000)

let wired = false
let promptingAvailable = false
let promptingDownloaded = false

/**
 * Wire electron-updater to native popup dialogs:
 *   update-available  -> "Download now?"  (download on consent)
 *   update-downloaded -> "Restart now?"   (quitAndInstall on consent)
 *
 * Updates are published to GitHub Releases for wankimmy/Bossku-AI (see
 * electron-builder.yml `publish`). The feed is only meaningful in a packaged
 * build, so this no-ops in `npm start` dev mode.
 *
 * @param {object} opts
 * @param {() => (Electron.BrowserWindow | null)} opts.getWindow  current window for modal parenting
 * @param {(line: string) => void} [opts.sendLog]
 * @param {() => void} [opts.prepareForQuit]  lets main.js flip its isQuitting flag before install
 */
function initAutoUpdater({ getWindow, sendLog, prepareForQuit } = {}) {
  const log = (line) => {
    if (sendLog) sendLog(line)
  }

  if (!app.isPackaged) {
    log('Auto-update disabled in dev (run an installed build to receive updates).')
    return
  }
  if (wired) return
  wired = true

  // We drive the UX ourselves: ask before downloading, ask before installing.
  autoUpdater.autoDownload = false
  autoUpdater.autoInstallOnAppQuit = true
  autoUpdater.logger = {
    info: log,
    warn: log,
    error: (e) => log(`update error: ${e && e.message ? e.message : e}`),
    debug: () => {},
  }

  autoUpdater.on('checking-for-update', () => log('Checking for updates...'))
  autoUpdater.on('update-not-available', () => log('No update available (already on the latest version).'))
  autoUpdater.on('error', (err) => log(`Update check failed: ${err && err.message ? err.message : err}`))

  autoUpdater.on('download-progress', (p) => {
    log(`Downloading update: ${Math.round(p.percent)}% (${Math.round(p.bytesPerSecond / 1024)} KB/s)`)
  })

  autoUpdater.on('update-available', async (info) => {
    if (promptingAvailable) return
    promptingAvailable = true
    log(`Update available: v${info.version}`)
    const win = getWindow && getWindow()
    const opts = {
      type: 'info',
      buttons: ['Download', 'Later'],
      defaultId: 0,
      cancelId: 1,
      title: 'Update available',
      message: `BosskuAI v${info.version} is available.`,
      detail: 'A new version has been published. Download it now? You can keep working while it downloads.',
    }
    const { response } = win
      ? await dialog.showMessageBox(win, opts)
      : await dialog.showMessageBox(opts)
    promptingAvailable = false
    if (response === 0) {
      log('User accepted update download.')
      autoUpdater.downloadUpdate().catch((e) => log(`downloadUpdate failed: ${e && e.message ? e.message : e}`))
    } else {
      log('User postponed the update.')
    }
  })

  autoUpdater.on('update-downloaded', async (info) => {
    if (promptingDownloaded) return
    promptingDownloaded = true
    log(`Update downloaded: v${info.version}`)
    const win = getWindow && getWindow()
    const opts = {
      type: 'info',
      buttons: ['Restart now', 'Later'],
      defaultId: 0,
      cancelId: 1,
      title: 'Update ready',
      message: `BosskuAI v${info.version} is ready to install.`,
      detail: 'Restart now to apply the update, or it will be installed automatically the next time you quit.',
    }
    const { response } = win
      ? await dialog.showMessageBox(win, opts)
      : await dialog.showMessageBox(opts)
    promptingDownloaded = false
    if (response === 0) {
      log('Restarting to install update...')
      if (prepareForQuit) prepareForQuit()
      // isSilent=false (show installer), isForceRunAfter=true (relaunch after install)
      autoUpdater.quitAndInstall(false, true)
    }
  })

  const check = () => {
    autoUpdater.checkForUpdates().catch((e) => log(`checkForUpdates failed: ${e && e.message ? e.message : e}`))
  }

  setTimeout(check, FIRST_CHECK_DELAY_MS)
  setInterval(check, CHECK_INTERVAL_MS)
}

/** Manual trigger (e.g. from a tray "Check for updates" item). */
function checkForUpdatesNow(sendLog) {
  if (!app.isPackaged) {
    if (sendLog) sendLog('Auto-update disabled in dev.')
    return
  }
  autoUpdater.checkForUpdates().catch((e) => {
    if (sendLog) sendLog(`checkForUpdates failed: ${e && e.message ? e.message : e}`)
  })
}

module.exports = { initAutoUpdater, checkForUpdatesNow }
