<template>
  <div>
    <div class="text-h6 mb-4">Settings</div>
    <CompanySettingsCard class="mb-4" />
    <ClientDetailsCard class="mb-4" />

    <!-- Jira pattern -->
    <v-card variant="outlined" class="mb-4">
      <v-card-title>Configuration</v-card-title>
      <v-card-text>
        <v-combobox
          v-model="jiraTags"
          label="Jira ticket tags"
          variant="outlined"
          density="compact"
          style="max-width:480px"
          hint="e.g. PROJ-, ABC-123 — entries whose description contains any of these are flagged"
          persistent-hint
          chips
          multiple
          closable-chips
          hide-no-data
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
definePageMeta({ layout: 'module' })

const ui = useUiStore()
const contractor = useContractorStore()

useShortcuts()

const jiraTags = ref(contractor.jiraTags)

async function saveJira() {
  contractor.jiraTags = jiraTags.value
  await contractor.saveCustomization()
}


onMounted(async () => {
  await Promise.all([contractor.loadCompany(), contractor.loadClients()])
})
</script>
