<template>
  <div>
    <RepliconDayEntryTable @copy-from="showCopyFrom = true" />
    <CopyFromDayDialog
      v-model="showCopyFrom"
      :entries-by-date="allEntriesByDate"
      @copy="handleCopyFrom"
    />
  </div>
</template>

<script setup lang="ts">
definePageMeta({ layout: 'module' })

const replicon = useRepliconStore()
const ui = useUiStore()

useShortcuts()

const showCopyFrom = ref(false)

// Pass entries with clientName/task mapped for CopyFromDayDialog display
const allEntriesByDate = computed(() => {
  const map: Record<string, any[]> = {}
  replicon.entries.forEach(e => {
    if (!map[e.date]) map[e.date] = []
    map[e.date].push({ ...e, clientName: e.project ?? '', task: e.subProject ?? '' })
  })
  return map
})

async function handleCopyFrom(entryIds: string[]) {
  const toCopy = replicon.entries.filter(e => entryIds.includes(e.id))
  for (const e of toCopy) {
    // Re-resolve project/task from current data (like the edit dialog does)
    // instead of copying repliconTaskId verbatim — a source entry created
    // before it was linked to a task has repliconTaskId: null, and copying
    // that as-is stops it from grouping with other entries on the same task
    // in the compiled view.
    const project = replicon.projectForEntry(e)
    const task    = project?.tasks.find(t => t.id === e.repliconTaskId) ??
                    project?.tasks.find(t => t.name === (e.subProject ?? ''))
    await replicon.create({
      date:            ui.currentDate,
      project:         project?.code ?? e.project ?? '',
      subProject:      task?.name    ?? e.subProject ?? '',
      repliconTaskId:  task?.id ?? null,
      description:     e.description,
      subDescription:  e.subDescription,
      furtherInfo:     e.furtherInfo,
      start:           '',
      finish:          '',
      duration:        '0:00',
      durationMinutes: 0,
      logged:          false,
    })
  }
  showCopyFrom.value = false
}
</script>
