<template>
  <v-card rounded="lg" elevation="2" class="pa-6">
    <div class="text-h5 font-weight-bold mb-6">Create account</div>

    <v-form @submit.prevent="submit" :disabled="loading">
      <v-text-field v-model="name" label="Name" variant="outlined" class="mb-3" required autofocus />
      <v-text-field v-model="email" label="Email" type="email" variant="outlined" class="mb-3" required />
      <v-text-field v-model="password" label="Password" type="password" variant="outlined" class="mb-3" required />
      <v-text-field v-model="confirm" label="Confirm password" type="password" variant="outlined" class="mb-4" required />

      <v-alert v-if="error" type="error" class="mb-4" density="compact">{{ error }}</v-alert>

      <v-btn type="submit" color="primary" block size="large" :loading="loading">Create account</v-btn>
    </v-form>

    <div class="mt-4 text-center text-body-2">
      Already have an account? <NuxtLink to="/login">Sign in</NuxtLink>
    </div>
  </v-card>
</template>

<script setup lang="ts">
definePageMeta({ layout: 'auth' })

const auth = useAuthStore()
const router = useRouter()

const name = ref('')
const email = ref('')
const password = ref('')
const confirm = ref('')
const error = ref('')
const loading = ref(false)

async function submit() {
  error.value = ''
  loading.value = true
  try {
    await auth.register(name.value, email.value, password.value, confirm.value)
    await router.push('/verify-email')
  } catch (e: any) {
    const errs = e?.data?.errors
    error.value = errs ? Object.values(errs).flat().join(' ') : (e?.data?.message ?? 'Registration failed')
  } finally {
    loading.value = false
  }
}
</script>
