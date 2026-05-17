<template>
  <v-card variant="outlined" class="mb-4">
    <v-card-title>Row Map (Project → Timesheet Row)</v-card-title>
    <v-card-text>
      <p class="text-body-2 mb-3">
        Map each project+task to its row index in your Replicon weekly timesheet (row 0 = first task row).
      </p>
      <v-data-table
        :headers="headers"
        :items="rows"
        :items-per-page="10"
        density="compact"
      >
        <template #item.rowIndex="{ item }">
          <v-text-field
            :model-value="localMap[item.key] ?? ''"
            @update:model-value="setRow(item.key, String($event))"
            type="number"
            min="0"
            density="compact"
            variant="underlined"
            hide-details
            style="max-width:80px"
          />
        </template>
        <template #no-data>
          <span class="text-medium-emphasis">No projects. Sync first.</span>
        </template>
      </v-data-table>
    </v-card-text>
    <v-card-actions>
      <v-tooltip location="top" max-width="300">
        <template #activator="{ props }">
          <v-btn v-bind="props" variant="text" @click="copyScript">Copy script</v-btn>
        </template>
        <div>
          Open your Replicon timesheet, paste this script in the DevTools
          console, and run it. It reads the project/task rows directly from
          the page and maps them automatically — no manual entry needed.
          Run <strong>Sync from Replicon</strong> first so the projects exist.
        </div>
      </v-tooltip>
      <v-btn variant="text" :loading="refreshing" @click="refresh">Refresh</v-btn>
      <v-spacer />
      <v-btn color="primary" :loading="saving" :disabled="!replicon.projects.length" @click="save">
        Save Row Map
      </v-btn>
    </v-card-actions>
    <v-snackbar v-model="saved" :timeout="2000" location="bottom right">Row map saved.</v-snackbar>
    <v-snackbar v-model="copied" :timeout="2000" location="bottom right">Script copied to clipboard.</v-snackbar>
  </v-card>
</template>

<script setup lang="ts">
const replicon = useRepliconStore()
const auth = useAuthStore()
const { public: { apiBase } } = useRuntimeConfig()
const saving = ref(false)
const saved = ref(false)
const copied = ref(false)
const refreshing = ref(false)
const localMap = ref<Record<string, number | ''>>({})

const headers = [
  { title: 'Project', key: 'projectName', sortable: true },
  { title: 'Task',    key: 'taskName',    sortable: true },
  { title: 'Row',     key: 'rowIndex',    sortable: false, width: '100px' },
]

const rows = computed(() =>
  replicon.projects.flatMap(proj =>
    proj.tasks.map(task => ({
      key:         `${proj.id}:${task.id}`,
      projectName: proj.name,
      taskName:    task.name,
    }))
  )
)

watch(() => replicon.rowMap, (m) => {
  localMap.value = { ...m }
}, { immediate: true })

function setRow(key: string, value: string) {
  localMap.value = { ...localMap.value, [key]: value === '' ? '' : Number(value) }
}

function buildRowMapScript() {
  const token = auth.token ?? ''
  return `(function(){
  var rows=[];
  document.querySelectorAll('tr[rowid]').forEach(function(tr){
    var rowId=parseInt(tr.getAttribute('rowid'),10);
    var a=tr.querySelector('a[projectvalue][taskvalue]');
    if(!a)return;
    var pId=a.getAttribute('projectvalue');
    var tId=a.getAttribute('taskvalue');
    if(!pId||!tId)return;
    var pName=((a.querySelector('.projectPartName')||{}).textContent||'').trim();
    var tEl=a.querySelector('.taskPartName .selected')||a.querySelector('.taskPartName');
    var tName=((tEl||{}).textContent||'').trim();
    rows.push({rowId:rowId,projectId:pId,taskId:tId,projectName:pName,taskName:tName});
  });
  if(!rows.length){alert('No rows found. Make sure you are on the Replicon timesheet page.');return;}
  fetch('${apiBase}/api/replicon/row-map',{
    method:'POST',
    headers:{'Content-Type':'application/json','Accept':'application/json','Authorization':'Bearer ${token}'},
    body:JSON.stringify({rows:rows})
  }).then(function(r){return r.json();})
    .then(function(d){console.log('Mapped '+d.count+' of '+d.total+' rows.',d);alert('Mapped '+d.count+' of '+d.total+' rows!');})
    .catch(function(e){console.error('Row map failed:',e);});
})();`
}

async function copyScript() {
  await navigator.clipboard.writeText(buildRowMapScript())
  copied.value = true
}

async function refresh() {
  refreshing.value = true
  try {
    await replicon.loadRowMap()
  } finally {
    refreshing.value = false
  }
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
