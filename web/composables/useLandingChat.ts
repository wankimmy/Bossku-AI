import type { SseEvent } from '~/composables/useRunStream'

export type ChatTurn = {
  id: string
  role: 'user' | 'assistant'
  content: string
  createdAt: number
}

export type LandingConversation = {
  id: string
  title: string
  updatedAt: number
  createdAt: number
  turns: ChatTurn[]
  runEvents?: SseEvent[]
  activeRunId?: string | null
}

export type StoredV2 = {
  version: 2
  activeId: string | null
  conversations: LandingConversation[]
}

/** SSR-safe default; localStorage is loaded only in hydrateFromStorage(). */
export function createEmptyLandingChatStore(): StoredV2 {
  return { version: 2, activeId: null, conversations: [] }
}

const STORAGE_KEY = 'bossku_landing_chat_v2'
const LEGACY_KEY = 'bossku_landing_chat_v1'
const MAX_CONVERSATIONS = 30
const MAX_TURNS_PER_CONV = 40
const MAX_HISTORY_CHARS = 12_000

function newId(prefix: string): string {
  return `${prefix}_${Date.now()}_${Math.random().toString(36).slice(2, 9)}`
}

function titleFromMessage(text: string): string {
  const t = text.trim().replace(/\s+/g, ' ')
  if (!t) return 'New conversation'
  return t.length > 48 ? `${t.slice(0, 47)}…` : t
}

function loadStore(): StoredV2 {
  if (!import.meta.client) {
    return createEmptyLandingChatStore()
  }
  try {
    const raw = localStorage.getItem(STORAGE_KEY)
    if (raw) {
      const parsed = JSON.parse(raw) as StoredV2
      if (parsed?.version === 2 && Array.isArray(parsed.conversations)) {
        return {
          version: 2,
          activeId: parsed.activeId,
          conversations: parsed.conversations.filter(c => c?.id),
        }
      }
    }
  }
  catch {
    //
  }

  return migrateLegacy()
}

function migrateLegacy(): StoredV2 {
  try {
    const legacy = localStorage.getItem(LEGACY_KEY)
    if (!legacy) {
      return { version: 2, activeId: null, conversations: [] }
    }
    const turns = JSON.parse(legacy) as ChatTurn[]
    if (!Array.isArray(turns) || turns.length === 0) {
      return { version: 2, activeId: null, conversations: [] }
    }
    const firstUser = turns.find(t => t.role === 'user')
    const conv: LandingConversation = {
      id: newId('conv'),
      title: titleFromMessage(firstUser?.content ?? 'Imported chat'),
      updatedAt: turns.at(-1)?.createdAt ?? Date.now(),
      createdAt: turns[0]?.createdAt ?? Date.now(),
      turns: turns.filter(t => t.role && t.content),
    }
    localStorage.removeItem(LEGACY_KEY)
    return { version: 2, activeId: conv.id, conversations: [conv] }
  }
  catch {
    return { version: 2, activeId: null, conversations: [] }
  }
}

function saveStore(store: StoredV2) {
  if (!import.meta.client) return
  const sorted = [...store.conversations]
    .sort((a, b) => b.updatedAt - a.updatedAt)
    .slice(0, MAX_CONVERSATIONS)
  const activeStillExists = sorted.some(c => c.id === store.activeId)
  const payload: StoredV2 = {
    version: 2,
    activeId: activeStillExists ? store.activeId : (sorted[0]?.id ?? null),
    conversations: sorted.map(c => ({
      ...c,
      turns: c.turns.slice(-MAX_TURNS_PER_CONV),
    })),
  }
  try {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(payload))
  }
  catch {
    localStorage.setItem(STORAGE_KEY, JSON.stringify({
      ...payload,
      conversations: payload.conversations.slice(0, Math.floor(MAX_CONVERSATIONS / 2)),
    }))
  }
}

/** Build prompt + prior turns for the orchestrator / memory search. */
export function buildContextualPrompt(turns: ChatTurn[], nextUserMessage: string): string {
  const userMsg = nextUserMessage.trim()
  if (!userMsg) return ''
  if (turns.length === 0) return userMsg

  const lines: string[] = []
  let used = 0
  for (let i = turns.length - 1; i >= 0; i--) {
    const t = turns[i]
    const line = `${t.role === 'user' ? 'User' : 'Assistant'}: ${t.content.trim()}`
    if (used + line.length > MAX_HISTORY_CHARS) break
    lines.unshift(line)
    used += line.length
  }

  return `Previous conversation:\n${lines.join('\n\n')}\n\nCurrent request:\n${userMsg}`
}

export function turnsToConversation(turns: ChatTurn[]): { role: 'user' | 'assistant'; content: string }[] {
  return turns.map(t => ({ role: t.role, content: t.content.trim() })).filter(t => t.content !== '')
}

