<template>
  <v-card variant="outlined">
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
          <div class="text-body-2 mb-2 text-medium-emphasis">Company Logo</div>
          <div
            class="logo-upload-zone"
            :class="{
              'logo-upload-zone--has-preview': !!logoPreview,
              'logo-upload-zone--dragging': isDragging,
            }"
            @dragenter.prevent="isDragging = true"
            @dragover.prevent="isDragging = true"
            @dragleave.prevent="isDragging = false"
            @drop.prevent="onDrop"
            @click="!logoPreview && triggerFileInput()"
          >
            <input
              ref="fileInputRef"
              type="file"
              accept="image/*"
              class="logo-file-input"
              @change="onLogoChange"
            />

            <Transition name="logo-fade" mode="out-in">
              <!-- Empty state -->
              <div v-if="!logoPreview && !uploadingLogo" key="empty" class="logo-empty-state">
                <div class="logo-upload-icon">
                  <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="3" width="18" height="18" rx="2" />
                    <circle cx="8.5" cy="8.5" r="1.5" />
                    <polyline points="21 15 16 10 5 21" />
                  </svg>
                </div>
                <div class="logo-empty-label">
                  <span class="logo-action-text">Click to upload</span> or drag &amp; drop
                </div>
                <div class="logo-empty-hint">PNG, JPG, SVG · max 2 MB</div>
              </div>

              <!-- Uploading state -->
              <div v-else-if="uploadingLogo" key="uploading" class="logo-empty-state">
                <div class="logo-upload-icon">
                  <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#5b6af5" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="logo-spin">
                    <circle cx="12" cy="12" r="10" stroke-dasharray="40 20" />
                  </svg>
                </div>
                <div class="logo-empty-label" style="color:#5b6af5">Uploading…</div>
              </div>

              <!-- Preview state -->
              <div v-else-if="!uploadingLogo" key="preview" class="logo-preview-state">
                <div class="logo-preview-img-wrap">
                  <img :src="logoPreview" class="logo-preview-img" alt="company logo preview" />
                </div>
                <div class="logo-preview-actions">
                  <button class="logo-preview-btn" title="Replace logo" @click.stop="triggerFileInput()">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                      <polyline points="17 8 12 3 7 8" />
                      <line x1="12" y1="3" x2="12" y2="15" />
                    </svg>
                    Replace
                  </button>
                  <button class="logo-preview-btn logo-preview-btn--remove" title="Remove logo" :disabled="removingLogo" @click.stop="removeLogo()">
                    <svg v-if="!removingLogo" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <polyline points="3 6 5 6 21 6" />
                      <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6" />
                      <path d="M10 11v6M14 11v6" />
                    </svg>
                    <svg v-else width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="logo-spin">
                      <circle cx="12" cy="12" r="10" stroke-dasharray="40 20" />
                    </svg>
                    {{ removingLogo ? '…' : 'Remove' }}
                  </button>
                </div>
              </div>
            </Transition>
          </div>
        </v-col>
      </v-row>
    </v-card-text>
    <v-card-actions>
      <v-spacer />
      <v-btn color="primary" :loading="saving" :disabled="uploadingLogo || removingLogo" @click="save">Save</v-btn>
    </v-card-actions>
    <v-snackbar v-model="saved" :timeout="2000" location="bottom right">Company settings saved.</v-snackbar>
    <v-snackbar v-model="errorSnackbar" :timeout="5000" location="bottom right" color="error">{{ errorMsg }}</v-snackbar>
  </v-card>
</template>

<script setup lang="ts">
const contractor = useContractorStore()
const api = useApi()
const saving = ref(false)
const saved = ref(false)
const errorMsg = ref<string | null>(null)
const errorSnackbar = computed({
  get: () => !!errorMsg.value,
  set: (v) => { if (!v) errorMsg.value = null },
})
const uploadingLogo = ref(false)
const logoPreview = ref<string | null>(null)
const fileInputRef = ref<HTMLInputElement | null>(null)
const isDragging = ref(false)
const removingLogo = ref(false)

function triggerFileInput() {
  fileInputRef.value?.click()
}

async function removeLogo() {
  removingLogo.value = true
  try {
    await api('/api/contractor/company/logo', { method: 'DELETE' })
    logoPreview.value = null
    if (fileInputRef.value) fileInputRef.value.value = ''
    await contractor.loadCompany()
  } catch (err: any) {
    errorMsg.value = err?.data?.message ?? 'Failed to remove logo.'
  } finally {
    removingLogo.value = false
  }
}

