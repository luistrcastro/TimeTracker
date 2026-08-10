<template>
  <v-tooltip :text="selectedName" location="top" :disabled="!selectedName">
    <template #activator="{ props: tooltipProps }">
      <div v-bind="tooltipProps">
        <v-autocomplete
          :model-value="modelValue"
          :items="projectOptions"
          item-title="name"
          item-value="id"
          density="compact"
          clearable
          v-model:search="search"
          :auto-select-first="isSingleMatch"
          v-bind="$attrs"
          @update:model-value="$emit('update:modelValue', $event)"
        />
      </div>
    </template>
  </v-tooltip>
</template>

<script setup lang="ts">
defineOptions({ inheritAttrs: false })

const props = defineProps<{ modelValue: string | null }>()
defineEmits<{ 'update:modelValue': [string | null] }>()

const replicon = useRepliconStore()

const projectOptions = computed(() =>
  replicon.projects.map(p => ({ id: p.id, name: p.name }))
)

const selectedName = computed(() => {
  if (!props.modelValue) return ''
  return replicon.projects.find(p => p.id === props.modelValue)?.name ?? ''
})

const search = ref('')
const isSingleMatch = useSingleMatchAutocomplete(projectOptions, search, p => p.name)
</script>
