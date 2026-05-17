<template>
  <v-card variant="outlined">
    <v-card-title>Client Details</v-card-title>
    <v-card-text>

      <!-- Create new client -->
      <v-row dense class="mb-3">
        <v-col>
          <v-text-field
            v-model="newClientName"
            label="New client name"
            variant="outlined"
            density="compact"
            hide-details
            @keydown.enter.prevent="createClient"
          />
        </v-col>
        <v-col cols="auto" class="d-flex align-center">
          <v-btn color="primary" variant="outlined" :disabled="!newClientName.trim()" :loading="creating" @click="createClient">
            Add Client
          </v-btn>
        </v-col>
      </v-row>

      <v-divider class="mb-3" />

      <v-select
        v-model="selectedClientId"
        :items="clientItems"
        item-title="name"
        item-value="id"
        label="Select client"
        variant="outlined"
        density="compact"
        class="mb-3"
        clearable
      />

      <template v-if="selectedClient">
        <!-- Billing info -->
        <v-row dense>
          <v-col cols="12" sm="6">
            <v-text-field v-model="form.legalName" label="Legal Name" variant="outlined" density="compact" />
          </v-col>
          <v-col cols="12" sm="6">
            <v-text-field v-model="form.email" label="Email" type="email" variant="outlined" density="compact" />
          </v-col>
          <v-col cols="12">
            <v-textarea v-model="form.address" label="Address" variant="outlined" density="compact" rows="2" />
          </v-col>
          <v-col cols="12" sm="6">
            <v-text-field v-model="form.phone" label="Phone" variant="outlined" density="compact" />
          </v-col>
        </v-row>

        <!-- Task management -->
        <div class="text-subtitle-2 mt-3 mb-2">Tasks</div>
        <div class="d-flex flex-wrap gap-1 mb-2">
          <v-chip
            v-for="task in form.tasks"
            :key="task.name"
            closable
            size="small"
            @click:close="removeTask(task.name)"
          >{{ task.name }}</v-chip>
          <span v-if="!form.tasks.length" class="text-medium-emphasis text-body-2">No tasks yet</span>
        </div>
        <v-row dense>
          <v-col>
            <v-text-field
              v-model="newTaskName"
              label="Add task"
              variant="outlined"
              density="compact"
              hide-details
              @keydown.enter.prevent="addTask"
            />
          </v-col>
          <v-col cols="auto" class="d-flex align-center">
            <v-btn color="secondary" variant="outlined" size="small" :disabled="!newTaskName.trim()" @click="addTask">Add</v-btn>
          </v-col>
        </v-row>
      </template>
    </v-card-text>

    <v-card-actions v-if="selectedClient">
      <v-btn color="error" variant="text" :loading="deleting" @click="deleteClient">Delete Client</v-btn>
      <v-spacer />
      <v-btn color="primary" :loading="saving" @click="save">Save Client</v-btn>
    </v-card-actions>

    <v-snackbar v-model="saved" :timeout="2000" location="bottom right">Client details saved.</v-snackbar>
  </v-card>
</template>

<script setup lang="ts">
const contractor = useContractorStore()
const api = useApi()
const saving = ref(false)
const creating = ref(false)
const deleting = ref(false)
const saved = ref(false)
const selectedClientId = ref<string | null>(null)
const newClientName = ref('')
const newTaskName = ref('')

const form = reactive({ legalName: '', email: '', address: '', phone: '', tasks: [] as { id: string; name: string }[] })

const clientItems = computed(() => contractor.clients)

const selectedClient = computed(() =>
  contractor.clients.find(c => c.id === selectedClientId.value) ?? null
)

watch(selectedClient, (c) => {
  if (!c) return
  form.legalName = c.legalName ?? ''
  form.email     = c.email ?? ''
  form.address   = c.address ?? ''
  form.phone     = c.phone ?? ''
  form.tasks     = [...(c.tasks ?? [])]
  newTaskName.value = ''
})

function addTask() {
  const name = newTaskName.value.trim()
  if (!name || form.tasks.some(t => t.name === name)) return
  // Use a temporary client-side ID — server will assign a real one after save
  form.tasks.push({ id: '', name })
  newTaskName.value = ''
}

function removeTask(name: string) {
  form.tasks = form.tasks.filter(t => t.name !== name)
}

async function createClient() {
  const name = newClientName.value.trim()
  if (!name) return
  creating.value = true
  try {
    const client = await api('/api/contractor/clients', {
      method: 'POST',
      body: { name },
    }) as any
    await contractor.loadClients()
    selectedClientId.value = client.id
    newClientName.value = ''
  } finally {
    creating.value = false
  }
}

async function save() {
  if (!selectedClientId.value) return
  saving.value = true
  try {
    await api(`/api/contractor/clients/${selectedClientId.value}`, {
      method: 'PUT',
      body: {
        legalName: form.legalName,
        email:     form.email,
        address:   form.address,
        phone:     form.phone,
        tasks:     form.tasks.map(t => t.name),
      },
    })
    await contractor.loadClients()
    saved.value = true
  } finally {
    saving.value = false
  }
}

async function deleteClient() {
  if (!selectedClientId.value) return
  if (!confirm('Delete this client? This cannot be undone.')) return
  deleting.value = true
  try {
    await api(`/api/contractor/clients/${selectedClientId.value}`, { method: 'DELETE' })
    await contractor.loadClients()
    selectedClientId.value = null
  } finally {
    deleting.value = false
  }
}
</script>
