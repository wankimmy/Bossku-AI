<script setup lang="ts">
// Re-export wrapper — delegates to the root-level PlanChecklist component
</script>

<template>
  <PlanChecklist v-bind="$attrs">
    <template v-for="(_, name) in $slots" #[name]="slotProps">
      <slot :name="name" v-bind="slotProps ?? {}" />
    </template>
  </PlanChecklist>
</template>
