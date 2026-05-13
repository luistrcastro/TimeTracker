<template>
  <v-card variant="outlined">
    <v-card-title>Client Details</v-card-title>
    <v-card-text>
      <v-select
        v-model="selectedClientId"
        :items="clientItems"
        item-title="name"
        item-value="id"
        label="Select client"
        variant="outlined"
        density="compact"
        class="mb-3"
      />
      <template v-if="selectedClient">
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
      </template>
    </v-card-text>
    <v-card-actions v-if="selectedClient">
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
const saved = ref(false)
const selectedClientId = ref<string | null>(null)

const form = reactive({ legalName: '', email: '', address: '', phone: '' })

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
})

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
      },
    })
    await contractor.loadClients()
    saved.value = true
  } finally {
    saving.value = false
  }
}
</script>
