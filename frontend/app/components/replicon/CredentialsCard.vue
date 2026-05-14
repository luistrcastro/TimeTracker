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

    </v-card-text>
    <v-card-actions>
      <v-tooltip location="top" max-width="280">
        <template #activator="{ props }">
          <v-btn v-bind="props" variant="text" @click="copyScript">Copy script</v-btn>
        </template>
        <div>
          Open your Replicon timesheet with DevTools open, paste this
          script in the Console tab, then <strong>click any cell</strong>
          in the timesheet. A prompt will appear — switch to the Network
          tab, find that request, copy the full <strong>Cookie</strong>
          header value, and paste it into the prompt.
        </div>
      </v-tooltip>
      <v-btn variant="text" :loading="refreshing" @click="refresh">Refresh</v-btn>
      <v-spacer />
      <v-btn v-if="replicon.credsOk" color="error" variant="text" @click="replicon.deleteCredentials()">Clear</v-btn>
      <v-btn color="primary" :loading="saving" @click="save">Save Credentials</v-btn>
    </v-card-actions>
    <v-snackbar v-model="saved" :timeout="2000" location="bottom right">Credentials saved.</v-snackbar>
  </v-card>
</template>

<script setup lang="ts">
const replicon = useRepliconStore()
const auth = useAuthStore()
const { public: { apiBase } } = useRuntimeConfig()
const saving = ref(false)
const saved = ref(false)
const refreshing = ref(false)

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

function buildCaptureScript() {
  const token = auth.token ?? ''
  return `(function(){
  if(window._rplCapture){console.log('Already listening.');return;}
  window._rplCapture=true;
  var oO=XMLHttpRequest.prototype.open,oS=XMLHttpRequest.prototype.send;
  XMLHttpRequest.prototype.open=function(m,u){this._u=u;return oO.apply(this,arguments);};
  XMLHttpRequest.prototype.send=function(b){
    if(this._u&&this._u.indexOf('QueueRequests')!==-1&&b){
      try{
        var d=JSON.parse(b);
        var sid=d.sessionId,svs=d.serverViewStateId;
        if(sid&&svs){
          var base=(this._u.match(/(https?:\\/\\/[^\\/]+)/)||[])[1]||location.origin;
          var maxIdx=Math.max.apply(null,(d.requests||[]).map(function(r){return r.requestIndex||0;}));
          XMLHttpRequest.prototype.open=oO;
          XMLHttpRequest.prototype.send=oS;
          window._rplCapture=false;
          var cookie=prompt('Paste the full Cookie header value from the Network tab:');
          if(!cookie){console.warn('Cancelled — no cookie provided.');return oS.apply(this,arguments);}
          fetch('${apiBase}/api/replicon/credentials',{
            method:'PUT',
            headers:{'Content-Type':'application/json','Accept':'application/json','Authorization':'Bearer ${token}'},
            body:JSON.stringify({base_url:base,session_id:sid,server_view_state_id:svs,
              cookie_header:cookie,last_request_index:maxIdx})
          }).then(function(r){return r.json();})
            .then(function(r){console.log('Credentials saved:',r);debugger; /* ← confirm r looks correct, then resume */})
            .catch(function(e){console.error('Save failed:',e);});
          return oS.apply(this,arguments);
        }
      }catch(e){console.error(e);}
    }
    return oS.apply(this,arguments);
  };
  console.log('Listening — click any cell in your Replicon timesheet to capture credentials...');
})();`
}

async function copyScript() {
  await navigator.clipboard.writeText(buildCaptureScript())
}

async function refresh() {
  refreshing.value = true
  try {
    await replicon.loadCredentials()
  } finally {
    refreshing.value = false
  }
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
