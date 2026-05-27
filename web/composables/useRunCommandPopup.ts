export interface RunCommandOptions {
  title?: string
  description?: string
  command: string
}

const state = reactive({
  open: false,
  title: 'Run this command manually',
  description: '',
  command: '',
})

export function useRunCommandPopup() {
  function show(opts: RunCommandOptions) {
    state.title = opts.title ?? 'Run this command manually'
    state.description = opts.description ?? ''
    state.command = opts.command
    state.open = true
  }

  function close() {
    state.open = false
  }

  return { state: readonly(state), show, close }
}
