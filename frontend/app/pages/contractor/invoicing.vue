<template>
  <div>
    <div class="text-h6 mb-4">Invoicing</div>

    <InvoiceCreateCard @created="onCreated" />

    <InvoiceList :invoices="contractor.invoices" @view="openDetail" />

    <InvoiceDetailDialog
      v-model="detailOpen"
      :invoice-id="detailId"
    />
  </div>
</template>

<script setup lang="ts">
const contractor = useContractorStore()

useShortcuts()

const detailOpen = ref(false)
const detailId = ref<string | null>(null)

function openDetail(id: string) {
  detailId.value = id
  detailOpen.value = true
}

function onCreated() {
  // entries + invoices already refreshed by InvoiceCreateCard
}

onMounted(async () => {
  await Promise.all([
    contractor.loadEntries(),
    contractor.loadClients(),
    contractor.loadInvoices(),
    contractor.loadCompany(),
  ])
})
</script>
