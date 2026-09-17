<template>
  <v-tooltip :text="selectedPath" location="top" :disabled="!selectedPath">
    <template #activator="{ props: tooltipProps }">
      <div v-bind="tooltipProps">
        <v-combobox
          :model-value="modelValue"
          :items="taskOptions"
          item-title="name"
          item-value="id"
          :return-object="false"
          density="compact"
          clearable
          v-model:search="search"
          :auto-select-first="isSingleMatch"
          v-bind="$attrs"
          @update:model-value="onUpdateModelValue"
          @keydown.enter.stop
        >
          <template #item="{ props, item }">
            <v-list-item
              v-bind="props"
              :subtitle="item.raw.path?.length ? item.raw.path.join(' › ') : undefined"
            />
          </template>
        </v-combobox>
      </div>
    </template>
  </v-tooltip>
</template>

<script setup lang="ts">
defineOptions({ inheritAttrs: false })

const props = defineProps<{
  modelValue: string | null
  projectId: string | null
}>()

const emit = defineEmits<{ 'update:modelValue': [string | null] }>()

const replicon = useRepliconStore()

const taskOptions = computed(() => {
  const project = replicon.projects.find(p => p.id === props.projectId)
  const tasks = project?.tasks ?? []
  return tasks.filter(t => t.isActive || t.id === props.modelValue)
})

const selectedPath = computed(() => {
  if (!props.modelValue) return ''
  const task = taskOptions.value.find(t => t.id === props.modelValue)
  return task?.path?.length ? task.path.join(' › ') : ''
})

const search = ref('')
const isSingleMatch = useSingleMatchAutocomplete(taskOptions, search, t => t.name)

// See RepliconProjectSelect: v-combobox can commit raw typed text instead of
// resolving to the highlighted item's id when the search text doesn't narrow
// to a single exact match. Resolve it back to the matching task's id.
function onUpdateModelValue(value: string | null) {
  if (value && !taskOptions.value.some(t => t.id === value)) {
    const match = taskOptions.value.find(t => t.name.toLowerCase() === value.toLowerCase())
    if (match) value = match.id
  }
  emit('update:modelValue', value)
}
</script>
