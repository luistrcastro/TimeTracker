<template>
  <div>
    <div class="text-h6 mb-4">Replicon Settings</div>

    <CredentialsCard />
    <ProjectBrowser />
    <RowMapEditor />

    <v-card variant="outlined">
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
const replicon = useRepliconStore()

useShortcuts()

const jiraPattern = ref(replicon.jiraPattern)

async function saveJira() {
  replicon.jiraPattern = jiraPattern.value
  await replicon.saveCustomization()
}

onMounted(async () => {
  await Promise.all([replicon.loadCredentials(), replicon.loadProjects(), replicon.loadRowMap()])
})
</script>
