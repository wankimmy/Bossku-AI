'use strict'

const { contextBridge, ipcRenderer } = require('electron')

contextBridge.exposeInMainWorld('bossku', {
  onStatus: (cb) => {
    const handler = (_event, payload) => cb(payload)
    ipcRenderer.on('status', handler)
    return () => ipcRenderer.removeListener('status', handler)
  },
  onLog: (cb) => {
    const handler = (_event, line) => cb(line)
    ipcRenderer.on('log', handler)
    return () => ipcRenderer.removeListener('log', handler)
  },
  retry: () => ipcRenderer.send('retry'),
  quit: () => ipcRenderer.send('quit'),
  // Returns the selected absolute folder path, or null if cancelled.
  openFolder: () => ipcRenderer.invoke('dialog:openFolder'),
  // Lets the web UI detect it's running inside the BosskuAI desktop shell.
  isDesktop: true,
})
