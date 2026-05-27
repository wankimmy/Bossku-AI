export interface RunCommandOptions {
  title?: string
  description?: string
  command: string
  requireOutput?: boolean
}

const state = reactive({
  open: false,
  title: 'Run this command manually',
  description: '',
  command: '',
  requireOutput: false,
  output: '',
})

export function useRunCommandPopup() {
  function show(opts: RunCommandOptions) {
    state.title = opts.title ?? 'Run this command manually'
    state.description = opts.description ?? ''
    state.command = opts.command
    state.requireOutput = opts.requireOutput ?? false
    state.output = ''
    state.open = true
  }

  function close() {
    state.open = false
  }

  function setOutput(value: string) {
    state.output = value
  }

  return { state: readonly(state), show, close, setOutput }
}
