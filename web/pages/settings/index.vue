<script setup lang="ts">
definePageMeta({ layout: 'default' })

const router = useRouter()
onMounted(() => router.replace('/settings/providers'))
</script>

<template>
  <div class="text-sm text-zinc-500">Redirecting to providers...</div>
</template>
