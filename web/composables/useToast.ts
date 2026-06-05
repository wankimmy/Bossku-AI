export type ToastType = 'success' | 'error' | 'info' | 'warning'

export interface Toast {
  id: string
  type: ToastType
  message: string
}

export function useToast() {
  const toasts = useState<Toast[]>('toasts', () => [])

  function add(message: string, type: ToastType = 'info', duration = 3500) {
    if (!import.meta.client) return
    const id = Math.random().toString(36).slice(2)
    toasts.value.push({ id, type, message })
    setTimeout(() => remove(id), duration)
  }

  function remove(id: string) {
    toasts.value = toasts.value.filter(t => t.id !== id)
  }

  return {
    toasts: readonly(toasts),
    success: (msg: string) => add(msg, 'success'),
    error:   (msg: string) => add(msg, 'error', 5000),
    info:    (msg: string) => add(msg, 'info'),
    warning: (msg: string) => add(msg, 'warning'),
    remove,
  }
}
