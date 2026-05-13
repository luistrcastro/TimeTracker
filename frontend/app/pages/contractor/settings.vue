<template>
  <div>
    <div class="text-h6 mb-4">Settings</div>

    <!-- Jira pattern -->
    <v-card variant="outlined" class="mb-4">
      <v-card-title>Configuration</v-card-title>
      <v-card-text>
        <v-text-field
          v-model="jiraPattern"
          label="Jira ticket pattern (regex)"
          variant="outlined"
          density="compact"
          style="max-width:320px"
          hint="e.g. PROJ-\d+"
          persistent-hint
        />
      </v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn color="primary" @click="saveJira">Save</v-btn>
      </v-card-actions>
    </v-card>

    <!-- Export / Import -->
    <v-card variant="outlined" class="mb-4">
      <v-card-title>Data</v-card-title>
      <v-card-text>
        <v-btn variant="outlined" class="mr-2" @click="exportJson">Export JSON</v-btn>
        <v-btn variant="outlined" class="mr-2" @click="exportCsv">Export CSV</v-btn>
        <label>
          <v-btn variant="outlined" as="span">Import JSON</v-btn>
          <input type="file" accept=".json" style="display:none" @change="importJson" />
        </label>
      </v-card-text>
    </v-card>

    <CompanySettingsCard />
    <ClientDetailsCard />
  </div>
</template>

<script setup lang="ts">
const ui = useUiStore()
const contractor = useContractorStore()

useShortcuts()

const jiraPattern = ref(ui.jiraPattern)

function saveJira() { ui.jiraPattern = jiraPattern.value }

function exportJson() {
  const blob = new Blob([JSON.stringify(contractor.entries, null, 2)], { type: 'application/json' })
  const a = document.createElement('a'); a.href = URL.createObjectURL(blob)
  a.download = `timetracker-contractor-${new Date().toISOString().slice(0, 10)}.json`
  a.click()
}

function exportCsv() {
  const rows = [['Date', 'Client', 'Task', 'Description', 'Sub-description', 'Start', 'Finish', 'Duration', 'Invoiced']]
  contractor.entries.forEach(e => {
    const client = contractor.clients.find(c => c.id === e.clientId)?.name ?? ''
    rows.push([e.date, client, e.task ?? '', e.description, e.subDescription ?? '', e.start ?? '', e.finish ?? '', e.duration, String(e.invoiced)])
  })
  const csv = rows.map(r => r.map(v => `"${v}"`).join(',')).join('\n')
  const blob = new Blob([csv], { type: 'text/csv' })
  const a = document.createElement('a'); a.href = URL.createObjectURL(blob)
  a.download = `timetracker-contractor-${new Date().toISOString().slice(0, 10)}.csv`
  a.click()
}

async function importJson(e: Event) {
  const file = (e.target as HTMLInputElement).files?.[0]
  if (!file) return
  const text = await file.text()
  const data = JSON.parse(text)
  for (const entry of data) {
    const { id, ...rest } = entry
    await contractor.create(rest)
  }
  await contractor.loadEntries()
}

onMounted(async () => {
  await Promise.all([contractor.loadCompany(), contractor.loadClients()])
})
</script>
