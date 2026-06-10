<template>
  <v-dialog v-model="model" max-width="600">
    <v-card @keydown.enter.exact="handleEnter">
      <v-card-title>Edit Entry</v-card-title>
      <v-card-text>
        <v-row dense>
          <v-col cols="6">
            <ContractorClientSelect
              v-model="form.clientId"
              label="Client"
              variant="outlined"
              @update:model-value="form.taskId = null"
            />
          </v-col>
          <v-col cols="6">
            <ContractorTaskSelect
              v-model="form.taskId"
              :client-id="form.clientId"
              label="Task"
              variant="outlined"
            />
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
          <v-col cols="4">
            <v-text-field v-model="form.start" type="time" label="Start" variant="outlined" density="compact" @input="calcDuration" />
          </v-col>
          <v-col cols="4">
            <v-text-field v-model="form.finish" type="time" label="Finish" variant="outlined" density="compact" @input="calcDuration" />
          </v-col>
          <v-col cols="4">
            <v-text-field :model-value="form.duration" label="Duration" variant="outlined" density="compact" readonly />
          </v-col>
        </v-row>

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
        <v-btn variant="text" :disabled="saving" @click="model = false">Cancel</v-btn>
        <v-btn color="primary" :loading="saving" @click="save(false)">Save Changes</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup lang="ts">
import type { TimeEntry } from '~/types'

const props = defineProps<{
  modelValue: boolean
  entry: TimeEntry | null
}>()

const emit = defineEmits<{ 'update:modelValue': [boolean] }>()

const contractor = useContractorStore()

const saving = ref(false)

const model = computed({
  get: () => props.modelValue,
  set: (v) => emit('update:modelValue', v),
})

const originalFinish = ref('')

const form = reactive({
  clientId: null as string | null,
  taskId:   null as string | null,
  description:    '',
  subDescription: '',
  date:     '',
  start:    '',
  finish:   '',
  duration: '0:00',
  durationMinutes: 0,
})

watch(() => props.entry, (e) => {
  if (!e) return
  const client = contractor.clients.find(c => c.id === e.clientId)
  const task   = client?.tasks?.find(t => t.id === e.clientTaskId) ??
                 client?.tasks?.find(t => t.name === (e.task ?? ''))
  form.clientId       = client?.id ?? null
  form.taskId         = task?.id ?? null
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
  return contractor.entries.filter(e =>
    e.date === form.date && e.id !== props.entry?.id && e.start && e.start >= originalFinish.value
  ).length
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

function handleEnter(e: KeyboardEvent) {
  // Don't submit if focus is inside an autocomplete overlay (dropdown open)
  if ((document.activeElement as HTMLElement)?.closest('.v-overlay__content')) return
  // Don't submit if a button is focused (let it click naturally)
  if ((e.target as HTMLElement).tagName === 'BUTTON') return
  save(false)
}

async function save(cascade: boolean) {
  if (!props.entry) return
  saving.value = true
  try {
    const task = contractor.clients
      .find(c => c.id === form.clientId)?.tasks
      ?.find(t => t.id === form.taskId)

    if (cascade && cascadeDelta.value !== 0) {
      const affected = contractor.entries.filter(e =>
        e.date === form.date && e.id !== props.entry!.id && e.start && e.start >= originalFinish.value
      )
      for (const ae of affected) {
        await contractor.update(ae.id, {
          start:  minutesToHHMM(timeToMinutes(ae.start!) + cascadeDelta.value),
          finish: ae.finish ? minutesToHHMM(timeToMinutes(ae.finish) + cascadeDelta.value) : ae.finish,
        })
      }
    }

    await contractor.update(props.entry.id, {
      clientId:        form.clientId,
      clientTaskId:    form.taskId,
      task:            task?.name ?? props.entry.task ?? '',
      description:     form.description,
      subDescription:  form.subDescription,
      date:            form.date,
      start:           form.start,
      finish:          form.finish,
      duration:        form.duration,
      durationMinutes: form.durationMinutes,
    })

    model.value = false
  } finally {
    saving.value = false
  }
}
</script>
