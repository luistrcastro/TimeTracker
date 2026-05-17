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
    const { id, ...rest } = e
    await replicon.create({ ...rest, date: ui.currentDate, start: '', finish: '', duration: '0:00', durationMinutes: 0 })
  }
  showCopyFrom.value = false
}
</script>
