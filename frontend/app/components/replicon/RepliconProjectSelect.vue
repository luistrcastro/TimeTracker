<template>
  <v-tooltip :text="selectedName" location="top" :disabled="!selectedName">
    <template #activator="{ props: tooltipProps }">
      <div v-bind="tooltipProps">
        <v-combobox
          ref="comboRef"
          :model-value="modelValue"
          :items="projectOptions"
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
        />
      </div>
    </template>
  </v-tooltip>
</template>

<script setup lang="ts">
defineOptions({ inheritAttrs: false })

const props = defineProps<{ modelValue: string | null }>()
const emit = defineEmits<{ 'update:modelValue': [string | null] }>()

const replicon = useRepliconStore()

const projectOptions = computed(() => {
  const options = replicon.projects.filter(p => p.isActive || p.id === props.modelValue)
  return options.map(p => ({ id: p.id, name: p.name }))
})

const selectedName = computed(() => {
  if (!props.modelValue) return ''
  return replicon.projects.find(p => p.id === props.modelValue)?.name ?? ''
})

const search = ref('')
const isSingleMatch = useSingleMatchAutocomplete(projectOptions, search, p => p.name)

// v-combobox allows free text, so Enter/Tab can commit the raw typed string
// instead of resolving to the highlighted item's id when the search text
// doesn't narrow to a single exact match (autoSelectFirst then never fires).
// Resolve it back to the matching project's id so this doesn't silently
// leave form.projectId as text an actual project can't be found from.
function onUpdateModelValue(value: string | null) {
  if (value && !replicon.projects.some(p => p.id === value)) {
    const match = projectOptions.value.find(p => p.name.toLowerCase() === value.toLowerCase())
    if (match) value = match.id
  }
  emit('update:modelValue', value)
}

const comboRef = ref()
defineExpose({ focus: () => comboRef.value?.focus() })
</script>
