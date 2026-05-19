<template>
  <v-autocomplete
    :model-value="modelValue"
    :items="taskOptions"
    item-title="name"
    item-value="id"
    density="compact"
    clearable
    :disabled="!taskOptions.length"
    v-bind="$attrs"
    @update:model-value="$emit('update:modelValue', $event)"
  >
    <template #item="{ props, item }">
      <v-list-item
        v-bind="props"
        :subtitle="item.raw.path?.length ? item.raw.path.join(' › ') : undefined"
      />
    </template>
  </v-autocomplete>
</template>

<script setup lang="ts">
defineOptions({ inheritAttrs: false })

const props = defineProps<{
  modelValue: string | null
  projectId: string | null
}>()

defineEmits<{ 'update:modelValue': [string | null] }>()

const replicon = useRepliconStore()

const taskOptions = computed(() => {
  const project = replicon.projects.find(p => p.id === props.projectId)
  return project?.tasks ?? []
})
</script>
