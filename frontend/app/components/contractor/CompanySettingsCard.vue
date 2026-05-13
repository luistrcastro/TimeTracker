<template>
  <v-card variant="outlined" class="mb-4">
    <v-card-title>Company &amp; Invoicing Defaults</v-card-title>
    <v-card-text>
      <v-row dense>
        <v-col cols="12" sm="6">
          <v-text-field v-model="form.name" label="Company Name" variant="outlined" density="compact" />
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
        <v-col cols="6" sm="3">
          <v-text-field v-model.number="form.defaultRate" label="Default Rate ($/hr)" type="number" variant="outlined" density="compact" />
        </v-col>
        <v-col cols="6" sm="3">
          <v-text-field v-model.number="form.defaultTaxRate" label="Default Tax (%)" type="number" variant="outlined" density="compact" />
        </v-col>
        <v-col cols="12">
          <div class="text-body-2 mb-1">Company Logo</div>
          <input type="file" accept="image/*" @change="onLogoChange" />
          <div v-if="logoPreview" class="mt-2">
            <img :src="logoPreview" style="max-height:48px;max-width:200px" alt="logo preview" />
          </div>
        </v-col>
      </v-row>
    </v-card-text>
    <v-card-actions>
      <v-spacer />
      <v-btn color="primary" :loading="saving" @click="save">Save</v-btn>
    </v-card-actions>
    <v-snackbar v-model="saved" :timeout="2000" location="bottom right">Company settings saved.</v-snackbar>
  </v-card>
</template>

<script setup lang="ts">
const contractor = useContractorStore()
const api = useApi()
const saving = ref(false)
const saved = ref(false)
const logoPreview = ref<string | null>(null)
const logoFile = ref<File | null>(null)

const form = reactive({
  name: '',
  email: '',
  address: '',
  phone: '',
  defaultRate: 0,
  defaultTaxRate: 0,
})

watch(() => contractor.company, (c) => {
  if (!c) return
  form.name = c.name
  form.email = c.email
  form.address = c.address
  form.phone = c.phone
  form.defaultRate = c.defaultRate
  form.defaultTaxRate = c.defaultTaxRate
  if (c.logoUrl) logoPreview.value = c.logoUrl
}, { immediate: true })

function onLogoChange(e: Event) {
  const file = (e.target as HTMLInputElement).files?.[0]
  if (!file) return
  logoFile.value = file
  const reader = new FileReader()
  reader.onload = (ev) => { logoPreview.value = ev.target?.result as string }
  reader.readAsDataURL(file)
}

async function save() {
  saving.value = true
  try {
    await api('/api/contractor/company', {
      method: 'PUT',
      body: {
        name:           form.name,
        email:          form.email,
        address:        form.address,
        phone:          form.phone,
        defaultRate:    form.defaultRate,
        defaultTaxRate: form.defaultTaxRate,
      },
    })

    if (logoFile.value) {
      const fd = new FormData()
      fd.append('logo', logoFile.value)
      await api('/api/contractor/company/logo', { method: 'POST', body: fd })
      logoFile.value = null
    }

    await contractor.loadCompany()
    saved.value = true
  } finally {
    saving.value = false
  }
}
</script>
