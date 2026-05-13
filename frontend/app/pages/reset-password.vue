<template>
  <v-card rounded="lg" elevation="2" class="pa-6">
    <div class="text-h5 font-weight-bold mb-6">Set new password</div>
    <v-form @submit.prevent="submit" :disabled="loading">
      <v-text-field v-model="password" label="New password" type="password" variant="outlined" class="mb-3" required autofocus />
      <v-text-field v-model="confirm" label="Confirm password" type="password" variant="outlined" class="mb-4" required />
      <v-alert v-if="done" type="success" class="mb-4">Password reset. <NuxtLink to="/login">Sign in</NuxtLink></v-alert>
      <v-alert v-if="error" type="error" class="mb-4">{{ error }}</v-alert>
      <v-btn type="submit" color="primary" block size="large" :loading="loading" :disabled="done">Reset password</v-btn>
    </v-form>
  </v-card>
</template>

<script setup lang="ts">
definePageMeta({ layout: 'auth' })
const auth = useAuthStore()
const route = useRoute()
const password = ref(''); const confirm = ref(''); const done = ref(false); const error = ref(''); const loading = ref(false)
async function submit() {
  error.value = ''; loading.value = true
  try {
    await auth.resetPassword(String(route.query.token ?? ''), String(route.query.email ?? ''), password.value, confirm.value)
    done.value = true
  } catch (e: any) { error.value = e?.data?.message ?? 'Error' }
  finally { loading.value = false }
}
</script>
