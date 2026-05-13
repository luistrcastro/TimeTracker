<template>
  <v-card variant="outlined" class="mb-4">
    <v-card-title>Row Map (Project → Timesheet Row)</v-card-title>
    <v-card-text>
      <p class="text-body-2 mb-3">
        Map each project+task to its row index in your Replicon weekly timesheet (row 0 = first task row).
      </p>
      <v-table density="compact">
        <thead>
          <tr><th>Project</th><th>Task</th><th>Row Index</th></tr>
        </thead>
        <tbody>
          <template v-for="proj in replicon.projects" :key="proj.id">
            <tr v-for="task in proj.tasks" :key="task.id">
              <td>{{ proj.name }}</td>
              <td>{{ task.name }}</td>
              <td style="width:100px">
                <v-text-field
                  :model-value="localMap[`${proj.id}:${task.id}`] ?? ''"
                  @update:model-value="setRow(proj.id, task.id, String($event))"
                  type="number"
                  min="0"
                  density="compact"
                  variant="underlined"
                  hide-details
                  style="max-width:80px"
                />
              </td>
            </tr>
          </template>
          <tr v-if="!replicon.projects.length">
            <td colspan="3" class="text-center text-medium-emphasis py-3">No projects. Sync first.</td>
          </tr>
        </tbody>
      </v-table>
    </v-card-text>
    <v-card-actions>
      <v-spacer />
      <v-btn color="primary" :loading="saving" :disabled="!replicon.projects.length" @click="save">
        Save Row Map
      </v-btn>
    </v-card-actions>
    <v-snackbar v-model="saved" :timeout="2000" location="bottom right">Row map saved.</v-snackbar>
  </v-card>
</template>

<script setup lang="ts">
const replicon = useRepliconStore()
const saving = ref(false)
const saved = ref(false)
const localMap = ref<Record<string, number | ''>>({})

watch(() => replicon.rowMap, (m) => {
  localMap.value = { ...m }
}, { immediate: true })

function setRow(projectId: string, taskId: string, value: string) {
  const key = `${projectId}:${taskId}`
  localMap.value = { ...localMap.value, [key]: value === '' ? '' : Number(value) }
}

async function save() {
  saving.value = true
  const map: Record<string, number> = {}
  Object.entries(localMap.value).forEach(([k, v]) => {
    if (v !== '') map[k] = Number(v)
  })
  try {
    await replicon.saveRowMap(map)
    saved.value = true
  } finally {
    saving.value = false
  }
}
</script>
