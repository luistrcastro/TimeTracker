<template>
  <v-dialog v-model="model" max-width="960">
    <v-card>
      <v-card-title class="d-flex align-center gap-2">
        Split Entry
        <span v-if="entry" class="text-body-2 text-medium-emphasis font-weight-regular">
          {{ entry.project }}{{ entry.subProject ? ' · ' + entry.subProject : '' }} — {{ entry.description }} ({{ entry.duration }})
        </span>
      </v-card-title>

      <v-card-text>
        <div class="d-flex gap-4 mb-3 text-body-2">
          <span>Original: <strong>{{ originalDuration }}</strong></span>
          <span>Used: <strong>{{ usedDuration }}</strong></span>
          <span>Remaining: <strong :class="remainingClass">{{ remainingDisplay }}</strong></span>
        </div>

        <v-table density="compact">
          <thead>
            <tr>
              <th>Project</th>
              <th>Sub-project</th>
              <th>Description</th>
              <th>Sub-description</th>
              <th>Start</th>
              <th>Finish</th>
              <th>Duration</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(row, i) in rows" :key="row._key">
              <td class="split-cell">
                <RepliconProjectSelect
                  v-model="row.projectId"
                  variant="underlined"
                  placeholder="Project"
                  hide-details
                  @update:model-value="row.taskId = null"
                />
              </td>
              <td class="split-cell">
                <RepliconSubProjectSelect
                  v-model="row.taskId"
                  :project-id="row.projectId"
                  variant="underlined"
                  placeholder="Sub-project"
                  hide-details
                />
              </td>
              <td class="split-cell">
                <v-text-field v-model="row.description" variant="underlined" hide-details placeholder="Description" density="compact" />
              </td>
              <td class="split-cell">
                <v-text-field v-model="row.subDescription" variant="underlined" hide-details placeholder="Sub-description" density="compact" />
              </td>
              <td class="split-cell-time">
                <v-text-field v-model="row.start" type="time" variant="underlined" hide-details density="compact" />
              </td>
              <td class="split-cell-time">
                <v-text-field v-model="row.finish" type="time" variant="underlined" hide-details density="compact" />
              </td>
              <td class="text-body-2">{{ rowDuration(row) }}</td>
              <td>
                <v-btn icon="mdi-close" size="x-small" variant="text" :disabled="rows.length <= 1" @click="removeRow(i)" />
              </td>
            </tr>
          </tbody>
        </v-table>

        <v-btn size="small" variant="text" prepend-icon="mdi-plus" class="mt-1" @click="addRow">Add Row</v-btn>
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
          Cascade {{ cascadeDeltaLabel }} to {{ cascadeCount }} row{{ cascadeCount !== 1 ? 's' : '' }}
        </v-btn>
        <v-spacer />
        <v-btn variant="text" @click="model = false">Cancel</v-btn>
        <v-btn color="primary" @click="save(false)">Save Split</v-btn>
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

const emit = defineEmits<{ 'update:modelValue': [boolean]; split: [] }>()

const replicon = useRepliconStore()

const model = computed({
  get: () => props.modelValue,
  set: (v) => emit('update:modelValue', v),
})

interface SplitRow {
  _key: number
  projectId: string | null
  taskId: string | null
  description: string
  subDescription: string
  start: string
  finish: string
  logged: boolean
}

let _keyCounter = 0
const rows = ref<SplitRow[]>([])
const originalFinish = ref('')

watch(() => props.entry, (e) => {
  if (!e) return
  const project = replicon.projects.find(p => p.code === (e.project ?? ''))
  const task    = project?.tasks.find(t => t.id === e.repliconTaskId) ??
                  project?.tasks.find(t => t.name === (e.subProject ?? ''))
  originalFinish.value = e.finish ?? ''
  rows.value = [{
    _key:           ++_keyCounter,
    projectId:      project?.id ?? null,
    taskId:         task?.id ?? null,
    description:    e.description,
    subDescription: e.subDescription ?? '',
    start:          e.start ?? '',
    finish:         e.finish ?? '',
    logged:         e.logged ?? false,
  }]
}, { immediate: true })

function toMins(hhmm: string): number {
  if (!hhmm) return 0
  const [h, m] = hhmm.split(':').map(Number)
  return h * 60 + m
}

function toHHMM(mins: number): string {
  if (mins < 0) mins += 24 * 60
  return `${String(Math.floor(mins / 60)).padStart(2, '0')}:${String(mins % 60).padStart(2, '0')}`
}

