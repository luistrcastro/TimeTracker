<template>
  <div>
    <!-- Topbar -->
    <div class="d-flex align-center gap-2 mb-3">
      <v-btn icon="mdi-chevron-left" variant="text" size="small" @click="prevDay" />
      <v-btn variant="text" size="small" @click="goToday">Today</v-btn>
      <v-btn icon="mdi-chevron-right" variant="text" size="small" @click="nextDay" />
      <span class="font-weight-medium">{{ formattedDate }}</span>
      <v-spacer />
      <span class="text-medium-emphasis text-body-2 mr-2">Today: {{ todayTotal }} | Week: {{ weekTotal }}</span>
      <v-btn size="small" variant="outlined" prepend-icon="mdi-content-copy" @click="$emit('copyFrom')">Copy from…</v-btn>
    </div>

    <v-table density="compact" class="entry-table">
      <thead>
        <tr>
          <th @click="ui.cycleSort('project')" class="sortable">Client {{ sortIndicator('project') }}</th>
          <th @click="ui.cycleSort('subProject')" class="sortable">Task {{ sortIndicator('subProject') }}</th>
          <th>Description</th>
          <th @click="ui.cycleSort('start')" class="sortable">Start {{ sortIndicator('start') }}</th>
          <th>Finish</th>
          <th>Duration</th>
          <th>Acc</th>
          <th>Inv</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <template v-for="row in displayRows" :key="row.type === 'gap' ? 'gap-' + row.afterId : row.id">
          <tr v-if="row.type === 'gap'" class="gap-row">
            <td colspan="9" class="text-center text-caption text-medium-emphasis py-1">
              ⟵ {{ row.minutes }}m gap ⟶
            </td>
          </tr>
          <tr
            v-else
            :class="rowClass(row)"
            @dblclick="openEdit(row.id!)"
          >
            <td>{{ clientName(row.clientId) }}</td>
            <td>{{ row.task }}</td>
            <td>{{ row.description }}</td>
            <td>{{ fmt.formatTime(row.start) }}</td>
            <td>{{ fmt.formatTime(row.finish) }}</td>
            <td>{{ row.duration }}</td>
            <td>{{ row.accTime }}</td>
            <td>
              <span :class="row.invoiced ? 'invoiced-yes' : 'invoiced-no'">
                {{ row.invoiced ? '✓' : '✗' }}
              </span>
            </td>
            <td class="actions-cell">
              <v-btn icon="mdi-pencil"       size="x-small" variant="text" @click.stop="openEdit(row.id!)" />
              <v-btn icon="mdi-content-copy" size="x-small" variant="text" title="Prefill entry row" @click.stop="prefillRow(row)" />
              <v-btn icon="mdi-delete"       size="x-small" variant="text" color="error" @click.stop="contractor.remove(row.id!)" />
            </td>
          </tr>
        </template>
        <ContractorEntryRowNew :prefill-start="prefillStart" :prefill="prefillData" />
      </tbody>
    </v-table>

    <ContractorEntryEditDialog v-model="editDialog" :entry="editEntry" />

    <v-snackbar v-model="showUndo" :timeout="5000" location="bottom right">
      Entry deleted.
      <template #actions>
        <v-btn color="primary" variant="text" @click="contractor.undo()">Undo</v-btn>
      </template>
    </v-snackbar>
  </div>
</template>

<script setup lang="ts">
import type { TimeEntry } from '~/types'

const emit = defineEmits<{ copyFrom: [] }>()

const contractor = useContractorStore()
const ui = useUiStore()
const fmt = useTimeFormat()
const { detectGapsAndOverlaps } = useGapOverlap()

const editDialog = ref(false)
const editEntry  = ref<TimeEntry | null>(null)

interface PrefillData { clientId: string | null; taskId: string | null; description: string }
const prefillData = ref<PrefillData | null>(null)

function prefillRow(row: DisplayRow) {
  prefillData.value = {
    clientId:    row.clientId ?? null,
    taskId:      row.clientTaskId ?? null,
    description: row.description ?? '',
  }
}

const showUndo = computed({
  get: () => !!contractor.deletedEntry,
  set: () => {},
})

const formattedDate = computed(() => {
  const d = new Date(ui.currentDate + 'T00:00:00')
  return d.toLocaleDateString('en-CA', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })
})

const clientById = computed(() =>
  Object.fromEntries(contractor.clients.map(c => [c.id, c.name]))
)

const clientName = (id?: string | null) => id ? (clientById.value[id] ?? '') : ''

const dayEntries = computed(() =>
  contractor.entries.filter(e => e.date === ui.currentDate)
)

const todayTotal = computed(() => {
  const total = dayEntries.value.reduce((s, e) => s + (e.durationMinutes ?? 0), 0)
  return `${Math.floor(total / 60)}h ${total % 60}m`
})

