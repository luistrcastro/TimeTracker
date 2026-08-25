<template>
  <div>
    <div class="text-h6 mb-4">Replicon Settings</div>

    <CredentialsCard />
    <ProjectBrowser />
    <RowMapEditor />

    <v-card variant="outlined">
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
const replicon = useRepliconStore()

useShortcuts()

const jiraTags = ref(replicon.jiraTags)

async function saveJira() {
  replicon.jiraTags = jiraTags.value
  await replicon.saveCustomization()
}

onMounted(async () => {
  await Promise.all([replicon.loadCredentials(), replicon.loadProjects(), replicon.loadRowMap()])
})
</script>