export function useLandingChat() {
  const store = useState<StoredV2>('bossku-landing-chat-v2', createEmptyLandingChatStore)
  const hydrated = useState('bossku-landing-chat-hydrated', () => false)

  const conversationsSorted = computed(() =>
    [...store.value.conversations].sort((a, b) => b.updatedAt - a.updatedAt),
  )

  const activeConversation = computed(() => {
    const id = store.value.activeId
    if (!id) return null
    return store.value.conversations.find(c => c.id === id) ?? null
  })

  const turns = computed(() => activeConversation.value?.turns ?? [])

  /** Chronological order for display (oldest → newest, latest at bottom). */
  const displayTurns = computed(() =>
    [...turns.value].sort((a, b) => a.createdAt - b.createdAt),
  )

  function persist() {
    saveStore(store.value)
  }

  function touchConversation(convId: string) {
    const conv = store.value.conversations.find(c => c.id === convId)
    if (conv) {
      conv.updatedAt = Date.now()
      persist()
    }
  }

  function ensureActiveConversation(firstMessage?: string): LandingConversation {
    let conv = activeConversation.value
    if (conv) return conv

    const title = firstMessage ? titleFromMessage(firstMessage) : 'New conversation'
    conv = {
      id: newId('conv'),
      title,
      updatedAt: Date.now(),
      createdAt: Date.now(),
      turns: [],
      runEvents: [],
    }
    store.value.conversations.unshift(conv)
    store.value.activeId = conv.id
    persist()
    return conv
  }

  function addUserTurn(content: string): ChatTurn {
    const text = content.trim()
    const conv = ensureActiveConversation(text)
    if (conv.turns.length === 0) {
      conv.title = titleFromMessage(text)
    }
    const turn: ChatTurn = { id: newId('turn'), role: 'user', content: text, createdAt: Date.now() }
    conv.turns.push(turn)
    touchConversation(conv.id)
    return turn
  }

  function addAssistantTurn(content: string): ChatTurn {
    const conv = ensureActiveConversation()
    const turn: ChatTurn = {
      id: newId('turn'),
      role: 'assistant',
      content: content.trim(),
      createdAt: Date.now(),
    }
    conv.turns.push(turn)
    touchConversation(conv.id)
    return turn
  }

  function saveRunEvents(events: SseEvent[], runId?: string | null) {
    const conv = activeConversation.value
    if (!conv) return
    conv.runEvents = [...events]
    if (runId !== undefined) {
      conv.activeRunId = runId
    }
    touchConversation(conv.id)
  }

  function setActiveRunId(runId: string | null) {
    const conv = activeConversation.value
    if (!conv) return
    conv.activeRunId = runId
    touchConversation(conv.id)
  }

  function getActiveRunId(conversationId: string): string | null {
    const conv = store.value.conversations.find(c => c.id === conversationId)
    return conv?.activeRunId ?? null
  }

  function getRunEvents(conversationId: string): SseEvent[] {
    const conv = store.value.conversations.find(c => c.id === conversationId)
    return conv?.runEvents ? [...conv.runEvents] : []
  }

  function startNewConversation() {
    const conv: LandingConversation = {
      id: newId('conv'),
      title: 'New conversation',
      updatedAt: Date.now(),
      createdAt: Date.now(),
      turns: [],
      runEvents: [],
    }
    store.value.conversations.unshift(conv)
    store.value.activeId = conv.id
    persist()
    return conv.id
  }

  function selectConversation(id: string): boolean {
    if (!store.value.conversations.some(c => c.id === id)) return false
    store.value.activeId = id
    persist()
    return true
  }

  function clearActiveConversation() {
    store.value.activeId = null
    persist()
  }

  function deleteConversation(id: string) {
    store.value.conversations = store.value.conversations.filter(c => c.id !== id)
    if (store.value.activeId === id) {
      store.value.activeId = store.value.conversations[0]?.id ?? null
    }
    persist()
  }

  function clearConversation() {
    startNewConversation()
  }

  function contextualPromptFor(nextUserMessage: string): string {
    return buildContextualPrompt(turns.value, nextUserMessage)
  }

  function conversationPayload(): { role: 'user' | 'assistant'; content: string }[] {
    return turnsToConversation(turns.value)
  }

  function hydrateFromStorage() {
    if (!import.meta.client || hydrated.value) return
    hydrated.value = true
    store.value = loadStore()
  }

  onMounted(() => {
    hydrateFromStorage()
  })

  return {
    store,
    conversations: conversationsSorted,
    activeId: computed(() => store.value.activeId),
    activeConversation,
    turns,
    displayTurns,
    hydrated,
    addUserTurn,
    addAssistantTurn,
    saveRunEvents,
    setActiveRunId,
    getActiveRunId,
    getRunEvents,
    startNewConversation,
    selectConversation,
    clearActiveConversation,
    deleteConversation,
    clearConversation,
    hydrateFromStorage,
    contextualPromptFor,
    conversationPayload,
    persist,
  }
}
