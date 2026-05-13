<template>
  <div>
    <div class="d-flex align-center gap-2 mb-4">
      <span class="font-weight-medium">{{ ui.currentDate }} — Replicon Compiled</span>
      <v-chip :color="replicon.credsOk ? 'success' : 'warning'" size="x-small" variant="tonal">
        {{ replicon.credsOk ? 'Credentials OK' : 'Credentials not set' }}
      </v-chip>
      <v-spacer />
      <v-btn size="small" variant="outlined" prepend-icon="mdi-content-copy" @click="copyAll">Copy All</v-btn>
      <v-btn
        size="small" color="primary" prepend-icon="mdi-send"
        :loading="submitting"
        :disabled="!replicon.credsOk || !compiledRows.length"
        @click="submit"
      >Submit to Replicon</v-btn>
    </div>

    <v-table density="compact">
      <thead>
        <tr><th>Project</th><th>Sub-project</th><th>Hours</th><th>Comments</th><th></th></tr>
      </thead>
      <tbody>
        <tr v-for="row in compiledRows" :key="row.key">
          <td>{{ row.project }}</td>
          <td>{{ row.subProject }}</td>
          <td>
            {{ row.hoursDecimal }}
            <v-icon v-if="submitResultMap[row.key] === 'ok'" color="success" size="small">mdi-check-circle</v-icon>
            <v-icon v-else-if="submitResultMap[row.key] === 'err'" color="error" size="small">mdi-alert-circle</v-icon>
          </td>
          <td class="text-caption">{{ row.comments }}</td>
          <td>
            <v-btn icon="mdi-content-copy" size="x-small" variant="text" @click="copyRow(row.comments)" />
          </td>
        </tr>
        <tr v-if="!compiledRows.length">
          <td colspan="5" class="text-center text-medium-emphasis py-4">No entries with times for this date.</td>
        </tr>
      </tbody>
    </v-table>

    <v-snackbar v-model="copied" :timeout="2000" location="bottom right">Copied!</v-snackbar>
    <v-snackbar v-model="submitDone" :timeout="3000" location="bottom right" color="success">Submitted to Replicon.</v-snackbar>
  </div>
</template>

<script setup lang="ts">
const ui = useUiStore()
const replicon = useRepliconStore()
const { minutesToDecimal } = useTimeFormat()

useShortcuts()

const copied = ref(false)
const submitting = ref(false)
const submitDone = ref(false)
const submitResultMap = ref<Record<string, string>>({})

const dayEntries = computed(() =>
  replicon.entries.filter(e => e.date === ui.currentDate && e.start && e.finish)
    .sort((a, b) => (a.start ?? '') > (b.start ?? '') ? 1 : -1)
)

const compiledRows = computed(() => {
  const map = new Map<string, { project: string; subProject: string; minutes: number; parts: string[] }>()
  dayEntries.value.forEach(e => {
    const key = `${e.project ?? ''}::${e.subProject ?? ''}`
    if (!map.has(key)) map.set(key, { project: e.project ?? '', subProject: e.subProject ?? '', minutes: 0, parts: [] })
    const row = map.get(key)!
    row.minutes += e.durationMinutes ?? 0
    const dec = minutesToDecimal(e.durationMinutes ?? 0)
    row.parts.push(`(${dec}) ${e.description}${e.subDescription ? ' - ' + e.subDescription : ''}`)
  })
  return [...map.entries()].map(([key, v]) => ({
    key,
    project: v.project,
    subProject: v.subProject,
    hoursDecimal: minutesToDecimal(v.minutes),
    comments: v.parts.join(', '),
  }))
})

async function copyRow(text: string) {
  await navigator.clipboard.writeText(text)
  copied.value = true
}

async function copyAll() {
  const lines = compiledRows.value.map(r => `${r.project}\t${r.subProject}\t${r.hoursDecimal}\t${r.comments}`)
  await navigator.clipboard.writeText(lines.join('\n'))
  copied.value = true
}

async function submit() {
  const rows = compiledRows.value.map(r => ({
    projectId: r.project,
    taskId: r.subProject,
    rowIndex: replicon.rowMap[`${r.project}:${r.subProject}`] ?? 0,
    hours: r.hoursDecimal,
    comment: r.comments,
  }))
  submitting.value = true
  try {
    const results = await replicon.submit(rows, ui.currentDate)
    results.forEach((res: any, i: number) => {
      const key = compiledRows.value[i]?.key
      if (key) submitResultMap.value = { ...submitResultMap.value, [key]: res.ok ? 'ok' : 'err' }
    })
    submitDone.value = true
  } finally {
    submitting.value = false
  }
}

onMounted(async () => {
  await Promise.all([replicon.loadEntries(), replicon.loadCredentials(), replicon.loadRowMap()])
})
watch(() => ui.currentDate, () => replicon.loadEntries(ui.currentDate))
</script>
