<template>
  <v-app :theme="ui.theme">
    <v-app-bar elevation="0" color="surface" border="b">
      <v-app-bar-title>
        <span class="font-weight-bold">Time Tracker</span>
      </v-app-bar-title>
      <template #append>
        <v-btn :icon="ui.theme === 'dark' ? 'mdi-weather-sunny' : 'mdi-weather-night'" @click="toggleTheme" size="small" />
        <v-btn :text="ui.use12h ? '12H' : '24H'" @click="ui.toggleTimeFormat()" variant="text" size="small" />
        <v-btn icon="mdi-logout" @click="handleLogout" size="small" />
      </template>
    </v-app-bar>

    <!-- Variant + page tabs -->
    <v-toolbar density="compact" color="surface" border="b" elevation="0">
      <v-tabs density="compact" class="mr-2">
        <v-tab
          value="replicon"
          :to="'/replicon/day'"
          :active="isReplicon"
          @click="ui.activeVariant = 'replicon'"
        >Replicon</v-tab>
        <v-tab
          value="contractor"
          :to="'/contractor/day'"
          :active="!isReplicon"
          @click="ui.activeVariant = 'contractor'"
        >Contractor</v-tab>
      </v-tabs>
      <v-divider vertical class="mx-1" style="height:24px;align-self:center" />
      <v-tabs density="compact">
        <v-tab v-for="tab in pageTabs" :key="tab.to" :to="tab.to">{{ tab.label }}</v-tab>
      </v-tabs>
    </v-toolbar>

    <v-main>
      <v-container fluid class="pa-4">
        <slot />
      </v-container>
    </v-main>
  </v-app>
</template>

<script setup lang="ts">
const ui = useUiStore()
const auth = useAuthStore()
const router = useRouter()
const route = useRoute()
const nuxtApp = useNuxtApp()

const isReplicon = computed(() => !route.path.startsWith('/contractor'))
const variant = computed(() => isReplicon.value ? 'replicon' : 'contractor')

const pageTabs = computed(() => {
  const base = `/${variant.value}`
  return [
    { label: 'Day',      to: `${base}/day` },
    { label: 'Week',     to: `${base}/week` },
    { label: isReplicon.value ? 'Replicon' : 'Compiled', to: `${base}/compiled` },
    ...(isReplicon.value ? [] : [{ label: 'Invoicing', to: `${base}/invoicing` }]),
    { label: 'Settings', to: `${base}/settings` },
  ]
})

function toggleTheme() {
  ui.toggleTheme()
  const vuetify = (nuxtApp as any).$vuetify
  if (vuetify) vuetify.theme.global.name.value = ui.theme
}

async function handleLogout() {
  await auth.logout()
  router.push('/login')
}

onMounted(() => {
  const vuetify = (nuxtApp as any).$vuetify
  if (vuetify) vuetify.theme.global.name.value = ui.theme
})
</script>
