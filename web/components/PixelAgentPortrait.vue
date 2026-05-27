<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import {
  agentRoleToCharIndex,
  characterSpriteStyle,
  characterSpriteUrl,
  normalizeAgentRole,
} from '~/utils/pixelAgentCharacter'
import { themeForAgent } from '~/utils/agentTheme'

const props = withDefaults(defineProps<{
  agentRole: string
  scale?: number
}>(), {
  scale: 3,
})

const spriteFailed = ref(false)

const normalizedRole = computed(() => normalizeAgentRole(props.agentRole))
const charIndex = computed(() => agentRoleToCharIndex(props.agentRole))
const theme = computed(() => themeForAgent(normalizedRole.value))
const spriteStyle = computed(() => characterSpriteStyle(props.scale, charIndex.value))
const spriteUrl = computed(() => characterSpriteUrl(charIndex.value))

function probeSprite(url: string) {
  spriteFailed.value = false
  const img = new Image()
  img.onload = () => {
    spriteFailed.value = false
  }
  img.onerror = () => {
    spriteFailed.value = true
  }
  img.src = url
}

watch(spriteUrl, (url) => probeSprite(url), { immediate: true })

const displayName = computed(() => {
  const role = normalizedRole.value
  return role
    .split('-')
    .map((w) => w.charAt(0).toUpperCase() + w.slice(1))
    .join(' ')
})
</script>

<template>
  <div class="flex shrink-0 flex-col items-center gap-1" data-testid="pixel-agent-portrait">
    <div
      v-if="!spriteFailed"
      role="img"
      :aria-label="`${displayName} agent`"
      :style="spriteStyle"
    />
    <div
      v-else
      class="flex items-center justify-center rounded border border-zinc-600 bg-zinc-800 text-2xl"
      :style="{ width: `${24 * scale}px`, height: `${32 * scale}px` }"
      :aria-label="`${displayName} agent`"
    >
      {{ theme.icon }}
    </div>
    <span class="text-[10px] font-medium leading-tight" :class="theme.text">
      {{ displayName }}
    </span>
  </div>
</template>
