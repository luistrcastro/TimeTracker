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
          @update:model-value="$emit('update:modelValue', $event)"
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

defineEmits<{ 'update:modelValue': [string | null] }>()

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
</script>
