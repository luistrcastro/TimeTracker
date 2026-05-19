<template>
  <v-card rounded="lg" elevation="2" class="pa-6">
        <div class="auth-brand mb-6 text-center">
          <v-icon class="auth-brand__icon mb-2" icon="mdi-timer-outline" size="48" color="#5b6af5" />
          <div class="auth-brand__name">TimeTracker</div>
          <div class="auth-brand__tagline">Track your work, own your time</div>
        </div>
    <div class="text-h5 font-weight-bold mb-6">Sign in</div>

    <v-form @submit.prevent="submit" :disabled="loading">
      <v-text-field
        v-model="email" label="Email" type="email"
        variant="outlined" class="mb-3" required autofocus
      />
      <v-text-field
        v-model="password" label="Password" :type="showPassword ? 'text' : 'password'"
        variant="outlined" class="mb-4" required
        :append-inner-icon="showPassword ? 'mdi-eye-off' : 'mdi-eye'"
        @click:append-inner="showPassword = !showPassword"
      />

      <v-checkbox v-model="keepLoggedIn" label="Keep me logged in" density="compact" class="mb-2" hide-details />

      <v-alert v-if="justVerified" type="success" class="mb-4" density="compact">Email verified! Sign in to continue.</v-alert>
      <v-alert v-if="error" type="error" class="mb-4" density="compact">{{ error }}</v-alert>

      <v-btn type="submit" color="primary" block size="large" :loading="loading">Sign in</v-btn>
    </v-form>

    <div class="mt-4 text-center text-body-2">
      <NuxtLink to="/forgot-password">Forgot password?</NuxtLink>
      &nbsp;·&nbsp;
      <NuxtLink to="/register">Create account</NuxtLink>
    </div>
  </v-card>
</template>

<script setup lang="ts">
definePageMeta({ layout: 'auth' })

const auth = useAuthStore()
const router = useRouter()
const route = useRoute()

const email = ref('')
const password = ref('')
const showPassword = ref(false)
const keepLoggedIn = ref(true)
const error = ref('')
const loading = ref(false)
const justVerified = computed(() => route.query.verified === '1')

async function submit() {
  error.value = ''
  loading.value = true
  try {
    await auth.login(email.value, password.value, keepLoggedIn.value)
    await router.push(auth.isVerified ? `/${useUiStore().activeVariant}/day` : '/verify-email')
  } catch (e: any) {
    error.value = e?.data?.message ?? 'Login failed'
  } finally {
    loading.value = false
  }
}
</script>
