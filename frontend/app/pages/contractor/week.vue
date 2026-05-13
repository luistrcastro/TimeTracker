<template>
  <div>
    <div class="d-flex align-center gap-2 mb-4">
      <v-btn icon="mdi-chevron-left" variant="text" @click="prevWeek" />
      <span class="font-weight-medium">{{ weekLabel }}</span>
      <v-btn icon="mdi-chevron-right" variant="text" @click="nextWeek" />
    </div>

    <div v-for="day in weekDays" :key="day.date" class="mb-4">
      <div class="d-flex align-center gap-2 mb-1">
        <span class="font-weight-medium">{{ day.label }}</span>
        <v-chip size="x-small" variant="tonal">{{ day.total }}</v-chip>
      </div>
      <v-table density="compact" v-if="day.entries.length">
        <thead>
          <tr>
            <th>Client</th><th>Task</th><th>Description</th>
            <th>Start</th><th>Finish</th><th>Duration</th><th>Acc</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="e in day.entries" :key="e.id">
            <td>{{ clientName(e.clientId) }}</td>
            <td>{{ e.task }}</td>
            <td>{{ e.description }}{{ e.subDescription ? ' — ' + e.subDescription : '' }}</td>
            <td>{{ fmt.formatTime(e.start) }}</td>
            <td>{{ fmt.formatTime(e.finish) }}</td>
            <td>{{ e.duration }}</td>
            <td>{{ accFor(day.entries, e.id) }}</td>
          </tr>
        </tbody>
      </v-table>
      <p v-else class="text-caption text-medium-emphasis">No entries</p>
    </div>
  </div>
</template>

<script setup lang="ts">
import type { TimeEntry } from '~/types'

const ui = useUiStore()
const contractor = useContractorStore()
const fmt = useTimeFormat()

useShortcuts()

const clientName = (id?: string | null) =>
  id ? (contractor.clients.find(c => c.id === id)?.name ?? '') : ''

// Saturday-to-Friday week: find the most recent Saturday on or before currentDate
const weekStart = computed(() => {
  const d = new Date(ui.currentDate + 'T00:00:00')
  const dow = d.getDay() // 0=Sun..6=Sat
  // Days since last Saturday: Sat=0, Sun=1, Mon=2, ..., Fri=6
  const daysSinceSat = (dow + 1) % 7
  const sat = new Date(d)
  sat.setDate(d.getDate() - daysSinceSat)
  return sat
})

const weekDays = computed(() => {
  const days: {
    date: string
    label: string
    entries: TimeEntry[]
    total: string
  }[] = []
  for (let i = 0; i < 7; i++) {
    const d = new Date(weekStart.value)
    d.setDate(d.getDate() + i)
    const date = d.toISOString().slice(0, 10)
    const entries = contractor.entries
      .filter(e => e.date === date)
      .sort((a, b) => (a.start ?? '') > (b.start ?? '') ? 1 : -1)
    const totalMins = entries.reduce((s, e) => s + (e.durationMinutes ?? 0), 0)
    days.push({
      date,
      label: d.toLocaleDateString('en-CA', { weekday: 'long', month: 'short', day: 'numeric' }),
      entries,
      total: `${Math.floor(totalMins / 60)}h ${totalMins % 60}m`,
    })
  }
  return days
})

const weekLabel = computed(() => {
  const end = new Date(weekStart.value)
  end.setDate(end.getDate() + 6)
  const startLabel = weekStart.value.toLocaleDateString('en-CA', { month: 'short', day: 'numeric' })
  const endLabel = end.toLocaleDateString('en-CA', { month: 'short', day: 'numeric', year: 'numeric' })
  return `${startLabel} – ${endLabel}`
})

function accFor(entries: TimeEntry[], id: string) {
  let total = 0
  for (const e of entries) {
    total += e.durationMinutes ?? 0
    if (e.id === id) break
  }
  return `${Math.floor(total / 60)}:${String(total % 60).padStart(2, '0')}`
}

function prevWeek() {
  const d = new Date(ui.currentDate + 'T00:00:00')
  d.setDate(d.getDate() - 7)
  ui.setDate(d.toISOString().slice(0, 10))
}

function nextWeek() {
  const d = new Date(ui.currentDate + 'T00:00:00')
  d.setDate(d.getDate() + 7)
  ui.setDate(d.toISOString().slice(0, 10))
}

onMounted(async () => {
  await Promise.all([contractor.loadEntries(), contractor.loadClients()])
})
</script>
