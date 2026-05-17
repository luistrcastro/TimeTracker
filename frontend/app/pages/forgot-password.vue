<template>
  <v-card rounded="lg" elevation="2" class="pa-6">
    <div class="text-h5 font-weight-bold mb-6">Reset password</div>
    <v-form @submit.prevent="submit" :disabled="loading">
      <v-text-field v-model="email" label="Email" type="email" variant="outlined" class="mb-4" required autofocus />
      <v-alert v-if="sent" type="success" class="mb-4">If that email is registered, a reset link was sent.</v-alert>
      <v-alert v-if="error" type="error" class="mb-4">{{ error }}</v-alert>
      <v-btn type="submit" color="primary" block size="large" :loading="loading">Send reset link</v-btn>
    </v-form>
    <div class="mt-4 text-center text-body-2">
      <NuxtLink to="/login">Back to sign in</NuxtLink>
    </div>
  </v-card>
</template>

<script setup lang="ts">
definePageMeta({ layout: 'auth' })
const auth = useAuthStore()
const email = ref(''); const sent = ref(false); const error = ref(''); const loading = ref(false)
async function submit() {
  error.value = ''; loading.value = true
  try { await auth.forgotPassword(email.value); sent.value = true }
  catch (e: any) { error.value = e?.data?.message ?? 'Error' }
  finally { loading.value = false }
}
</script>
