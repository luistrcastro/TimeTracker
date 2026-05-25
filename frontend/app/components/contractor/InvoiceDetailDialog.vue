<template>
  <v-dialog v-model="model" max-width="700">
    <v-card v-if="inv">
      <v-card-title class="d-flex align-center gap-2">
        {{ inv.number }}
        <v-chip :color="statusColor(inv.status)" size="small" variant="tonal">{{ inv.status }}</v-chip>
        <v-spacer />
        <v-btn
          v-if="inv.status !== 'void'"
          icon="mdi-file-pdf-box"
          variant="text"
          size="small"
          :loading="downloading"
          @click="downloadPdf"
        />
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

        <div class="text-right">
          <div>Subtotal: ${{ displaySubtotal }}</div>
          <div>Tax ({{ inv.taxRate }}%): ${{ displayTaxAmount }}</div>
          <div class="font-weight-bold">Total: ${{ displayTotal }}</div>
        </div>

        <div v-if="inv.notes" class="mt-2 text-caption text-medium-emphasis">{{ inv.notes }}</div>
      </v-card-text>

      <v-card-actions>
        <template v-if="inv.status === 'draft'">
          <v-btn color="primary" :loading="acting" @click="doSend">Send Invoice</v-btn>
          <v-btn color="error" variant="text" :loading="voiding" @click="doVoid">Void</v-btn>
        </template>
        <template v-else-if="inv.status === 'sent'">
          <v-btn color="primary" :loading="acting" @click="doApprove">Approve</v-btn>
          <v-btn variant="text" :loading="reverting" @click="doRevert">Revert to Draft</v-btn>
          <v-btn color="error" variant="text" :loading="voiding" @click="doVoid">Void</v-btn>
        </template>
        <template v-else-if="inv.status === 'approved'">
          <v-btn color="primary" :loading="acting" @click="doPaid">Mark as Paid</v-btn>
          <v-btn color="error" variant="text" :loading="voiding" @click="doVoid">Void</v-btn>
        </template>
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
const fmt = useTimeFormat()
const { public: { apiBase } } = useRuntimeConfig()

const model = computed({
  get: () => props.modelValue,
  set: (v) => emit('update:modelValue', v),
})

const acting    = ref(false)
const reverting = ref(false)
const voiding   = ref(false)
const downloading = ref(false)

const inv = computed<Invoice | null>(() =>
  props.invoiceId ? contractor.invoices.find(i => i.id === props.invoiceId) ?? null : null
)

const clientName = computed(() =>
  inv.value ? (contractor.clients.find(c => c.id === inv.value!.clientId)?.name ?? '') : ''
)

const lineItems = computed(() =>
  contractor.entries.filter(e => inv.value?.entryIds?.includes(e.id))
    .sort((a, b) => a.date > b.date ? 1 : -1)
)

const liveSubtotal = computed(() =>
  lineItems.value.reduce((s, e) => s + fmt.minutesToDecimal(e.durationMinutes ?? 0) * Number(inv.value?.rate ?? 0), 0)
)
const liveTaxAmount = computed(() => liveSubtotal.value * Number(inv.value?.taxRate ?? 0) / 100)
const liveTotal     = computed(() => liveSubtotal.value + liveTaxAmount.value)

const displaySubtotal = computed(() =>
  inv.value?.status === 'draft' ? liveSubtotal.value.toFixed(2) : Number(inv.value?.subtotal ?? 0).toFixed(2)
)
const displayTaxAmount = computed(() =>
  inv.value?.status === 'draft' ? liveTaxAmount.value.toFixed(2) : Number(inv.value?.taxAmount ?? 0).toFixed(2)
)
const displayTotal = computed(() =>
  inv.value?.status === 'draft' ? liveTotal.value.toFixed(2) : Number(inv.value?.total ?? 0).toFixed(2)
)

function statusColor(status: string) {
  return ({ draft: 'default', sent: 'blue', approved: 'purple', paid: 'success', void: 'error' } as Record<string, string>)[status] ?? 'default'
}

async function doSend() {
  if (!inv.value) return
  acting.value = true
  try { await contractor.sendInvoice(inv.value.id) } finally { acting.value = false }
}

async function doRevert() {
  if (!inv.value) return
  reverting.value = true
  try { await contractor.revertInvoice(inv.value.id) } finally { reverting.value = false }
}

async function doVoid() {
  if (!inv.value) return
  voiding.value = true
  try {
    await contractor.voidInvoice(inv.value.id)
    model.value = false
  } finally { voiding.value = false }
}

async function doApprove() {
  if (!inv.value) return
  acting.value = true
  try { await contractor.updateInvoiceStatus(inv.value.id, 'approved') } finally { acting.value = false }
}

async function doPaid() {
  if (!inv.value) return
  acting.value = true
  try { await contractor.updateInvoiceStatus(inv.value.id, 'paid') } finally { acting.value = false }
}

async function downloadPdf() {
  if (!inv.value) return
  downloading.value = true
  try {
    const url = `${apiBase}/api/contractor/invoices/${inv.value.id}/pdf`
    const res = await fetch(url, { headers: { Authorization: `Bearer ${auth.token ?? ''}` } })
    const blob = await res.blob()
    const a = document.createElement('a')
    a.href = URL.createObjectURL(blob)
    const cd = res.headers.get('Content-Disposition') ?? ''
    const match = cd.match(/filename="([^"]+)"/)
    a.download = match?.[1] ?? `${inv.value.number}.pdf`
    a.click()
  } finally {
    downloading.value = false
  }
}
</script>
