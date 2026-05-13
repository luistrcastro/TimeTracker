<template>
  <v-card variant="outlined" class="mb-4">
    <v-card-title class="d-flex align-center gap-2">
      Replicon Credentials
      <v-chip :color="replicon.credsOk ? 'success' : 'error'" size="x-small" variant="tonal">
        {{ replicon.credsOk ? 'Connected' : 'Not configured' }}
      </v-chip>
    </v-card-title>
    <v-card-text>
      <v-row dense>
        <v-col cols="12">
          <v-text-field
            v-model="form.base_url"
            label="Base URL"
            variant="outlined"
            density="compact"
            placeholder="https://your-company.replicon.com"
          />
        </v-col>
        <v-col cols="12" sm="6">
          <v-text-field v-model="form.session_id" label="Session ID" variant="outlined" density="compact" />
        </v-col>
        <v-col cols="12" sm="6">
          <v-text-field v-model="form.server_view_state_id" label="Server View State ID" variant="outlined" density="compact" />
        </v-col>
        <v-col cols="12">
          <v-text-field
            v-model="form.cookie_header"
            :label="replicon.credentials?.cookie_set ? 'Cookie (set — leave blank to keep)' : 'Cookie Header'"
            variant="outlined"
            density="compact"
            :placeholder="replicon.credentials?.cookie_set ? '(unchanged)' : 'Paste full Cookie header'"
          />
        </v-col>
      </v-row>

      <v-expansion-panels variant="accordion" class="mt-2">
        <v-expansion-panel title="Show capture script">
          <v-expansion-panel-text>
            <div class="text-caption mb-2">
              Open your Replicon timesheet in a browser, open DevTools console, and paste:
            </div>
            <pre class="capture-script">{{ captureScript }}</pre>
            <v-btn size="x-small" variant="text" @click="copyScript">Copy script</v-btn>
          </v-expansion-panel-text>
        </v-expansion-panel>
      </v-expansion-panels>
    </v-card-text>
    <v-card-actions>
      <v-btn v-if="replicon.credsOk" color="error" variant="text" @click="replicon.deleteCredentials()">Clear</v-btn>
      <v-spacer />
      <v-btn color="primary" :loading="saving" @click="save">Save Credentials</v-btn>
    </v-card-actions>
    <v-snackbar v-model="saved" :timeout="2000" location="bottom right">Credentials saved.</v-snackbar>
  </v-card>
</template>

<script setup lang="ts">
const replicon = useRepliconStore()
const { public: { apiBase } } = useRuntimeConfig()
const saving = ref(false)
const saved = ref(false)

const form = reactive({
  base_url: '',
  session_id: '',
  server_view_state_id: '',
  cookie_header: '',
})

watch(() => replicon.credentials, (c) => {
  if (!c) return
  form.base_url = c.base_url
  form.session_id = c.session_id
  form.server_view_state_id = c.server_view_state_id
  // Never pre-fill cookie_header — security constraint
}, { immediate: true })

const captureScript = computed(() => `(function(){
  var sid=document.cookie.match(/ASP\\.NET_SessionId=([^;]+)/)?.[1]||'';
  var svs=window.__viewState||window.serverViewStateId||'';
  var token=(JSON.parse(localStorage.getItem('tt_auth')||'{}').token||'');
  fetch('${apiBase}/api/replicon/credentials',{
    method:'PUT',
    headers:{'Content-Type':'application/json','Authorization':'Bearer '+token},
    body:JSON.stringify({base_url:location.origin,session_id:sid,
      server_view_state_id:svs,cookie_header:document.cookie})
  }).then(r=>r.json()).then(r=>console.log('Saved:',r)).catch(console.error);
})();`)

async function copyScript() {
  await navigator.clipboard.writeText(captureScript.value)
}

async function save() {
  saving.value = true
  try {
    await replicon.saveCredentials({
      base_url:             form.base_url,
      session_id:           form.session_id,
      server_view_state_id: form.server_view_state_id,
      cookie_header:        form.cookie_header,
    })
    saved.value = true
    form.cookie_header = ''
  } finally {
    saving.value = false
  }
}
</script>

<style scoped>
.capture-script {
  font-size: 11px;
  white-space: pre-wrap;
  word-break: break-all;
  background: rgba(0,0,0,.05);
  padding: 8px;
  border-radius: 4px;
  max-height: 180px;
  overflow-y: auto;
}
</style>
