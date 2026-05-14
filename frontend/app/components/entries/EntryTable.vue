<template>
  <div>
    <!-- Topbar -->
    <div class="d-flex align-center gap-2 mb-3">
      <v-btn icon="mdi-chevron-left" variant="text" size="small" @click="prevDay" />
      <v-btn variant="text" size="small" @click="goToday">Today</v-btn>
      <v-btn icon="mdi-chevron-right" variant="text" size="small" @click="nextDay" />
      <span class="font-weight-medium">{{ formattedDate }}</span>
      <v-spacer />
      <span class="text-medium-emphasis text-body-2 mr-2">{{ todayTotal }}</span>
      <v-btn size="small" variant="outlined" prepend-icon="mdi-content-copy" @click="$emit('copyFrom')">Copy from…</v-btn>
    </div>

    <!-- Table -->
    <v-table density="compact" class="entry-table">
      <thead>
        <tr>
          <th @click="ui.cycleSort('project')" class="sortable">
            {{ props.variant === 'replicon' ? 'Project' : 'Client' }} {{ sortIndicator('project') }}
          </th>
          <th @click="ui.cycleSort('subProject')" class="sortable">
            {{ props.variant === 'replicon' ? 'Sub Project' : 'Task' }} {{ sortIndicator('subProject') }}
          </th>
          <th>Description</th>
          <th>Sub-Description</th>
          <th @click="ui.cycleSort('start')" class="sortable">
            Start {{ sortIndicator('start') }}
          </th>
          <th>Finish</th>
          <th>{{ props.variant === 'replicon' ? 'Time' : 'Duration' }}</th>
          <th>{{ props.variant === 'replicon' ? 'Acc Time' : 'Acc' }}</th>
          <th>{{ props.variant === 'replicon' ? 'Logged' : 'Inv' }}</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <template v-for="row in displayRows" :key="row.type === 'gap' ? 'gap-' + row.afterId : row.id">
          <!-- Gap indicator row -->
          <tr v-if="row.type === 'gap'" class="gap-row">
            <td colspan="10" class="text-center text-caption text-medium-emphasis py-1">
              ⟵ {{ row.minutes }}m gap ⟶
            </td>
          </tr>
          <!-- Entry row -->
          <tr
            v-else
            :class="rowClass(row)"
            @dblclick="$emit('edit', row.id)"
          >
            <td>{{ row.clientName }}</td>
            <td>{{ row.task }}</td>
            <td>{{ row.description }}</td>
            <td>{{ row.subDescription }}</td>
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
              <v-btn icon="mdi-pencil" size="x-small" variant="text" @click.stop="$emit('edit', row.id)" />
              <v-btn icon="mdi-content-copy" size="x-small" variant="text" @click.stop="$emit('duplicate', row.id)" />
              <v-btn icon="mdi-content-cut" size="x-small" variant="text" @click.stop="$emit('split', row.id)" />
              <v-btn icon="mdi-delete" size="x-small" variant="text" color="error" @click.stop="$emit('delete', row.id)" />
            </td>
          </tr>
        </template>
        <!-- New entry row -->
        <EntryRowNew
          :clients="clientNames"
          :tasks="taskSuggestions"
          :prefill-start="prefillStart"
          @save="$emit('save', $event)"
        />
      </tbody>
    </v-table>

    <!-- Undo toast -->
    <v-snackbar v-model="showUndo" :timeout="5000" location="bottom right">
      Entry deleted.
      <template #actions>
        <v-btn color="primary" variant="text" @click="$emit('undo')">Undo</v-btn>
      </template>
    </v-snackbar>
  </div>
</template>

<script setup lang="ts">
import type { TimeEntry } from '~/types'

const props = defineProps<{
  entries: TimeEntry[]
  clients: { id: string; name: string; tasks?: string[] }[]
  hasDeleted: boolean
  prefillStart: string
  variant?: 'replicon' | 'contractor'
}>()

const emit = defineEmits<{
  edit: [id: string]
  duplicate: [id: string]
  split: [id: string]
  delete: [id: string]
  save: [entry: Partial<TimeEntry>]
  undo: []
  copyFrom: []
}>()

