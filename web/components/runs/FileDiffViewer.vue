<script setup lang="ts">
// Re-export wrapper — delegates to the root-level FileDiffViewer component
</script>

<template>
  <FileDiffViewer v-bind="$attrs">
    <template v-for="(_, name) in $slots" #[name]="slotProps">
      <slot :name="name" v-bind="slotProps ?? {}" />
    </template>
  </FileDiffViewer>
</template>
