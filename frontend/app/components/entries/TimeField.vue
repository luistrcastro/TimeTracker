<template>
  <VMaskInput
    v-if="!ui.use12h"
    v-bind="$attrs"
    v-model="value"
    mask="##:##"
    return-masked-value
    :rules="rules"
  />
  <v-text-field
    v-else
    v-bind="$attrs"
    v-model="value"
    type="time"
  />
</template>

<script setup lang="ts">
defineOptions({ inheritAttrs: false })

const props = defineProps<{ modelValue: string }>()
const emit = defineEmits<{ 'update:modelValue': [string] }>()

const ui = useUiStore()

const value = computed({
  get: () => props.modelValue,
  set: (v) => emit('update:modelValue', v ?? ''),
})

function validTime(v: string) {
  if (!v) return true
  return /^([01]\d|2[0-3]):[0-5]\d$/.test(v) || 'Invalid time'
}
const rules = [validTime]
</script>
