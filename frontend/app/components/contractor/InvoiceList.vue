<template>
  <v-card variant="outlined">
    <v-card-title>Invoices</v-card-title>
    <v-table density="compact">
      <thead>
        <tr>
          <th>Number</th><th>Client</th><th>Date</th><th>Due</th>
          <th>Total</th><th>Status</th><th style="width:1%"></th>
        </tr>
      </thead>
      <tbody>
        <tr v-if="!invoices.length">
          <td colspan="7" class="text-center text-medium-emphasis py-4">No invoices yet.</td>
        </tr>
        <tr v-for="inv in invoices" :key="inv.id">
          <td>{{ inv.number }}</td>
          <td>{{ clientName(inv.clientId) }}</td>
          <td>{{ inv.createdDate }}</td>
          <td>{{ inv.dueDate }}</td>
          <td>${{ Number(inv.total).toFixed(2) }}</td>
          <td>
            <v-chip :color="statusColor(inv.status)" size="x-small" variant="tonal">
              {{ inv.status }}
            </v-chip>
          </td>
          <td class="d-flex align-center gap-1" style="white-space:nowrap">
            <v-btn size="x-small" variant="text" @click="$emit('view', inv.id)">View</v-btn>
            <v-btn size="x-small" variant="text" icon="mdi-file-pdf-box" :loading="downloading === inv.id" @click="downloadPdf(inv)" />
          </td>
        </tr>
      </tbody>
    </v-table>
  </v-card>
</template>

<script setup lang="ts">
import type { Invoice } from '~/types'

defineProps<{ invoices: Invoice[] }>()
defineEmits<{ view: [id: string] }>()

const contractor = useContractorStore()
const auth = useAuthStore()
const { public: { apiBase } } = useRuntimeConfig()
const downloading = ref<string | null>(null)

const clientName = (id: string) =>
  contractor.clients.find(c => c.id === id)?.name ?? id

function statusColor(status: string) {
  return ({ draft: 'default', sent: 'blue', paid: 'success', void: 'error' } as Record<string, string>)[status] ?? 'default'
}

async function downloadPdf(inv: Invoice) {
  downloading.value = inv.id
  try {
    const url = `${apiBase}/api/contractor/invoices/${inv.id}/pdf`
    const res = await fetch(url, { headers: { Authorization: `Bearer ${auth.token ?? ''}` } })
    const blob = await res.blob()
    const a = document.createElement('a')
    a.href = URL.createObjectURL(blob)
    const cd = res.headers.get('Content-Disposition') ?? ''
    const match = cd.match(/filename="([^"]+)"/)
    a.download = match?.[1] ?? `${inv.number}.pdf`
    a.click()
  } finally {
    downloading.value = null
  }
}
</script>
