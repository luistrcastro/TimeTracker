<template>
  <v-autocomplete
    :model-value="modelValue"
    :items="taskOptions"
    item-title="name"
    item-value="id"
    density="compact"
    clearable
    :disabled="!clientId"
    v-model:search="search"
    v-bind="$attrs"
    @update:model-value="$emit('update:modelValue', $event)"
  >
    <template #no-data>
      <v-list-item
        v-if="search?.trim()"
        :title="`Create '${search.trim()}'`"
        prepend-icon="mdi-plus"
        :disabled="creating"
        @click="createTask"
      />
    </template>
  </v-autocomplete>
</template>

<script setup lang="ts">
defineOptions({ inheritAttrs: false })

const props = defineProps<{
  modelValue: string | null
  clientId: string | null
}>()

const emit = defineEmits<{ 'update:modelValue': [string | null] }>()

const contractor = useContractorStore()
const search = ref('')
const creating = ref(false)

const taskOptions = computed(() => {
  const client = contractor.clients.find(c => c.id === props.clientId)
  return client?.tasks ?? []
})

async function createTask() {
  if (!props.clientId || !search.value?.trim()) return
  creating.value = true
  try {
    const newTask = await contractor.createTask(props.clientId, search.value.trim())
    emit('update:modelValue', newTask.id)
    search.value = ''
  } finally {
    creating.value = false
  }
}
</script>
