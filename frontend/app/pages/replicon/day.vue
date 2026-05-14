<template>
  <div>
    <EntryTable
      :entries="dayEntries"
      :clients="[]"
      :has-deleted="!!replicon.deletedEntry"
      :prefill-start="prefillStart"
      variant="replicon"
      @edit="openEdit"
      @duplicate="replicon.duplicate($event)"
      @split="() => {}"
      @delete="replicon.remove($event)"
      @save="handleSave"
      @undo="replicon.undo()"
      @copy-from="showCopyFrom = true"
    />

    <EntryEditDialog
      v-model="editDialog"
      :entry="editEntry"
      :clients="[]"
      :entries="replicon.entries"
      @saved="handleEditSaved"
    />

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
const replicon = useRepliconStore()

useShortcuts()

const editDialog = ref(false)
const editEntry = ref<TimeEntry | null>(null)
const showCopyFrom = ref(false)

const dayEntries = computed(() =>
  replicon.entries.filter(e => e.date === ui.currentDate)
)

const prefillStart = computed(() => {
  const sorted = [...dayEntries.value].filter(e => e.finish)
    .sort((a, b) => (a.start ?? '') > (b.start ?? '') ? 1 : -1)
  return sorted[sorted.length - 1]?.finish ?? ''
})

const allEntriesByDate = computed(() => {
  const map: Record<string, TimeEntry[]> = {}
  replicon.entries.forEach(e => {
    if (!map[e.date]) map[e.date] = []
    map[e.date].push(e)
  })
  return map
})

async function handleSave(entry: any) {
  await replicon.create({
    ...entry,
    project:    entry.clientName ?? entry.project ?? '',
    subProject: entry.task       ?? entry.subProject ?? '',
  })
}

function openEdit(id: string) {
  editEntry.value = replicon.entries.find(e => e.id === id) ?? null
  editDialog.value = true
}

async function handleEditSaved(updated: Partial<TimeEntry> & { id: string }) {
  await replicon.update(updated.id, updated)
  editDialog.value = false
}

async function handleCopyFrom(entryIds: string[]) {
  const toCopy = replicon.entries.filter(e => entryIds.includes(e.id))
  for (const e of toCopy) {
    const { id, ...rest } = e
    await replicon.create({ ...rest, date: ui.currentDate, start: '', finish: '', duration: '0:00', durationMinutes: 0 })
  }
  showCopyFrom.value = false
}

onMounted(() => replicon.loadEntries())
watch(() => ui.currentDate, (date) => replicon.loadEntries(date))
</script>
