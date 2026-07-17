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
      <template v-else>
        <div class="d-flex gap-2 mb-3">
          <v-autocomplete
            v-model="selectedProjectId"
            class="mr-2"
            :items="projectOptions"
            item-title="label"
            item-value="id"
            label="Project"
            variant="outlined"
            density="compact"
            clearable
            style="max-width:320px"
          />
          <v-text-field
            v-model="searchText"
            label="Search tasks"
            prepend-inner-icon="mdi-magnify"
            variant="outlined"
            density="compact"
            clearable
            style="max-width:320px"
          />
        </div>
        <div v-if="!filteredProjects.length" class="text-medium-emphasis text-body-2">
          No projects or tasks match your filter.
        </div>
        <v-list v-else v-model:opened="opened" density="compact" lines="one" style="max-height:320px;overflow-y:auto">
          <v-list-group v-for="proj in filteredProjects" :key="proj.id" :value="proj.id">
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
      </template>
    </v-card-text>
  </v-card>
</template>

<script setup lang="ts">
const replicon = useRepliconStore()

const selectedProjectId = ref<string | null>(null)
const searchText = ref('')

const opened = ref<string[]>([])
const openedBeforeSearch = ref<string[]>([])

watch([searchText, selectedProjectId], ([nextText], [prevText]) => {
  const wasSearching = (prevText ?? '').trim().length > 0
  const isSearching = (nextText ?? '').trim().length > 0

  if (isSearching && !wasSearching) {
    openedBeforeSearch.value = [...opened.value]
  }

  if (isSearching) {
    opened.value = filteredProjects.value.map(p => p.id)
  } else if (wasSearching) {
    opened.value = [...openedBeforeSearch.value]
  }
})

const projectOptions = computed(() =>
  replicon.projects.map(p => ({ id: p.id, label: `[${p.code}] ${p.name}` }))
)

const filteredProjects = computed(() => {
  const query = (searchText.value ?? '').trim().toLowerCase()

  return replicon.projects
    .filter(proj => !selectedProjectId.value || proj.id === selectedProjectId.value)
    .map(proj => {
      if (!query) return proj
      const tasks = proj.tasks.filter(t => t.name.toLowerCase().includes(query))
      return tasks.length ? { ...proj, tasks } : null
    })
    .filter((proj): proj is NonNullable<typeof proj> => proj !== null)
})

async function sync() {
  await replicon.sync()
  await replicon.loadProjects()
}
</script>
