<script setup lang="ts">
import type { CompanyStaffAgent } from '~/types/api'
import { apiErrorMessage } from '~/utils/apiErrorMessage'

definePageMeta({ layout: 'default' })

const api = useApi()
const toast = useToast()
const registry = useProjects()
await registry.refresh()

const hasActiveProject = computed(() => Boolean(registry.activeProjectId.value))
const savingId = ref<string | null>(null)
const seeding = ref(false)
const { data, pending, error, refresh } = await useAsyncData<{ data?: CompanyStaffAgent[] } | CompanyStaffAgent[]>(
  'company-staff',
  () => api.get('/company-staff'),
)

const staff = computed<CompanyStaffAgent[]>(() => {
  const raw = data.value
  if (!raw) return []
  return Array.isArray(raw) ? raw : raw.data ?? []
})

async function seedStaff() {
  seeding.value = true
  try {
    await api.post('/company-staff/seed')
    await refresh()
    toast.success('Company staff seeded.')
  } catch (e) {
    toast.error(apiErrorMessage(e, 'Could not seed company staff'))
  } finally {
    seeding.value = false
  }
}

const { data: teamsData, refresh: refreshTeams } = await useAsyncData('company-teams-page', () => api.get('/company-teams'))
const teams = computed(() => {
  const raw = teamsData.value as { data?: Array<{ slug: string; name: string }> } | undefined
  return raw?.data ?? []
})
const installingTeam = ref<string | null>(null)

async function installTeam(slug: string) {
  installingTeam.value = slug
  try {
    await api.post('/company-teams/install', { team_slug: slug })
    await Promise.all([refresh(), refreshTeams()])
    toast.success('Team installed.')
  }
  catch (e) {
    toast.error(apiErrorMessage(e, 'Could not install team'))
  }
  finally {
    installingTeam.value = null
  }
}

async function patchStaff(agent: CompanyStaffAgent, payload: Partial<CompanyStaffAgent>) {
  savingId.value = agent.id
  try {
    await api.patch(`/company-staff/${agent.id}`, payload)
    await refresh()
    toast.success('Staff settings updated.')
  }
  catch (e) {
    toast.error(apiErrorMessage(e, 'Could not update staff settings'))
  }
  finally {
    savingId.value = null
  }
}
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-3">
      <div>
        <h1 class="text-xl font-bold text-zinc-100">
          Staff
        </h1>
        <p class="mt-1 text-sm text-zinc-500">
          Project-scoped company staff who advise the CEO workflow.
        </p>
      </div>
      <button
        type="button"
        class="rounded-md border border-emerald-700/70 px-3 py-2 text-sm text-emerald-300 hover:bg-emerald-950 disabled:opacity-50"
        :disabled="!hasActiveProject || seeding"
        @click="seedStaff"
      >
        {{ seeding ? 'Seeding...' : 'Seed Product Team Plus' }}
      </button>
    </div>

    <div
      v-if="!hasActiveProject"
      class="rounded-lg border border-amber-800/60 bg-amber-950/30 px-4 py-3 text-sm text-amber-200"
    >
      No active project. Register a folder on
      <NuxtLink
        to="/project"
        class="font-medium text-amber-100 underline underline-offset-2 hover:text-white"
      >
        Project
      </NuxtLink>
      and click <strong>Activate</strong> before seeding staff or installing teams.
    </div>

    <div
      v-if="pending"
      class="text-sm text-zinc-500"
    >
      Loading staff...
    </div>

    <div
      v-else-if="error"
      class="rounded border border-red-900/60 bg-red-950/30 p-3 text-sm text-red-200"
    >
      Could not load staff.
    </div>

    <template v-else>
      <CompanyStaffRoster :staff="staff" />

      <section
        v-if="teams.length"
        class="space-y-3"
      >
        <h2 class="text-base font-semibold text-zinc-100">
          Teams catalog
        </h2>
        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
          <article
            v-for="team in teams"
            :key="team.slug"
            class="rounded-lg border border-zinc-800 bg-zinc-900 p-4"
          >
            <h3 class="text-sm font-semibold text-zinc-100">
              {{ team.name }}
            </h3>
            <p class="mt-1 text-xs text-zinc-500">
              {{ team.slug }}
            </p>
            <button
              type="button"
              class="mt-3 rounded border border-emerald-700/70 px-2.5 py-1.5 text-xs text-emerald-300 hover:bg-emerald-950 disabled:opacity-50"
              :disabled="!hasActiveProject || installingTeam === team.slug"
              @click="installTeam(team.slug)"
            >
              {{ installingTeam === team.slug ? 'Installing...' : 'Install team' }}
            </button>
          </article>
        </div>
      </section>

      <section
        v-if="staff.length"
        class="space-y-3"
      >
        <h2 class="text-base font-semibold text-zinc-100">
          Staff settings
        </h2>
        <div class="overflow-hidden rounded-lg border border-zinc-800">
          <table class="min-w-full divide-y divide-zinc-800 text-sm">
            <thead class="bg-zinc-900 text-xs uppercase tracking-wider text-zinc-500">
              <tr>
                <th class="px-3 py-2 text-left">Role</th>
                <th class="px-3 py-2 text-left">Active</th>
                <th class="px-3 py-2 text-left">Council</th>
                <th class="px-3 py-2 text-left">Runtime</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-zinc-800 bg-zinc-950">
              <tr
                v-for="agent in staff"
                :key="agent.id"
              >
                <td class="px-3 py-2 text-zinc-200">
                  {{ agent.display_name }}
                </td>
                <td class="px-3 py-2">
                  <button
                    type="button"
                    class="rounded border border-zinc-700 px-2 py-1 text-xs text-zinc-300 hover:bg-zinc-900"
                    :disabled="savingId === agent.id"
                    @click="patchStaff(agent, { staff_active: !agent.staff_active })"
                  >
                    {{ agent.staff_active ? 'Pause' : 'Activate' }}
                  </button>
                </td>
                <td class="px-3 py-2">
                  <button
                    type="button"
                    class="rounded border border-zinc-700 px-2 py-1 text-xs text-zinc-300 hover:bg-zinc-900"
                    :disabled="savingId === agent.id"
                    @click="patchStaff(agent, { council_enabled: !agent.council_enabled })"
                  >
                    {{ agent.council_enabled ? 'Disable' : 'Enable' }}
                  </button>
                </td>
                <td class="px-3 py-2">
                  <select
                    class="rounded border border-zinc-700 bg-zinc-900 px-2 py-1 text-xs text-zinc-200"
                    :value="agent.runtime_mode"
                    :disabled="savingId === agent.id"
                    @change="patchStaff(agent, { runtime_mode: ($event.target as HTMLSelectElement).value })"
                  >
                    <option value="advisory">advisory</option>
                    <option value="mixed">mixed</option>
                  </select>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </template>
  </div>
</template>
