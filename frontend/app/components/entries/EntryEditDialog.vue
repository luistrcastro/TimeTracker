<template>
  <v-dialog v-model="model" max-width="600">
    <v-card>
      <v-card-title>Edit Entry</v-card-title>
      <v-card-text>
        <v-row dense>
          <v-col cols="6">
            <AutocompleteInput
              v-model="form.clientName"
              :suggestions="clientNames"
              label="Client"
              variant="outlined"
              density="compact"
            />
          </v-col>
          <v-col cols="6">
            <v-text-field v-model="form.task" label="Task" variant="outlined" density="compact" />
          </v-col>
          <v-col cols="12">
            <v-text-field
              v-model="form.description"
              label="Description"
              variant="outlined"
              density="compact"
              :rules="[(v: string) => !!v || 'Required']"
            />
          </v-col>
          <v-col cols="12">
            <v-text-field v-model="form.subDescription" label="Sub-description" variant="outlined" density="compact" />
          </v-col>
          <v-col cols="4">
            <v-text-field v-model="form.start" type="time" label="Start" variant="outlined" density="compact" @input="calcDuration" />
          </v-col>
          <v-col cols="4">
            <v-text-field v-model="form.finish" type="time" label="Finish" variant="outlined" density="compact" @input="onFinishChange" />
          </v-col>
          <v-col cols="4">
            <v-text-field :model-value="form.duration" label="Duration" variant="outlined" density="compact" readonly />
          </v-col>
        </v-row>

        <!-- Cascade -->
        <div v-if="cascadeCount !== null" class="mt-2">
          <v-btn
            :disabled="cascadeCount === 0"
            variant="outlined"
            size="small"
            color="warning"
            @click="save(true)"
          >
            Cascade {{ cascadeDelta > 0 ? '+' : '' }}{{ cascadeDelta }}m to {{ cascadeCount }} row{{ cascadeCount !== 1 ? 's' : '' }}
          </v-btn>
        </div>
      </v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" @click="model = false">Cancel</v-btn>
        <v-btn color="primary" @click="save(false)">Save Changes</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup lang="ts">
import type { TimeEntry } from '~/types'

const props = defineProps<{
  modelValue: boolean
  entry: TimeEntry | null
  clients: { id: string; name: string }[]
  entries?: TimeEntry[]
}>()

const emit = defineEmits<{
  'update:modelValue': [boolean]
  saved: [entry: Partial<TimeEntry> & { id: string }]
}>()

const contractor = useContractorStore()

const allEntries = computed(() => props.entries ?? contractor.entries)

const model = computed({
  get: () => props.modelValue,
  set: (v) => emit('update:modelValue', v),
})

const clientNames = computed(() => props.clients.map(c => c.name))

const originalFinish = ref('')
const form = reactive({
  clientName: '',
  task: '',
  description: '',
  subDescription: '',
  date: '',
  start: '',
  finish: '',
  duration: '0:00',
  durationMinutes: 0,
})

watch(() => props.entry, (e) => {
  if (!e) return
  const client = props.clients.find(c => c.id === e.clientId)
  form.clientName     = client?.name ?? e.project ?? ''
  form.task           = e.task ?? e.subProject ?? ''
  form.description    = e.description
  form.subDescription = e.subDescription ?? ''
  form.date           = e.date
  form.start          = e.start ?? ''
  form.finish         = e.finish ?? ''
  form.duration       = e.duration
  form.durationMinutes = e.durationMinutes ?? 0
  originalFinish.value = e.finish ?? ''
}, { immediate: true })

const cascadeDelta = computed(() => {
  if (!form.finish || !originalFinish.value) return 0
  return timeToMinutes(form.finish) - timeToMinutes(originalFinish.value)
})

const cascadeCount = computed(() => {
  if (!originalFinish.value || cascadeDelta.value === 0) return null
  const affected = allEntries.value.filter(e =>
    e.date === form.date &&
    e.id !== props.entry?.id &&
    e.start && e.start >= originalFinish.value
  )
  return affected.length
})

function timeToMinutes(hhmm: string) {
  const [h, m] = hhmm.split(':').map(Number)
  return h * 60 + m
}

function minutesToHHMM(mins: number) {
  if (mins < 0) mins += 24 * 60
  return `${String(Math.floor(mins / 60)).padStart(2, '0')}:${String(mins % 60).padStart(2, '0')}`
}

function calcDuration() {
  if (form.start && form.finish) {
    const diff = timeToMinutes(form.finish) - timeToMinutes(form.start)
    if (diff > 0) {
      form.durationMinutes = diff
      form.duration = `${Math.floor(diff / 60)}:${String(diff % 60).padStart(2, '0')}`
      return
    }
  }
  form.durationMinutes = 0
  form.duration = '0:00'
}

function onFinishChange() {
  calcDuration()
}

async function save(cascade: boolean) {
  if (!props.entry) return
  const client = props.clients.find(c => c.name === form.clientName)

  const updated: Partial<TimeEntry> & { id: string } = {
    id:              props.entry.id,
    clientId:        client?.id ?? props.entry.clientId,
    task:            form.task,
    project:         form.clientName,
    subProject:      form.task,
    description:     form.description,
    subDescription:  form.subDescription,
    date:            form.date,
    start:           form.start,
    finish:          form.finish,
    duration:        form.duration,
    durationMinutes: form.durationMinutes,
  }

  if (cascade && cascadeDelta.value !== 0) {
    const affected = allEntries.value.filter(e =>
      e.date === form.date && e.id !== props.entry!.id && e.start && e.start >= originalFinish.value
    )
    for (const ae of affected) {
      const newStart  = minutesToHHMM(timeToMinutes(ae.start!) + cascadeDelta.value)
      const newFinish = ae.finish ? minutesToHHMM(timeToMinutes(ae.finish) + cascadeDelta.value) : ae.finish
      await contractor.update(ae.id, { start: newStart, finish: newFinish })
    }
  }

  emit('saved', updated)
}
</script>
