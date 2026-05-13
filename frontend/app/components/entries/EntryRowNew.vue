<template>
  <tr class="new-row">
    <td>
      <AutocompleteInput
        v-model="form.clientName"
        :suggestions="clients"
        density="compact"
        variant="underlined"
        placeholder="Client"
        hide-details
        @keydown.enter.prevent="focusNext('task')"
      />
    </td>
    <td>
      <AutocompleteInput
        v-model="form.task"
        :suggestions="taskSuggestionsForClient"
        density="compact"
        variant="underlined"
        placeholder="Task"
        hide-details
        ref="taskRef"
        @keydown.enter.prevent="focusNext('desc')"
      />
    </td>
    <td>
      <v-text-field
        v-model="form.description"
        density="compact"
        variant="underlined"
        placeholder="Description"
        hide-details
        ref="descRef"
        @keydown.enter.prevent="focusNext('subdesc')"
      />
    </td>
    <td>
      <v-text-field
        v-model="form.subDescription"
        density="compact"
        variant="underlined"
        placeholder="Sub-description"
        hide-details
        ref="subdescRef"
        @keydown.enter.prevent="focusNext('start')"
      />
    </td>
    <td>
      <v-text-field
        v-model="form.start"
        type="time"
        density="compact"
        variant="underlined"
        hide-details
        ref="startRef"
        @keydown.enter.prevent="focusNext('finish')"
      />
    </td>
    <td>
      <v-text-field
        v-model="form.finish"
        type="time"
        density="compact"
        variant="underlined"
        hide-details
        ref="finishRef"
        @input="calcDuration"
        @keydown.enter.prevent="save"
      />
    </td>
    <td class="text-body-2">{{ form.duration }}</td>
    <td></td>
    <td></td>
    <td>
      <v-btn size="x-small" color="primary" icon="mdi-check" @click="save" :disabled="!canSave" />
    </td>
  </tr>
</template>

<script setup lang="ts">
import type { TimeEntry } from '~/types'

const props = defineProps<{
  clients: string[]
  tasks: string[]
  prefillStart: string
}>()

const emit = defineEmits<{ save: [entry: Partial<TimeEntry>] }>()

const ui = useUiStore()

const taskRef = ref()
const descRef = ref()
const subdescRef = ref()
const startRef = ref()
const finishRef = ref()

const form = reactive({
  clientName: '',
  task: '',
  description: '',
  subDescription: '',
  start: '',
  finish: '',
  duration: '0:00',
  durationMinutes: 0,
})

// Populate task suggestions filtered to selected client — or all tasks
const taskSuggestionsForClient = computed(() => props.tasks)

watch(() => props.prefillStart, (v) => { if (!form.start) form.start = v })

function calcDuration() {
  if (form.start && form.finish) {
    const startMins = timeToMinutes(form.start)
    const finishMins = timeToMinutes(form.finish)
    const diff = finishMins - startMins
    if (diff > 0) {
      form.durationMinutes = diff
      form.duration = `${Math.floor(diff / 60)}:${String(diff % 60).padStart(2, '0')}`
      return
    }
  }
  form.durationMinutes = 0
  form.duration = '0:00'
}

function timeToMinutes(hhmm: string) {
  const [h, m] = hhmm.split(':').map(Number)
  return h * 60 + m
}

const canSave = computed(() => !!form.description && !!form.start && !!form.finish)

function focusNext(field: string) {
  const map: Record<string, any> = {
    task: taskRef, desc: descRef, subdesc: subdescRef,
    start: startRef, finish: finishRef,
  }
  const el = map[field]?.value?.$el?.querySelector('input')
  el?.focus()
}

async function save() {
  if (!canSave.value) return
  emit('save', {
    clientId:        undefined, // resolved by parent via client name
    clientName:      form.clientName,
    task:            form.task,
    description:     form.description,
    subDescription:  form.subDescription,
    date:            ui.currentDate,
    start:           form.start,
    finish:          form.finish,
    duration:        form.duration,
    durationMinutes: form.durationMinutes,
    invoiced:        false,
  } as Partial<TimeEntry> & { clientName: string })

  const savedFinish = form.finish
  form.clientName = ''
  form.task = ''
  form.description = ''
  form.subDescription = ''
  form.start = savedFinish
  form.finish = ''
  form.duration = '0:00'
  form.durationMinutes = 0
}
</script>

<style scoped>
.new-row td { background: rgba(0,0,0,.02); }
</style>
