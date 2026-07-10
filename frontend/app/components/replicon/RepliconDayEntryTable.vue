<template>
  <div>
    <DateNavBar class="mb-3">
      <span class="text-medium-emphasis text-body-2 mr-2">Week: {{ weekTotal }}</span>
      <v-btn size="small" variant="outlined" prepend-icon="mdi-content-copy" @click="$emit('copyFrom')">Copy from…</v-btn>
    </DateNavBar>

    <v-table density="compact" class="entry-table">
      <thead>
        <tr>
          <th @click="ui.cycleSort('project')" class="sortable">Project {{ sortIndicator('project') }}</th>
          <th @click="ui.cycleSort('subProject')" class="sortable">Sub-project {{ sortIndicator('subProject') }}</th>
          <th>Description</th>
          <th>Sub-description</th>
          <th @click="ui.cycleSort('start')" class="sortable">Start {{ sortIndicator('start') }}</th>
          <th>Finish</th>
          <th>Duration</th>
          <th>Acc Time</th>
          <th>Logged</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <template v-for="row in displayRows" :key="row.type === 'gap' ? 'gap-' + row.afterId : row.id">
          <tr v-if="row.type === 'gap'" class="gap-row">
            <td colspan="10" class="text-center text-caption text-medium-emphasis py-1">
              ⟵ {{ row.minutes }}m gap ⟶
            </td>
          </tr>
          <tr
            v-else
            :class="rowClass(row)"
            @dblclick="openEdit(row.id!)"
          >
            <td>
              <v-tooltip :text="projectNameByCode[row.project ?? '']" location="top" :disabled="!projectNameByCode[row.project ?? '']">
                <template #activator="{ props }">
                  <span v-bind="props">{{ row.project }}</span>
                </template>
              </v-tooltip>
            </td>
            <td>
              <v-tooltip
                :text="row.repliconTaskId && taskPathById[row.repliconTaskId]?.length ? taskPathById[row.repliconTaskId].join(' › ') : undefined"
                location="top"
                :disabled="!row.repliconTaskId || !taskPathById[row.repliconTaskId ?? '']?.length"
              >
                <template #activator="{ props }">
                  <span v-bind="props">{{ row.subProject }}</span>
                </template>
              </v-tooltip>
            </td>
            <td>
              {{ row.description }}
              <v-chip v-if="needsJiraLog(row)" size="x-small" color="warning" class="ml-1">Needs Jira log</v-chip>
            </td>
            <td>{{ row.subDescription }}</td>
            <td>{{ fmt.formatTime(row.start) }}</td>
            <td>
              {{ fmt.formatTime(row.finish) }}
              <div v-if="row._overlapFinish">
                <v-chip size="x-small" color="error" class="mt-1">Overlaps next</v-chip>
              </div>
            </td>
            <td>{{ row.duration }}</td>
            <td>{{ row.accTime }}</td>
            <td>
              <v-checkbox-btn
                :model-value="row.logged"
                :color="row.logged ? 'success' : undefined"
                density="compact"
                hide-details
                :disabled="togglingId !== null"
                @update:model-value="toggleLogged(row.id!, $event as boolean)"
              />
            </td>
            <td class="actions-cell">
              <v-btn icon="mdi-pencil"       size="x-small" variant="text" title="Edit this entry"   @click.stop="openEdit(row.id!)" />
              <v-btn icon="mdi-call-split"   size="x-small" variant="text" title="Split this entry"  @click.stop="openSplit(row.id!)" />
              <v-btn icon="mdi-content-copy" size="x-small" variant="text" title="Prefill entry row" @click.stop="prefillRow(row)" />
              <v-btn icon="mdi-delete"       size="x-small" variant="text" color="error" title="Delete entry" :loading="deletingId === row.id" :disabled="deletingId !== null" @click.stop="deleteEntry(row.id!)" />
            </td>
          </tr>
        </template>
        <RepliconEntryRowNew :prefill-start="prefillStart" :prefill="prefillData" />
      </tbody>
    </v-table>

    <RepliconEntryEditDialog v-model="editDialog" :entry="editEntry" />
    <RepliconSplitDialog v-model="splitDialog" :entry="splitEntry" @split="onSplit" />

    <v-snackbar v-model="showUndo" :timeout="5000" location="bottom right">
      Entry deleted.
      <template #actions>
        <v-btn color="primary" variant="text" @click="replicon.undo()">Undo</v-btn>
      </template>
    </v-snackbar>

    <v-snackbar v-model="showSplitDone" :timeout="3000" location="bottom right">
      Entry split successfully.
    </v-snackbar>
  </div>
</template>

<script setup lang="ts">
import type { TimeEntry } from '~/types'

