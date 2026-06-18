<script setup lang="ts">
import type { CompanyStaffAgent } from '~/types/api'

defineProps<{
  staff: CompanyStaffAgent[]
}>()
</script>

<template>
  <div
    class="grid gap-4 lg:grid-cols-2 xl:grid-cols-3"
    data-testid="company-staff-roster"
  >
    <article
      v-for="agent in staff"
      :key="agent.id"
      class="rounded-lg border border-zinc-800 bg-zinc-900 p-4"
    >
      <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="min-w-0">
          <h3 class="truncate text-sm font-semibold text-zinc-100">
            {{ agent.display_name }}
          </h3>
          <p class="mt-1 font-mono text-xs text-zinc-500">
            {{ agent.role_slug }}
          </p>
        </div>
        <div class="flex flex-col items-end gap-1">
          <span
            class="rounded border px-2 py-0.5 text-[11px]"
            :class="agent.staff_active ? 'border-emerald-700 bg-emerald-950/50 text-emerald-300' : 'border-zinc-700 bg-zinc-950 text-zinc-400'"
          >
            {{ agent.staff_active ? 'active' : 'paused' }}
          </span>
          <span class="rounded border border-zinc-700 bg-zinc-950 px-2 py-0.5 text-[11px] text-zinc-300">
            {{ agent.runtime_mode }}
          </span>
        </div>
      </div>

      <p
        v-if="agent.description"
        class="mt-3 line-clamp-3 text-sm leading-relaxed text-zinc-300"
      >
        {{ agent.description }}
      </p>

      <dl class="mt-4 grid grid-cols-2 gap-2 text-xs">
        <div>
          <dt class="text-zinc-600">Council</dt>
          <dd class="mt-0.5 text-zinc-300">
            {{ agent.council_enabled ? 'enabled' : 'paused' }}
          </dd>
        </div>
        <div>
          <dt class="text-zinc-600">Approval</dt>
          <dd class="mt-0.5 text-zinc-300">
            {{ agent.approval_status }}
          </dd>
        </div>
      </dl>

      <div
        v-if="agent.trigger_keywords?.length"
        class="mt-4 flex flex-wrap gap-1.5"
      >
        <span
          v-for="keyword in agent.trigger_keywords"
          :key="keyword"
          class="rounded border border-zinc-700 bg-zinc-950 px-1.5 py-0.5 text-[11px] text-zinc-300"
        >
          {{ keyword }}
        </span>
      </div>
    </article>

    <UiEmptyState
      v-if="staff.length === 0"
      class="col-span-full"
      title="No staff seeded yet."
      hint="Seed the default Product Team Plus roster for the active project."
    />
  </div>
</template>