const weekTotal = computed(() => {
  const d = new Date(ui.currentDate + 'T00:00:00')
  // Week is Sat–Fri; find the Saturday on or before currentDate
  const dow = d.getDay() // 0=Sun..6=Sat
  const satOffset = dow === 6 ? 0 : -(dow + 1)
  const weekStart = new Date(d)
  weekStart.setDate(d.getDate() + satOffset)
  const weekEnd = new Date(weekStart)
  weekEnd.setDate(weekStart.getDate() + 6)
  const start = weekStart.toISOString().slice(0, 10)
  const end = weekEnd.toISOString().slice(0, 10)
  const total = contractor.entries
    .filter(e => e.date >= start && e.date <= end)
    .reduce((s, e) => s + (e.durationMinutes ?? 0), 0)
  return `${Math.floor(total / 60)}h ${total % 60}m`
})

const chronologicalEntries = computed(() =>
  [...dayEntries.value].sort((a, b) => (a.start ?? '') > (b.start ?? '') ? 1 : -1)
)

const prefillStart = computed(() => {
  const last = [...chronologicalEntries.value].reverse().find(e => e.finish)
  return last?.finish ?? ''
})

const accByEntry = computed(() => {
  const map: Record<string, string> = {}
  let total = 0
  chronologicalEntries.value.forEach(e => {
    total += e.durationMinutes ?? 0
    map[e.id] = `${Math.floor(total / 60)}:${String(total % 60).padStart(2, '0')}`
  })
  return map
})

const sortedEntries = computed(() => {
  const sorted = [...dayEntries.value]
  if (ui.sortCol && ui.sortDir) {
    sorted.sort((a: any, b: any) => {
      const av = (ui.sortCol === 'project' ? clientName(a.clientId) : a[ui.sortCol!]) ?? ''
      const bv = (ui.sortCol === 'project' ? clientName(b.clientId) : b[ui.sortCol!]) ?? ''
      const cmp = av < bv ? -1 : av > bv ? 1 : 0
      return ui.sortDir === 'asc' ? cmp : -cmp
    })
  } else {
    sorted.sort((a, b) => (a.start ?? '') > (b.start ?? '') ? 1 : -1)
  }
  return sorted
})

interface DisplayRow {
  type?: 'gap'
  id?: string
  afterId?: string
  minutes?: number
  clientId?: string | null
  clientTaskId?: string | null
  task?: string
  description?: string
  start?: string
  finish?: string
  duration?: string
  accTime?: string
  invoiced?: boolean
  _overlap?: boolean
}

const displayRows = computed((): DisplayRow[] => {
  const { gaps, overlaps } = detectGapsAndOverlaps(dayEntries.value)
  const gapMap = new Map(gaps.map(g => [g.afterId, g]))
  const rows: DisplayRow[] = []
  sortedEntries.value.forEach(e => {
    rows.push({
      ...e,
      accTime: accByEntry.value[e.id] ?? '0:00',
      _overlap: overlaps.has(e.id),
    })
    const gap = gapMap.get(e.id)
    if (gap) rows.push({ type: 'gap', id: `gap-${e.id}`, afterId: gap.afterId, minutes: gap.minutes })
  })
  return rows
})

function sortIndicator(col: string) {
  if (ui.sortCol !== col) return ''
  return ui.sortDir === 'asc' ? '↑' : '↓'
}

function rowClass(row: DisplayRow) {
  return row._overlap ? 'row-overlap' : ''
}

function openEdit(id: string) {
  editEntry.value = contractor.entries.find(e => e.id === id) ?? null
  editDialog.value = true
}

function prevDay() {
  const d = new Date(ui.currentDate + 'T00:00:00')
  d.setDate(d.getDate() - 1)
  ui.setDate(d.toISOString().slice(0, 10))
}

function nextDay() {
  const d = new Date(ui.currentDate + 'T00:00:00')
  d.setDate(d.getDate() + 1)
  ui.setDate(d.toISOString().slice(0, 10))
}

function goToday() {
  ui.setDate(new Date().toISOString().slice(0, 10))
}

onMounted(async () => {
  await Promise.all([contractor.loadEntries(), contractor.loadClients()])
})
watch(() => ui.currentDate, (date) => contractor.loadEntries(date))
</script>

<style scoped>
.sortable { cursor: pointer; user-select: none; }
.sortable:hover { background: rgba(0,0,0,.04); }
.gap-row td { background: rgba(245, 158, 11, .08); color: #b45309; }
.row-overlap { background: rgba(239, 68, 68, .08); }
.invoiced-yes { color: #16a34a; font-weight: 600; }
.invoiced-no { color: #9ca3af; }
.actions-cell { white-space: nowrap; }
</style>
