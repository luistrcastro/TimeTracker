<template>
  <v-card variant="outlined">
    <v-card-title>Invoices</v-card-title>
    <v-table density="compact">
      <thead>
        <tr>
          <th>Number</th><th>Client</th><th>Date</th><th>Due</th>
          <th>Total</th><th>Status</th><th></th>
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
          <td>
            <v-btn size="x-small" variant="text" @click="$emit('view', inv.id)">View</v-btn>
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

const clientName = (id: string) =>
  contractor.clients.find(c => c.id === id)?.name ?? id

function statusColor(status: string) {
  return ({ draft: 'default', sent: 'blue', paid: 'success', void: 'error' } as Record<string, string>)[status] ?? 'default'
}
</script>
