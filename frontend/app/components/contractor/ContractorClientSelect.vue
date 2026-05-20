<template>
  <v-autocomplete
    :model-value="modelValue"
    :items="contractor.clients"
    item-title="name"
    item-value="id"
    density="compact"
    clearable
    v-bind="$attrs"
    @update:model-value="$emit('update:modelValue', $event)"
  />
</template>

<script setup lang="ts">
defineOptions({ inheritAttrs: false })

defineProps<{ modelValue: string | null }>()
defineEmits<{ 'update:modelValue': [string | null] }>()

const contractor = useContractorStore()
</script>
