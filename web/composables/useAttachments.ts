import { apiUrl } from '~/composables/useApiBase'
import { apiAuthHeaders } from '~/utils/apiAuthHeaders'

export type UploadedAttachmentMeta = {
  id: string
  name: string
  kind: string
  mime?: string
  size: number
  preview?: string
}

type PendingFile = {
  key: string
  file: File
}

type UploadResponse = {
  attachments: UploadedAttachmentMeta[]
}

export function useAttachments() {
  const pending = ref<PendingFile[]>([])
  const uploaded = ref<UploadedAttachmentMeta[]>([])
  const uploading = ref(false)
  const error = ref<string | null>(null)

  const hasAttachments = computed(
    () => pending.value.length > 0 || uploaded.value.length > 0,
  )

  function addFiles(files: FileList | File[]) {
    error.value = null
    const list = Array.from(files)
    const max = 10
    const remaining = Math.max(0, max - pending.value.length - uploaded.value.length)

    for (const file of list.slice(0, remaining)) {
      pending.value.push({
        key: `pending_${Date.now()}_${Math.random().toString(36).slice(2, 9)}`,
        file,
      })
    }
  }

  function removePending(key: string) {
    pending.value = pending.value.filter(p => p.key !== key)
  }

  async function removeUploaded(id: string) {
    error.value = null
    try {
      await $fetch(apiUrl(`/attachments/${id}`), {
        method: 'DELETE',
        headers: apiAuthHeaders(),
      })
      uploaded.value = uploaded.value.filter(a => a.id !== id)
    }
    catch (e: unknown) {
      error.value = e instanceof Error ? e.message : 'Could not remove attachment.'
    }
  }

  async function uploadAll(hint?: string): Promise<string[]> {
    if (pending.value.length === 0) {
      return uploaded.value.map(a => a.id)
    }

    uploading.value = true
    error.value = null

    try {
      const form = new FormData()
      for (const item of pending.value) {
        form.append('files[]', item.file)
      }
      if (hint?.trim()) {
        form.append('hint', hint.trim())
      }

      const res = await $fetch<UploadResponse>(apiUrl('/attachments'), {
        method: 'POST',
        headers: apiAuthHeaders(),
        body: form,
      })

      uploaded.value = [...uploaded.value, ...(res.attachments ?? [])]
      pending.value = []

      return uploaded.value.map(a => a.id)
    }
    catch (e: unknown) {
      error.value = e instanceof Error ? e.message : 'Attachment upload failed.'
      throw e
    }
    finally {
      uploading.value = false
    }
  }

  function clear() {
    pending.value = []
    uploaded.value = []
    error.value = null
  }

  function formatSize(bytes: number): string {
    if (bytes < 1024) return `${bytes} B`
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
  }

  return {
    pending,
    uploaded,
    uploading,
    error,
    hasAttachments,
    addFiles,
    removePending,
    removeUploaded,
    uploadAll,
    clear,
    formatSize,
  }
}
