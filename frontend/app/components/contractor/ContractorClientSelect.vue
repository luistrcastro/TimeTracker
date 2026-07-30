<template>
  <v-autocomplete
    :model-value="modelValue"
    :items="clientItems"
    item-title="name"
    item-value="id"
    density="compact"
    clearable
    v-model:search="search"
    :auto-select-first="isSingleMatch"
    v-bind="$attrs"
    @update:model-value="$emit('update:modelValue', $event)"
  />
</template>

<script setup lang="ts">
defineOptions({ inheritAttrs: false })

defineProps<{ modelValue: string | null }>()
defineEmits<{ 'update:modelValue': [string | null] }>()

const contractor = useContractorStore()

const clientItems = computed(() => contractor.clients)
const search = ref('')
const isSingleMatch = useSingleMatchAutocomplete(clientItems, search, c => c.name)
</script>
