<script setup lang="ts">
// Re-export wrapper — delegates to the root-level ExecutionTimeline component
</script>

<template>
  <ExecutionTimeline v-bind="$attrs">
    <template v-for="(_, name) in $slots" #[name]="slotProps">
      <slot :name="name" v-bind="slotProps ?? {}" />
    </template>
  </ExecutionTimeline>
</template>
