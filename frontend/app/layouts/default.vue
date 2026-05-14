<template>
  <v-app :theme="ui.theme">
    <v-app-bar elevation="0" color="surface" border="b">
      <v-app-bar-title>
        <NuxtLink to="/" class="title-link text-decoration-none text-high-emphasis">
          <span class="font-weight-bold">Time Tracker</span>
        </NuxtLink>
        <span class="text-medium-emphasis font-weight-regular"> | v{{ appVersion }} | {{ moduleLabel }}</span>
      </v-app-bar-title>
      <template #append>
        <span class="text-mono text-body-2 text-medium-emphasis mr-2" style="min-width:70px;text-align:right">{{ clock }}</span>
        <v-btn :icon="ui.theme === 'dark' ? 'mdi-weather-sunny' : 'mdi-weather-night'" @click="toggleTheme" size="small" />
        <v-btn :text="ui.use12h ? '12H' : '24H'" @click="ui.toggleTimeFormat()" variant="text" size="small" />
        <v-btn icon="mdi-logout" @click="handleLogout" size="small" />
      </template>
    </v-app-bar>

    <v-main>
      <v-container fluid class="pa-4">
        <v-tabs density="compact" class="mb-3">
          <v-tab v-for="tab in pageTabs" :key="tab.to" :to="tab.to">{{ tab.label }}</v-tab>
        </v-tabs>
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
const { public: { appVersion } } = useRuntimeConfig()

const isReplicon = computed(() => route.path.startsWith('/replicon'))
const variant = computed(() => isReplicon.value ? 'replicon' : 'contractor')
const moduleLabel = computed(() => isReplicon.value ? 'Replicon' : 'Contractor')

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

// Live clock
const clock = ref('')
let clockTimer: ReturnType<typeof setInterval> | null = null

function updateClock() {
  const now = new Date()
  if (ui.use12h) {
    clock.value = now.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', second: '2-digit', hour12: true })
  } else {
    const h = String(now.getHours()).padStart(2, '0')
    const m = String(now.getMinutes()).padStart(2, '0')
    const s = String(now.getSeconds()).padStart(2, '0')
    clock.value = `${h}:${m}:${s}`
  }
}

watch(() => ui.use12h, updateClock)

onMounted(() => {
  updateClock()
  clockTimer = setInterval(updateClock, 1000)
  const vuetify = (nuxtApp as any).$vuetify
  if (vuetify) vuetify.theme.global.name.value = ui.theme
})

onUnmounted(() => {
  if (clockTimer) clearInterval(clockTimer)
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
</script>

<style scoped>
.title-link:hover { opacity: 0.75; }
.text-mono { font-family: monospace; }
</style>
