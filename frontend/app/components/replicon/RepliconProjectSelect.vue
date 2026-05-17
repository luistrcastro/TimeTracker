<template>
  <v-select
    :model-value="modelValue"
    :items="projectOptions"
    item-title="label"
    item-value="id"
    density="compact"
    clearable
    v-bind="$attrs"
    @update:model-value="$emit('update:modelValue', $event)"
  >
    <template #item="{ props, item }">
      <v-list-item v-bind="props" :subtitle="item.raw.name" />
    </template>
  </v-select>
</template>

<script setup lang="ts">
defineOptions({ inheritAttrs: false })

defineProps<{ modelValue: string | null }>()
defineEmits<{ 'update:modelValue': [string | null] }>()

const replicon = useRepliconStore()

const projectOptions = computed(() =>
  replicon.projects.map(p => ({ id: p.id, label: p.code, name: p.name }))
)
</script>
