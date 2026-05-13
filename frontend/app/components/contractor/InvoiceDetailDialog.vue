<template>
  <v-dialog v-model="model" max-width="700">
    <v-card v-if="inv">
      <v-card-title class="d-flex align-center gap-2">
        {{ inv.number }}
        <v-chip :color="statusColor(inv.status)" size="small" variant="tonal">{{ inv.status }}</v-chip>
        <v-spacer />
        <v-btn icon="mdi-printer" variant="text" size="small" @click="downloadPdf" />
      </v-card-title>

      <v-card-text>
        <v-row dense class="mb-3">
          <v-col cols="6">
            <div class="text-caption text-medium-emphasis">Client</div>
            <div>{{ clientName }}</div>
          </v-col>
          <v-col cols="3">
            <div class="text-caption text-medium-emphasis">Date</div>
            <div>{{ inv.createdDate }}</div>
          </v-col>
          <v-col cols="3">
            <div class="text-caption text-medium-emphasis">Due</div>
            <div>{{ inv.dueDate }}</div>
          </v-col>
        </v-row>

        <!-- Line items -->
        <v-table density="compact" class="mb-3">
          <thead>
            <tr><th>Date</th><th>Description</th><th>Hours</th><th>Amount</th></tr>
          </thead>
          <tbody>
            <tr v-for="e in lineItems" :key="e.id">
              <td>{{ e.date }}</td>
              <td>{{ e.description }}{{ e.subDescription ? ' — ' + e.subDescription : '' }}</td>
              <td>{{ fmt.minutesToDecimal(e.durationMinutes ?? 0) }}</td>
              <td>${{ (fmt.minutesToDecimal(e.durationMinutes ?? 0) * Number(inv.rate)).toFixed(2) }}</td>
            </tr>
          </tbody>
        </v-table>

        <!-- Totals -->
        <div class="text-right">
          <div>Subtotal: ${{ Number(inv.subtotal).toFixed(2) }}</div>
          <div>Tax ({{ inv.taxRate }}%): ${{ Number(inv.taxAmount).toFixed(2) }}</div>
          <div class="font-weight-bold">Total: ${{ Number(inv.total).toFixed(2) }}</div>
        </div>

        <v-divider class="my-3" />

        <!-- Status -->
        <v-row dense>
          <v-col cols="6">
            <v-select
              v-model="newStatus"
              :items="['draft','sent','paid','void']"
              label="Status"
              variant="outlined"
              density="compact"
            />
          </v-col>
          <v-col cols="6" class="d-flex align-end">
            <v-btn color="primary" size="small" :loading="saving" @click="saveStatus">Save Status</v-btn>
          </v-col>
        </v-row>

        <div v-if="inv.notes" class="mt-2 text-caption text-medium-emphasis">{{ inv.notes }}</div>
      </v-card-text>

      <v-card-actions>
        <v-btn color="error" variant="text" :loading="voiding" @click="voidInvoice">
          Void &amp; Unmark Entries
        </v-btn>
        <v-spacer />
        <v-btn variant="text" @click="model = false">Close</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup lang="ts">
import type { Invoice } from '~/types'

const props = defineProps<{ modelValue: boolean; invoiceId: string | null }>()
const emit = defineEmits<{ 'update:modelValue': [boolean] }>()

const contractor = useContractorStore()
const auth = useAuthStore()
const api = useApi()
const fmt = useTimeFormat()
const { public: { apiBase } } = useRuntimeConfig()

const model = computed({
  get: () => props.modelValue,
  set: (v) => emit('update:modelValue', v),
})

const saving = ref(false)
const voiding = ref(false)
const newStatus = ref<string>('draft')

const inv = computed<Invoice | null>(() =>
  props.invoiceId ? contractor.invoices.find(i => i.id === props.invoiceId) ?? null : null
)

watch(inv, (i) => { if (i) newStatus.value = i.status })

const clientName = computed(() =>
  inv.value ? (contractor.clients.find(c => c.id === inv.value!.clientId)?.name ?? '') : ''
)

const lineItems = computed(() =>
  contractor.entries.filter(e => inv.value?.entryIds?.includes(e.id))
    .sort((a, b) => a.date > b.date ? 1 : -1)
)

function statusColor(status: string) {
  return ({ draft: 'default', sent: 'blue', paid: 'success', void: 'error' } as Record<string, string>)[status] ?? 'default'
}

async function saveStatus() {
  if (!inv.value) return
  saving.value = true
  try {
    await api(`/api/contractor/invoices/${inv.value.id}`, {
      method: 'PUT',
      body: { status: newStatus.value, notes: inv.value.notes },
    })
    await contractor.loadInvoices()
  } finally {
    saving.value = false
  }
}

async function voidInvoice() {
  if (!inv.value) return
  voiding.value = true
  try {
    await api(`/api/contractor/invoices/${inv.value.id}`, {
      method: 'PUT',
      body: { status: 'void' },
    })
    await Promise.all([contractor.loadInvoices(), contractor.loadEntries()])
    model.value = false
  } finally {
    voiding.value = false
  }
}

async function downloadPdf() {
  if (!inv.value) return
  const url = `${apiBase}/api/contractor/invoices/${inv.value.id}/pdf`
  const res = await fetch(url, {
    headers: { Authorization: `Bearer ${auth.token ?? ''}` },
  })
  const blob = await res.blob()
  const a = document.createElement('a')
  a.href = URL.createObjectURL(blob)
  const cd = res.headers.get('Content-Disposition') ?? ''
  const match = cd.match(/filename="([^"]+)"/)
  a.download = match?.[1] ?? `${inv.value.number}.pdf`
  a.click()
}
</script>
