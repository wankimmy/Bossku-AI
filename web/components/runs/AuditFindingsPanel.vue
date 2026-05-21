<script setup lang="ts">
// Re-export wrapper — delegates to the root-level AuditFindingsPanel component
</script>

<template>
  <AuditFindingsPanel v-bind="$attrs">
    <template v-for="(_, name) in $slots" #[name]="slotProps">
      <slot :name="name" v-bind="slotProps ?? {}" />
    </template>
  </AuditFindingsPanel>
</template>
