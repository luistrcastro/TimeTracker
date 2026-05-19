<template>
  <div>
    <div class="text-h6 mb-4">Settings</div>
    <CompanySettingsCard class="mb-4" />
    <ClientDetailsCard class="mb-4" />

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

  </div>
</template>

<script setup lang="ts">
const ui = useUiStore()
const contractor = useContractorStore()

useShortcuts()

const jiraPattern = ref(contractor.jiraPattern)

async function saveJira() {
  contractor.jiraPattern = jiraPattern.value
  await contractor.saveCustomization()
}


onMounted(async () => {
  await Promise.all([contractor.loadCompany(), contractor.loadClients()])
})
</script>