function onDrop(e: DragEvent) {
  isDragging.value = false
  const file = e.dataTransfer?.files?.[0]
  if (!file || !file.type.startsWith('image/')) return
  applyFile(file)
}

async function applyFile(file: File) {
  if (fileInputRef.value) fileInputRef.value.value = ''
  const previousPreview = logoPreview.value
  const reader = new FileReader()
  reader.onload = (ev) => { logoPreview.value = ev.target?.result as string }
  reader.readAsDataURL(file)

  uploadingLogo.value = true
  try {
    const fd = new FormData()
    fd.append('logo', file)
    await api('/api/contractor/company/logo', { method: 'POST', body: fd })
    await contractor.loadCompany()
  } catch (err: any) {
    logoPreview.value = previousPreview
    const validationErrors = err?.data?.errors
    errorMsg.value = validationErrors
      ? Object.values(validationErrors).flat().join(' ')
      : (err?.data?.message ?? 'Failed to upload logo.')
  } finally {
    uploadingLogo.value = false
  }
}

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
  applyFile(file)
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

    await contractor.loadCompany()
    saved.value = true
  } finally {
    saving.value = false
  }
}
</script>

<style scoped>
.logo-file-input {
  display: none;
}

.logo-upload-zone {
  position: relative;
  border: 1.5px dashed rgba(128, 128, 128, 0.35);
  border-radius: 8px;
  min-height: 96px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: border-color 0.2s ease, background-color 0.2s ease;
  overflow: hidden;
}

.logo-upload-zone:hover:not(.logo-upload-zone--has-preview) {
  border-color: #5b6af5;
  background-color: rgba(91, 106, 245, 0.04);
}

.logo-upload-zone--dragging {
  border-color: #5b6af5 !important;
  background-color: rgba(91, 106, 245, 0.07) !important;
  border-style: solid !important;
}

.logo-upload-zone--has-preview {
  cursor: default;
  border-style: solid;
  border-color: rgba(128, 128, 128, 0.2);
  min-height: 80px;
}

/* Empty state */
.logo-empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
  padding: 20px 16px;
  text-align: center;
}

.logo-upload-icon {
  color: rgba(128, 128, 128, 0.5);
  transition: color 0.2s ease, transform 0.2s ease;
}

.logo-upload-zone:hover .logo-upload-icon {
  color: #5b6af5;
  transform: translateY(-2px);
}

.logo-upload-zone--dragging .logo-upload-icon {
  color: #5b6af5;
  transform: scale(1.1);
}

.logo-empty-label {
  font-size: 0.8125rem;
  color: rgba(128, 128, 128, 0.7);
  line-height: 1.4;
}

.logo-action-text {
  color: #5b6af5;
  font-weight: 500;
}

.logo-empty-hint {
  font-size: 0.7rem;
  letter-spacing: 0.03em;
  color: rgba(128, 128, 128, 0.45);
  text-transform: uppercase;
}

/* Preview state */
.logo-preview-state {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 12px 16px;
  width: 100%;
}

.logo-preview-img-wrap {
  flex-shrink: 0;
  background: repeating-conic-gradient(rgba(128,128,128,0.08) 0% 25%, transparent 0% 50%)
    0 0 / 12px 12px;
  border-radius: 4px;
  padding: 6px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.logo-preview-img {
  max-height: 48px;
  max-width: 160px;
  object-fit: contain;
  display: block;
}

.logo-preview-actions {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.logo-preview-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 4px 10px;
  font-size: 0.75rem;
  font-weight: 500;
  letter-spacing: 0.02em;
  border: 1px solid rgba(128, 128, 128, 0.25);
  border-radius: 4px;
  background: transparent;
  color: rgba(128, 128, 128, 0.8);
  cursor: pointer;
  transition: border-color 0.15s ease, color 0.15s ease, background-color 0.15s ease;
  line-height: 1;
}

.logo-preview-btn:hover {
  border-color: #5b6af5;
  color: #5b6af5;
  background-color: rgba(91, 106, 245, 0.06);
}

.logo-preview-btn--remove:hover {
  border-color: #ef5350;
  color: #ef5350;
  background-color: rgba(239, 83, 80, 0.06);
}

.logo-preview-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.logo-spin {
  animation: logo-spin 0.8s linear infinite;
}

@keyframes logo-spin {
  to { transform: rotate(360deg); }
}

/* Transition */
.logo-fade-enter-active,
.logo-fade-leave-active {
  transition: opacity 0.18s ease, transform 0.18s ease;
}

.logo-fade-enter-from {
  opacity: 0;
  transform: scale(0.97);
}

.logo-fade-leave-to {
  opacity: 0;
  transform: scale(1.02);
}
</style>
