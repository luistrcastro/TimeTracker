<template>
  <div>
    <div class="d-flex align-center gap-2 mb-4">
      <span class="font-weight-medium">{{ ui.currentDate }} — Compiled View</span>
      <v-spacer />
      <v-btn size="small" variant="outlined" prepend-icon="mdi-content-copy" @click="copyAll">Copy All</v-btn>
    </div>

    <v-table density="compact">
      <thead>
        <tr>
          <th>Client</th><th>Task</th><th>Hours</th><th>Comments</th><th></th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="row in compiledRows" :key="row.key">
          <td>{{ row.clientName }}</td>
          <td>{{ row.task }}</td>
          <td>{{ row.hoursDecimal }}</td>
          <td>{{ row.comments }}</td>
          <td>
            <v-btn icon="mdi-content-copy" size="x-small" variant="text" @click="copyRow(row.comments)" />
          </td>
        </tr>
      </tbody>
    </v-table>

    <v-snackbar v-model="copied" :timeout="2000" location="bottom right">Copied!</v-snackbar>
  </div>
</template>

<script setup lang="ts">
const ui = useUiStore()
const contractor = useContractorStore()
const { minutesToDecimal } = useTimeFormat()

useShortcuts()

const copied = ref(false)

const dayEntries = computed(() =>
  contractor.entries.filter(e => e.date === ui.currentDate)
)

const clientName = (id?: string | null) =>
  id ? (contractor.clients.find(c => c.id === id)?.name ?? '') : ''

const compiledRows = computed(() => {
  const map = new Map<string, { clientName: string; task: string; minutes: number; parts: string[] }>()

  dayEntries.value
    .filter(e => e.start && e.finish)
    .sort((a, b) => (a.start ?? '') > (b.start ?? '') ? 1 : -1)
    .forEach(e => {
      const key = `${e.clientId ?? ''}::${e.task ?? ''}`
      if (!map.has(key)) {
        map.set(key, { clientName: clientName(e.clientId), task: e.task ?? '', minutes: 0, parts: [] })
      }
      const row = map.get(key)!
      row.minutes += e.durationMinutes ?? 0
      const dec = minutesToDecimal(e.durationMinutes ?? 0)
      const part = `(${dec}) ${e.description}${e.subDescription ? ' - ' + e.subDescription : ''}`
      row.parts.push(part)
    })

  return [...map.entries()].map(([key, v]) => ({
    key,
    clientName: v.clientName,
    task: v.task,
    hoursDecimal: minutesToDecimal(v.minutes),
    comments: v.parts.join(', '),
  }))
})

async function copyRow(text: string) {
  await navigator.clipboard.writeText(text)
  copied.value = true
}

async function copyAll() {
  const lines = compiledRows.value.map(r => `${r.clientName}\t${r.task}\t${r.hoursDecimal}\t${r.comments}`)
  await navigator.clipboard.writeText(lines.join('\n'))
  copied.value = true
}

onMounted(async () => {
  await Promise.all([contractor.loadEntries(), contractor.loadClients()])
})
</script>
