<template>
  <v-card variant="outlined" class="mb-4">
    <v-card-title class="d-flex align-center gap-2">
      Projects &amp; Tasks
      <v-spacer />
      <v-btn
        size="small"
        variant="outlined"
        prepend-icon="mdi-sync"
        :loading="replicon.syncing"
        :disabled="!replicon.credsOk"
        @click="sync"
      >Sync from Replicon</v-btn>
    </v-card-title>
    <v-card-text>
      <div v-if="replicon.lastSyncStatus" class="text-caption mb-2 text-medium-emphasis">
        {{ replicon.lastSyncStatus }}
      </div>
      <div v-if="!replicon.projects.length" class="text-medium-emphasis text-body-2">
        No projects synced yet. Configure credentials and click Sync.
      </div>
      <v-list v-else density="compact" lines="one" style="max-height:320px;overflow-y:auto">
        <v-list-group v-for="proj in replicon.projects" :key="proj.id" :value="proj.id">
          <template #activator="{ props }">
            <v-list-item v-bind="props" :title="`[${proj.code}] ${proj.name}`" />
          </template>
          <v-list-item
            v-for="task in proj.tasks"
            :key="task.id"
            :title="task.name"
            density="compact"
            class="pl-8 text-caption"
          />
        </v-list-group>
      </v-list>
    </v-card-text>
  </v-card>
</template>

<script setup lang="ts">
const replicon = useRepliconStore()

async function sync() {
  await replicon.sync()
  await replicon.loadProjects()
}
</script>