const emit = defineEmits<{ copyFrom: [] }>()

const replicon = useRepliconStore()
const ui = useUiStore()

const jiraRe = computed(() => new RegExp(replicon.jiraPattern || 'PROJ-\\d+', 'i'))
function hasJira(desc?: string) { return jiraRe.value.test(desc ?? '') }
function needsJiraLog(row: DisplayRow) { return hasJira(row.description) && !row.logged }
const fmt = useTimeFormat()
const { detectGapsAndOverlaps } = useGapOverlap()

const editDialog  = ref(false)
const editEntry   = ref<TimeEntry | null>(null)
const splitDialog = ref(false)
const splitEntry  = ref<TimeEntry | null>(null)
const deletingId  = ref<string | null>(null)
const togglingId  = ref<string | null>(null)

interface PrefillData { projectId: string | null; taskId: string | null; description: string; subDescription: string }
const prefillData = ref<PrefillData | null>(null)

function prefillRow(row: DisplayRow) {
  const project = replicon.projects.find(p => p.code === row.project)
  prefillData.value = {
    projectId:      project?.id ?? null,
    taskId:         row.repliconTaskId ?? null,
    description:    row.description ?? '',
    subDescription: row.subDescription ?? '',
  }
}

const showUndo = computed({
  get: () => !!replicon.deletedEntry,
  set: () => {},
})

const showSplitDone = ref(false)
function onSplit() { showSplitDone.value = true }

const dayEntries = computed(() =>
  replicon.entries.filter(e => e.date === ui.currentDate)
)

const weekTotal = computed(() => {
  // Week is Sat–Fri
  const d = new Date(ui.currentDate + 'T00:00:00')
  const daysSinceSat = (d.getDay() + 1) % 7
  const sat = new Date(d)
  sat.setDate(d.getDate() - daysSinceSat)
  const fri = new Date(sat)
  fri.setDate(sat.getDate() + 6)
  const start = sat.toLocaleDateString('en-CA')
  const end = fri.toLocaleDateString('en-CA')
  const total = replicon.entries
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
      const av = a[ui.sortCol!] ?? '', bv = b[ui.sortCol!] ?? ''
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
  project?: string
  subProject?: string
  repliconTaskId?: string | null
  description?: string
  subDescription?: string
  start?: string
  finish?: string
  duration?: string
  accTime?: string
  logged?: boolean
  _overlap?: boolean
  _overlapFinish?: boolean
}

const projectNameByCode = computed(() =>
  Object.fromEntries(replicon.projects.map(p => [p.code, p.name]))
)

const taskPathById = computed(() => {
  const map: Record<string, string[]> = {}
  replicon.projects.forEach(p => p.tasks.forEach(t => { map[t.id] = t.path }))
  return map
})

const displayRows = computed((): DisplayRow[] => {
  const { gaps, overlaps, overlapFinish } = detectGapsAndOverlaps(dayEntries.value)
  const gapMap = new Map(gaps.map(g => [g.afterId, g]))
  const rows: DisplayRow[] = []
  sortedEntries.value.forEach(e => {
    rows.push({
      ...e,
      accTime: accByEntry.value[e.id] ?? '0:00',
      _overlap: overlaps.has(e.id),
      _overlapFinish: overlapFinish.has(e.id),
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
  if (row._overlap) return 'row-overlap'
  if (needsJiraLog(row)) return 'row-jira-warn'
  return ''
}

async function deleteEntry(id: string) {
  deletingId.value = id
  try {
    await replicon.remove(id)
  } finally {
    deletingId.value = null
  }
}

async function toggleLogged(id: string, value: boolean) {
  togglingId.value = id
  try {
    await replicon.update(id, { logged: value })
  } finally {
    togglingId.value = null
  }
}

function openEdit(id: string) {
  editEntry.value = replicon.entries.find(e => e.id === id) ?? null
  editDialog.value = true
}

function openSplit(id: string) {
  splitEntry.value = replicon.entries.find(e => e.id === id) ?? null
  splitDialog.value = true
}

onMounted(async () => {
  await Promise.all([replicon.loadEntries(), replicon.loadProjects()])
})
watch(() => ui.currentDate, (date) => replicon.loadEntries(date))
</script>

<style scoped>
.sortable { cursor: pointer; user-select: none; }
.sortable:hover { background: rgba(0,0,0,.04); }
.gap-row td { background: rgba(245, 158, 11, .08); color: #b45309; }
.row-overlap { background: rgba(239, 68, 68, .08); }
.row-jira-warn { background: rgba(245, 158, 11, .12); }
.actions-cell { white-space: nowrap; }
</style>
