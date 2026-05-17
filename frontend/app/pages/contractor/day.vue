<template>
  <div>
    <ContractorDayEntryTable @copy-from="showCopyFrom = true" />
    <CopyFromDayDialog
      v-model="showCopyFrom"
      :entries-by-date="allEntriesByDate"
      @copy="handleCopyFrom"
    />
  </div>
</template>

<script setup lang="ts">
const contractor = useContractorStore()
const ui = useUiStore()

useShortcuts()

const showCopyFrom = ref(false)

const allEntriesByDate = computed(() => {
  const map: Record<string, any[]> = {}
  const clientById = Object.fromEntries(contractor.clients.map(c => [c.id, c.name]))
  contractor.entries.forEach(e => {
    if (!map[e.date]) map[e.date] = []
    map[e.date].push({ ...e, clientName: clientById[e.clientId ?? ''] ?? '', task: e.task ?? '' })
  })
  return map
})

async function handleCopyFrom(entryIds: string[]) {
  const toCopy = contractor.entries.filter(e => entryIds.includes(e.id))
  for (const e of toCopy) {
    const { id, ...rest } = e
    await contractor.create({ ...rest, date: ui.currentDate, start: '', finish: '', duration: '0:00', durationMinutes: 0 })
  }
  showCopyFrom.value = false
}
</script>