function rowDurationMins(row: SplitRow): number {
  const d = toMins(row.finish) - toMins(row.start)
  return row.start && row.finish && d > 0 ? d : 0
}

function rowDuration(row: SplitRow): string {
  const m = rowDurationMins(row)
  return `${Math.floor(m / 60)}:${String(m % 60).padStart(2, '0')}`
}

const originalDuration = computed(() => {
  const m = props.entry?.durationMinutes ?? 0
  return `${Math.floor(m / 60)}:${String(m % 60).padStart(2, '0')}`
})

const usedMins = computed(() => rows.value.reduce((s, r) => s + rowDurationMins(r), 0))

const usedDuration = computed(() => {
  const m = usedMins.value
  return `${Math.floor(m / 60)}:${String(m % 60).padStart(2, '0')}`
})

const remainingMins = computed(() => (props.entry?.durationMinutes ?? 0) - usedMins.value)

const remainingDisplay = computed(() => {
  const abs = Math.abs(remainingMins.value)
  return `${remainingMins.value < 0 ? '-' : ''}${Math.floor(abs / 60)}:${String(abs % 60).padStart(2, '0')}`
})

const remainingClass = computed(() => {
  if (remainingMins.value === 0) return 'text-success'
  return remainingMins.value < 0 ? 'text-error' : ''
})

const lastSplitFinish = computed(() => {
  for (let i = rows.value.length - 1; i >= 0; i--) {
    if (rows.value[i].finish) return rows.value[i].finish
  }
  return ''
})

const cascadeDeltaMins = computed(() => {
  if (!lastSplitFinish.value || !originalFinish.value) return 0
  return toMins(lastSplitFinish.value) - toMins(originalFinish.value)
})

const cascadeDeltaLabel = computed(() => {
  const d = cascadeDeltaMins.value
  if (d === 0) return ''
  const sign = d > 0 ? '+' : '−'
  const abs = Math.abs(d)
  const h = Math.floor(abs / 60)
  return `${sign}${h > 0 ? h + 'h ' : ''}${abs % 60}m`
})

const cascadeCount = computed(() => {
  if (!cascadeDeltaMins.value || !originalFinish.value) return null
  return replicon.entries.filter(e =>
    e.date === props.entry?.date &&
    e.id !== props.entry?.id &&
    e.start &&
    e.start >= originalFinish.value
  ).length
})

function addRow() {
  const first = rows.value[0]
  const last  = rows.value[rows.value.length - 1]
  rows.value.push({
    _key:           ++_keyCounter,
    projectId:      first?.projectId ?? null,
    taskId:         first?.taskId ?? null,
    description:    first?.description ?? '',
    subDescription: first?.subDescription ?? '',
    start:          last?.finish ?? '',
    finish:         '',
    logged:         false,
  })
}

function removeRow(i: number) {
  if (rows.value.length <= 1) return
  rows.value.splice(i, 1)
}

async function save(cascade: boolean) {
  if (!props.entry) return

  for (const r of rows.value) {
    if (!r.description || !r.start || !r.finish) return
    if (toMins(r.finish) <= toMins(r.start)) return
  }

  const date = props.entry.date

  if (cascade && cascadeDeltaMins.value !== 0) {
    const affected = replicon.entries.filter(e =>
      e.date === date && e.id !== props.entry!.id && e.start && e.start >= originalFinish.value
    )
    for (const ae of affected) {
      await replicon.update(ae.id, {
        ...ae,
        start:  toHHMM(toMins(ae.start!) + cascadeDeltaMins.value),
        finish: ae.finish ? toHHMM(toMins(ae.finish) + cascadeDeltaMins.value) : ae.finish,
      })
    }
  }

  await replicon.remove(props.entry.id, true)

  for (const r of rows.value) {
    const project = replicon.projects.find(p => p.id === r.projectId)
    const task    = project?.tasks.find(t => t.id === r.taskId)
    await replicon.create({
      date,
      project:         project?.code ?? '',
      subProject:      task?.name ?? '',
      repliconTaskId:  r.taskId,
      description:     r.description,
      subDescription:  r.subDescription,
      start:           r.start,
      finish:          r.finish,
      duration:        rowDuration(r),
      durationMinutes: rowDurationMins(r),
      logged:          r.logged,
    })
  }

  model.value = false
  emit('split')
}
</script>

<style scoped>
.split-cell      { min-width: 130px; }
.split-cell-time { min-width: 100px; }
</style>
