<template>
  <v-tooltip :text="text" location="top">
    <template #activator="{ props }">
      <v-icon
        v-bind="props"
        :icon="active ? 'mdi-check-circle' : 'mdi-close-circle'"
        :color="active ? 'success' : 'error'"
        size="small"
      />
    </template>
  </v-tooltip>
</template>

<script setup lang="ts">
const props = defineProps<{ active: boolean }>()

const text = computed(() =>
  props.active
    ? 'Active in the latest Replicon sync'
    : 'Not returned by the latest Replicon sync — kept for historical entries'
)
</script>
