<template>
  <div>
    <EntryTable
      :entries="dayEntries"
      :clients="contractor.clients"
      :has-deleted="!!contractor.deletedEntry"
      :prefill-start="prefillStart"
      @edit="openEdit"
      @duplicate="contractor.duplicate($event)"
      @split="openSplit"
      @delete="contractor.remove($event)"
      @save="handleSave"
      @undo="contractor.undo()"
      @copy-from="showCopyFrom = true"
    />

    <!-- Edit dialog -->
    <EntryEditDialog
      v-model="editDialog"
      :entry="editEntry"
      :clients="contractor.clients"
      @saved="handleEditSaved"
    />

    <!-- Copy From dialog -->
    <CopyFromDayDialog
      v-model="showCopyFrom"
      :entries-by-date="allEntriesByDate"
      @copy="handleCopyFrom"
    />
  </div>
</template>

<script setup lang="ts">
import type { TimeEntry } from '~/types'

const ui = useUiStore()
const contractor = useContractorStore()

useShortcuts()

const editDialog = ref(false)
const editEntry = ref<TimeEntry | null>(null)
const showCopyFrom = ref(false)

const dayEntries = computed(() =>
  contractor.entries.filter(e => e.date === ui.currentDate)
)

const prefillStart = computed(() => {
  const sorted = [...dayEntries.value]
    .filter(e => !!e.finish)
    .sort((a, b) => (a.start ?? '') > (b.start ?? '') ? 1 : -1)
  return sorted[sorted.length - 1]?.finish ?? ''
})

const allEntriesByDate = computed(() => {
  const map: Record<string, (TimeEntry & { clientName?: string })[]> = {}
  const clientById = Object.fromEntries(contractor.clients.map(c => [c.id, c.name]))
  contractor.entries.forEach(e => {
    if (!map[e.date]) map[e.date] = []
    map[e.date].push({ ...e, clientName: clientById[e.clientId ?? ''] ?? '' })
  })
  return map
})

async function handleSave(entry: Partial<TimeEntry> & { clientName?: string }) {
  // Resolve client name → clientId
  const client = contractor.clients.find(c => c.name === (entry as { clientName?: string }).clientName)
  const { clientName: _, ...rest } = entry as Partial<TimeEntry> & { clientName?: string }
  await contractor.create({
    ...rest,
    clientId: client?.id ?? null,
    date: ui.currentDate,
    description: rest.description ?? '',
    duration: rest.duration ?? '0:00',
    durationMinutes: rest.durationMinutes ?? 0,
  } as Omit<TimeEntry, 'id'>)
}

function openEdit(id: string) {
  editEntry.value = contractor.entries.find(e => e.id === id) ?? null
  editDialog.value = true
}

function openSplit(_id: string) {
  // TODO Phase 4b: split dialog
}

async function handleEditSaved(updated: Partial<TimeEntry> & { id: string }) {
  await contractor.update(updated.id, updated)
  editDialog.value = false
}

async function handleCopyFrom(entryIds: string[], _sourceDate: string) {
  const toCopy = contractor.entries.filter(e => entryIds.includes(e.id))
  for (const e of toCopy) {
    const { id: _, ...rest } = e
    await contractor.create({
      ...rest,
      date: ui.currentDate,
      start: '',
      finish: '',
      duration: '0:00',
      durationMinutes: 0,
      invoiced: false,
    })
  }
  showCopyFrom.value = false
}

// Load on mount and when date changes
onMounted(async () => {
  await Promise.all([
    contractor.loadEntries(),
    contractor.loadClients(),
  ])
})

watch(() => ui.currentDate, async (date) => {
  await contractor.loadEntries(date)
})
</script>
