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
            <th>Project</th><th>Sub-project</th><th>Description</th>
            <th>Start</th><th>Finish</th><th>Duration</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="e in day.entries" :key="e.id">
            <td>{{ e.project }}</td>
            <td>{{ e.subProject }}</td>
            <td>{{ e.description }}{{ e.subDescription ? ' — ' + e.subDescription : '' }}</td>
            <td>{{ fmt.formatTime(e.start) }}</td>
            <td>{{ fmt.formatTime(e.finish) }}</td>
            <td>{{ e.duration }}</td>
          </tr>
        </tbody>
      </v-table>
      <p v-else class="text-caption text-medium-emphasis">No entries</p>
    </div>
  </div>
</template>

<script setup lang="ts">
const ui = useUiStore()
const replicon = useRepliconStore()
const fmt = useTimeFormat()

useShortcuts()

const weekStart = computed(() => {
  const d = new Date(ui.currentDate + 'T00:00:00')
  const dow = d.getDay()
  const sat = new Date(d)
  sat.setDate(d.getDate() - ((dow + 1) % 7))
  return sat
})

const weekDays = computed(() =>
  Array.from({ length: 7 }, (_, i) => {
    const d = new Date(weekStart.value)
    d.setDate(d.getDate() + i)
    const date = d.toISOString().slice(0, 10)
    const entries = replicon.entries.filter(e => e.date === date)
      .sort((a, b) => (a.start ?? '') > (b.start ?? '') ? 1 : -1)
    const totalMins = entries.reduce((s, e) => s + (e.durationMinutes ?? 0), 0)
    return {
      date,
      label: d.toLocaleDateString('en-CA', { weekday: 'long', month: 'short', day: 'numeric' }),
      entries,
      total: `${Math.floor(totalMins / 60)}h ${totalMins % 60}m`,
    }
  })
)

const weekLabel = computed(() => {
  const end = new Date(weekStart.value)
  end.setDate(end.getDate() + 6)
  return `${weekStart.value.toLocaleDateString('en-CA', { month: 'short', day: 'numeric' })} – ${end.toLocaleDateString('en-CA', { month: 'short', day: 'numeric', year: 'numeric' })}`
})

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

onMounted(() => replicon.loadEntries())
</script>
