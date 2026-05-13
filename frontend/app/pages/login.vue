<template>
  <v-card rounded="lg" elevation="2" class="pa-6">
    <div class="text-h5 font-weight-bold mb-6">Sign in</div>

    <v-form @submit.prevent="submit" :disabled="loading">
      <v-text-field
        v-model="email" label="Email" type="email"
        variant="outlined" class="mb-3" required autofocus
      />
      <v-text-field
        v-model="password" label="Password" type="password"
        variant="outlined" class="mb-4" required
      />

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

const email = ref('')
const password = ref('')
const error = ref('')
const loading = ref(false)

async function submit() {
  error.value = ''
  loading.value = true
  try {
    await auth.login(email.value, password.value)
    await router.push(auth.isVerified ? `/${useUiStore().activeVariant}/day` : '/verify-email')
  } catch (e: any) {
    error.value = e?.data?.message ?? 'Login failed'
  } finally {
    loading.value = false
  }
}
</script>
