/** Bossku host bridge — replaces VS Code acquireVsCodeApi when embedded in Nuxt iframe. */
export const bossku = {
  postMessage(msg: unknown): void {
    if (window.parent !== window) {
      window.parent.postMessage(msg, '*')
    }
  },
}