const ui = useUiStore()
const fmt = useTimeFormat()
const { detectGapsAndOverlaps } = useGapOverlap()

const showUndo = computed({
  get: () => props.hasDeleted,
  set: () => {},
})

const formattedDate = computed(() => {
  const d = new Date(props.entries[0]?.date ?? ui.currentDate + 'T00:00:00')
  const dateStr = ui.currentDate
  const d2 = new Date(dateStr + 'T00:00:00')
  return d2.toLocaleDateString('en-CA', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })
})

const clientNames = computed(() => props.clients.map(c => c.name))

const taskSuggestions = computed(() => {
  const all = new Set<string>()
  props.clients.forEach(c => (c.tasks ?? []).forEach(t => all.add(t)))
  props.entries.forEach(e => {
    if (e.task) all.add(e.task)
    if (e.subProject) all.add(e.subProject)
  })
  return [...all]
})

// Build client name lookup
const clientById = computed(() => Object.fromEntries(props.clients.map(c => [c.id, c.name])))

function sortIndicator(col: string) {
  if (ui.sortCol !== col) return ''
  return ui.sortDir === 'asc' ? '↑' : '↓'
}

// Acc time: sum duration_minutes chronologically
const chronologicalEntries = computed(() =>
  [...props.entries].sort((a, b) => {
    if (a.start && b.start) return a.start > b.start ? 1 : -1
    return 0
  })
)

const accByEntry = computed(() => {
  const map: Record<string, string> = {}
  let totalMins = 0
  chronologicalEntries.value.forEach(e => {
    totalMins += e.durationMinutes ?? 0
    const h = Math.floor(totalMins / 60)
    const m = totalMins % 60
    map[e.id] = `${h}:${String(m).padStart(2, '0')}`
  })
  return map
})

const todayTotal = computed(() => {
  const total = props.entries.reduce((s, e) => s + (e.durationMinutes ?? 0), 0)
  return `${Math.floor(total / 60)}h ${total % 60}m`
})

// Sorted display entries
const sortedEntries = computed(() => {
  const sorted = [...props.entries]
  if (ui.sortCol && ui.sortDir) {
    sorted.sort((a: Record<string, unknown>, b: Record<string, unknown>) => {
      const av = a[ui.sortCol!] ?? ''
      const bv = b[ui.sortCol!] ?? ''
      const cmp = av < bv ? -1 : av > bv ? 1 : 0
      return ui.sortDir === 'asc' ? cmp : -cmp
    })
  } else {
    sorted.sort((a, b) => {
      if (a.start && b.start) return a.start > b.start ? 1 : -1
      return 0
    })
  }
  return sorted
})

interface DisplayRow {
  type?: 'gap'
  id: string
  afterId?: string
  minutes?: number
  clientName?: string
  task?: string
  description?: string
  subDescription?: string
  start?: string
  finish?: string
  duration?: string
  accTime?: string
  invoiced?: boolean
  _overlap?: boolean
}

// Interleave gap rows
const displayRows = computed((): DisplayRow[] => {
  const { gaps, overlaps } = detectGapsAndOverlaps(props.entries)
  const gapMap = new Map(gaps.map(g => [g.afterId, g]))

  const rows: DisplayRow[] = []
  sortedEntries.value.forEach(e => {
    rows.push({
      ...e,
      clientName: clientById.value[e.clientId ?? ''] || e.project || '',
      task: e.task || e.subProject || '',
      accTime: accByEntry.value[e.id] ?? '0:00',
      _overlap: overlaps.has(e.id),
    } as DisplayRow)
    const gap = gapMap.get(e.id)
    if (gap) rows.push({ type: 'gap', id: `gap-${e.id}`, afterId: gap.afterId, minutes: gap.minutes })
  })
  return rows
})

const prefillStart = computed(() => {
  const last = [...chronologicalEntries.value].reverse().find(e => e.finish)
  return last?.finish ?? ''
})

function rowClass(row: DisplayRow) {
  if (row._overlap) return 'row-overlap'
  return ''
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
