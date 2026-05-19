<template>
  <NuxtLayout name="default">
    <v-tabs density="compact" class="mb-3">
      <v-tab v-for="tab in pageTabs" :key="tab.to" :to="tab.to">{{ tab.label }}</v-tab>
    </v-tabs>
    <slot />
  </NuxtLayout>
</template>

<script setup lang="ts">
const route = useRoute()

const isReplicon = computed(() => route.path.startsWith('/replicon'))

const pageTabs = computed(() => {
  const base = isReplicon.value ? '/replicon' : '/contractor'
  return [
    { label: 'Day',      to: `${base}/day` },
    { label: 'Week',     to: `${base}/week` },
    { label: isReplicon.value ? 'Replicon' : 'Compiled', to: `${base}/compiled` },
    ...(isReplicon.value ? [] : [{ label: 'Invoicing', to: `${base}/invoicing` }]),
    { label: 'Settings', to: `${base}/settings` },
  ]
})
</script>
