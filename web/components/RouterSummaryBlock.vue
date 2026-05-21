<script setup lang="ts">
import type { RouterDisplay } from '../utils/humanizeOutput'

defineProps<{ router: RouterDisplay }>()
</script>

<template>
  <div class="space-y-2 text-sm">
    <p>
      <span class="text-zinc-500">Primary skill</span>
      <span class="ml-1 font-medium text-cyan-200">{{ router.primarySkill }}</span>
    </p>
    <p v-if="router.primaryReason" class="text-zinc-400 leading-relaxed">
      {{ router.primaryReason }}
    </p>
    <div v-if="router.secondarySkills.length" class="space-y-1">
      <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">
        Also considered
      </p>
      <ul class="space-y-1">
        <li
          v-for="skill in router.secondarySkills"
          :key="skill.name"
          class="text-zinc-300"
        >
          <span class="font-mono text-xs text-zinc-400">{{ skill.name }}</span>
          <span v-if="skill.reason" class="text-zinc-500"> — {{ skill.reason }}</span>
        </li>
      </ul>
    </div>
    <p class="text-xs text-zinc-500">
      Context loaded: {{ router.rulesCount }} rule{{ router.rulesCount === 1 ? '' : 's' }},
      {{ router.playbooksCount }} playbook{{ router.playbooksCount === 1 ? '' : 's' }},
      {{ router.checklistsCount }} checklist{{ router.checklistsCount === 1 ? '' : 's' }}
    </p>
  </div>
</template>
