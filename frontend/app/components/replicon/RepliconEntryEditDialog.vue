<template>
  <v-dialog v-model="model" max-width="600">
    <v-card>
      <v-card-title>Edit Entry</v-card-title>
      <v-card-text>
        <v-row dense>
          <v-col cols="6">
            <RepliconProjectSelect
              v-model="form.projectId"
              variant="outlined"
              label="Project"
              @update:model-value="form.taskId = null"
            />
          </v-col>
          <v-col cols="6">
            <RepliconSubProjectSelect
              v-model="form.taskId"
              :project-id="form.projectId"
              variant="outlined"
              label="Sub-project"
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
          <v-col cols="12">
            <v-text-field v-model="form.subDescription" label="Sub-description" variant="outlined" density="compact" />
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
          <v-col cols="4">
            <v-checkbox v-model="form.logged" label="Logged" density="compact" hide-details />
          </v-col>
        </v-row>

      </v-card-text>
      <v-card-actions>
        <v-btn
          v-if="cascadeCount !== null"
          :disabled="cascadeCount === 0"
          variant="outlined"
          size="small"
          color="warning"
          @click="save(true)"
        >
          Cascade {{ cascadeDelta > 0 ? '+' : '' }}{{ cascadeDelta }}m to {{ cascadeCount }} row{{ cascadeCount !== 1 ? 's' : '' }}
        </v-btn>
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
}>()

const emit = defineEmits<{ 'update:modelValue': [boolean] }>()

const replicon = useRepliconStore()

const model = computed({
  get: () => props.modelValue,
  set: (v) => emit('update:modelValue', v),
})

const originalFinish = ref('')

const form = reactive({
  projectId: null as string | null,
  taskId:    null as string | null,
  description:    '',
  subDescription: '',
  date:     '',
  start:    '',
  finish:   '',
  duration: '0:00',
  durationMinutes: 0,
  logged:   false,
})

watch(() => props.entry, (e) => {
  if (!e) return
  const project = replicon.projects.find(p => p.code === (e.project ?? ''))
  const task    = project?.tasks.find(t => t.id === e.repliconTaskId) ??
                  project?.tasks.find(t => t.name === (e.subProject ?? ''))
  form.projectId      = project?.id ?? null
  form.taskId         = task?.id ?? null
  form.description    = e.description
  form.subDescription = e.subDescription ?? ''
  form.date           = e.date
  form.start          = e.start ?? ''
  form.finish         = e.finish ?? ''
  form.duration       = e.duration
  form.durationMinutes = e.durationMinutes ?? 0
  form.logged         = e.logged ?? false
  originalFinish.value = e.finish ?? ''
}, { immediate: true })

const cascadeDelta = computed(() => {
  if (!form.finish || !originalFinish.value) return 0
  return timeToMinutes(form.finish) - timeToMinutes(originalFinish.value)
})

const cascadeCount = computed(() => {
  if (!originalFinish.value || cascadeDelta.value === 0) return null
  return replicon.entries.filter(e =>
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

async function save(cascade: boolean) {
  if (!props.entry) return

  const project = replicon.projects.find(p => p.id === form.projectId)
  const task    = project?.tasks.find(t => t.id === form.taskId)

  if (cascade && cascadeDelta.value !== 0) {
    const affected = replicon.entries.filter(e =>
      e.date === form.date && e.id !== props.entry!.id && e.start && e.start >= originalFinish.value
    )
    for (const ae of affected) {
      await replicon.update(ae.id, {
        ...ae,
        start:  minutesToHHMM(timeToMinutes(ae.start!) + cascadeDelta.value),
        finish: ae.finish ? minutesToHHMM(timeToMinutes(ae.finish) + cascadeDelta.value) : ae.finish,
      })
    }
  }

  await replicon.update(props.entry.id, {
    project:         project?.code ?? props.entry.project ?? '',
    subProject:      task?.name    ?? props.entry.subProject ?? '',
    repliconTaskId:  form.taskId,
    description:     form.description,
    subDescription:  form.subDescription,
    date:            form.date,
    start:           form.start,
    finish:          form.finish,
    duration:        form.duration,
    durationMinutes: form.durationMinutes,
    logged:          form.logged,
  })

  model.value = false
}
</script>
