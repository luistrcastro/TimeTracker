<template>
  <v-card variant="outlined" class="mb-4">
    <v-card-title>Create Invoice</v-card-title>
    <v-card-text>
      <v-row dense>
        <v-col cols="12" sm="4">
          <AutocompleteInput
            v-model="form.clientName"
            :suggestions="contractor.clientNames"
            label="Client"
            variant="outlined"
            density="compact"
          />
        </v-col>
        <v-col cols="6" sm="2">
          <v-text-field v-model.number="form.rate" label="Rate ($/hr)" type="number" variant="outlined" density="compact" />
        </v-col>
        <v-col cols="6" sm="2">
          <v-text-field v-model.number="form.taxRate" label="Tax %" type="number" variant="outlined" density="compact" />
        </v-col>
        <v-col cols="6" sm="2">
          <v-text-field v-model="form.dateFrom" label="From" type="date" variant="outlined" density="compact" />
        </v-col>
        <v-col cols="6" sm="2">
          <v-text-field v-model="form.dateTo" label="To" type="date" variant="outlined" density="compact" />
        </v-col>
      </v-row>

      <v-btn variant="outlined" size="small" class="mb-3" @click="loadEntries">Load Uninvoiced Entries</v-btn>

      <template v-if="uninvoiced.length">
        <div class="d-flex justify-space-between align-center mb-1">
          <v-btn size="small" variant="text" @click="toggleAll">{{ allSelected ? 'Deselect all' : 'Select all' }}</v-btn>
        </div>
        <v-list density="compact" lines="two" class="mb-3" style="max-height:280px;overflow-y:auto">
          <v-list-item
            v-for="e in uninvoiced" :key="e.id"
            :prepend-icon="selected.has(e.id) ? 'mdi-checkbox-marked' : 'mdi-checkbox-blank-outline'"
            :title="`${e.date} · ${e.duration}`"
            :subtitle="`${e.description}${e.subDescription ? ' — ' + e.subDescription : ''}`"
            @click="toggle(e.id)"
          />
        </v-list>

        <!-- Live totals -->
        <v-row dense class="mb-3">
          <v-col cols="4">
            <div class="text-caption text-medium-emphasis">Subtotal</div>
            <div class="font-weight-medium">${{ subtotal.toFixed(2) }}</div>
          </v-col>
          <v-col cols="4">
            <div class="text-caption text-medium-emphasis">Tax ({{ form.taxRate }}%)</div>
            <div class="font-weight-medium">${{ taxAmount.toFixed(2) }}</div>
          </v-col>
          <v-col cols="4">
            <div class="text-caption text-medium-emphasis">Total</div>
            <div class="font-weight-bold text-primary">${{ total.toFixed(2) }}</div>
          </v-col>
        </v-row>

        <v-row dense>
          <v-col cols="6" sm="3">
            <v-text-field v-model="form.invoiceDate" label="Invoice Date" type="date" variant="outlined" density="compact" />
          </v-col>
          <v-col cols="6" sm="3">
            <v-text-field v-model="form.dueDate" label="Due Date" type="date" variant="outlined" density="compact" />
          </v-col>
          <v-col cols="12" sm="6">
            <v-textarea v-model="form.notes" label="Notes" variant="outlined" density="compact" rows="1" />
          </v-col>
        </v-row>
      </template>
    </v-card-text>
    <v-card-actions v-if="selected.size > 0">
      <v-spacer />
      <v-btn color="primary" :loading="creating" @click="create">Create Invoice</v-btn>
    </v-card-actions>
  </v-card>
</template>

<script setup lang="ts">
import type { TimeEntry } from '~/types'

const emit = defineEmits<{ created: [] }>()

const contractor = useContractorStore()
const api = useApi()
const { minutesToDecimal } = useTimeFormat()

const creating = ref(false)
const uninvoiced = ref<TimeEntry[]>([])
const selected = ref(new Set<string>())

const today = new Date().toISOString().slice(0, 10)
const form = reactive({
  clientName: '',
  rate: contractor.company?.defaultRate ?? 0,
  taxRate: contractor.company?.defaultTaxRate ?? 0,
  dateFrom: '',
  dateTo: today,
  invoiceDate: today,
  dueDate: '',
  notes: '',
})

watch(() => contractor.company, (c) => {
  if (!c) return
  if (!form.rate) form.rate = c.defaultRate
  if (!form.taxRate) form.taxRate = c.defaultTaxRate
})

const allSelected = computed(() =>
  uninvoiced.value.length > 0 && uninvoiced.value.every(e => selected.value.has(e.id))
)

async function loadEntries() {
  const client = contractor.clients.find(c => c.name === form.clientName)
  if (!client) return

  uninvoiced.value = contractor.entries.filter(e => {
    if (e.clientId !== client.id) return false
    if (e.invoiced) return false
    if (form.dateFrom && e.date < form.dateFrom) return false
    if (form.dateTo && e.date > form.dateTo) return false
    return true
  }).sort((a, b) => a.date > b.date ? 1 : -1)

  selected.value = new Set(uninvoiced.value.map(e => e.id))
}

function toggle(id: string) {
  if (selected.value.has(id)) selected.value.delete(id)
  else selected.value.add(id)
  selected.value = new Set(selected.value)
}

function toggleAll() {
  if (allSelected.value) selected.value = new Set()
  else selected.value = new Set(uninvoiced.value.map(e => e.id))
}

const selectedEntries = computed(() =>
  uninvoiced.value.filter(e => selected.value.has(e.id))
)

const subtotal = computed(() =>
  selectedEntries.value.reduce((s, e) => {
    return s + minutesToDecimal(e.durationMinutes ?? 0) * form.rate
  }, 0)
)

const taxAmount = computed(() => subtotal.value * form.taxRate / 100)
const total = computed(() => subtotal.value + taxAmount.value)

function nextInvoiceNumber(): string {
  const numbers = contractor.invoices.map(inv => {
    const m = inv.number.match(/(\d+)$/)
    return m ? parseInt(m[1]) : 0
  })
  const next = (numbers.length ? Math.max(...numbers) : 0) + 1
  return `INV-${String(next).padStart(4, '0')}`
}

async function create() {
  const client = contractor.clients.find(c => c.name === form.clientName)
  if (!client || !selected.value.size) return

  creating.value = true
  try {
    const number = nextInvoiceNumber()
    await api('/api/contractor/invoices', {
      method: 'POST',
      body: {
        clientId:    client.id,
        number,
        createdDate: form.invoiceDate,
        dueDate:     form.dueDate || form.invoiceDate,
        rate:        form.rate,
        subtotal:    subtotal.value,
        taxRate:     form.taxRate,
        taxAmount:   taxAmount.value,
        total:       total.value,
        notes:       form.notes,
        entryIds:    [...selected.value],
      },
    })

    await Promise.all([contractor.loadInvoices(), contractor.loadEntries()])
    uninvoiced.value = []
    selected.value = new Set()
    emit('created')
  } finally {
    creating.value = false
  }
}
</script>
