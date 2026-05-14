<template>
  <v-app :theme="ui.theme">
    <v-app-bar elevation="0" color="surface" border="b">
      <v-app-bar-title>
        <span class="font-weight-bold">Time Tracker</span>
        <span class="text-medium-emphasis font-weight-regular"> | v{{ appVersion }}</span>
      </v-app-bar-title>
      <template #append>
        <span class="text-mono text-body-2 text-medium-emphasis mr-3" style="min-width:70px;text-align:right">{{ clock }}</span>
        <v-btn :icon="ui.theme === 'dark' ? 'mdi-weather-sunny' : 'mdi-weather-night'" @click="toggleTheme" size="small" />
        <v-btn icon="mdi-logout" @click="handleLogout" size="small" />
      </template>
    </v-app-bar>

    <v-main>
      <v-container class="d-flex align-center justify-center" style="min-height: calc(100vh - 64px)">
        <div class="text-center">
          <div class="text-h5 font-weight-bold mb-2">Welcome, {{ auth.user?.name }}</div>
          <div class="text-body-2 text-medium-emphasis mb-8">Choose a mode to get started</div>
          <div class="d-flex gap-6 justify-center">
            <v-card
              rounded="xl"
              elevation="2"
              width="180"
              height="180"
              class="variant-card d-flex flex-column align-center justify-center cursor-pointer mr-2"
              :ripple="true"
              @click="go('replicon')"
            >
              <v-icon size="48" color="primary" class="mb-3">mdi-clock-time-four-outline</v-icon>
              <div class="text-subtitle-1 font-weight-semibold">Replicon</div>
              <div class="text-caption text-medium-emphasis mt-1">Timesheet sync</div>
            </v-card>

            <v-card
              rounded="xl"
              elevation="2"
              width="180"
              height="180"
              class="variant-card d-flex flex-column align-center justify-center cursor-pointer"
              :ripple="true"
              @click="go('contractor')"
            >
              <v-icon size="48" color="secondary" class="mb-3">mdi-briefcase-outline</v-icon>
              <div class="text-subtitle-1 font-weight-semibold">Contractor</div>
              <div class="text-caption text-medium-emphasis mt-1">Invoicing & clients</div>
            </v-card>
          </div>
        </div>
      </v-container>
    </v-main>
  </v-app>
</template>

<script setup lang="ts">
definePageMeta({ layout: false })

const ui = useUiStore()
const auth = useAuthStore()
const router = useRouter()
const nuxtApp = useNuxtApp()
const { public: { appVersion } } = useRuntimeConfig()

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

function go(variant: 'replicon' | 'contractor') {
  ui.activeVariant = variant
  router.push(`/${variant}/day`)
}

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
  updateClock()
  clockTimer = setInterval(updateClock, 1000)
  const vuetify = (nuxtApp as any).$vuetify
  if (vuetify) vuetify.theme.global.name.value = ui.theme
})

onUnmounted(() => {
  if (clockTimer) clearInterval(clockTimer)
})
</script>

<style scoped>
.text-mono { font-family: monospace; }
.variant-card {
  transition: transform 0.15s ease, box-shadow 0.15s ease;
}
.variant-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 8px 24px rgba(0,0,0,.12) !important;
}
</style>
