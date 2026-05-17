<template>
  <v-card rounded="lg" elevation="2" class="pa-6 text-center">
    <v-icon size="56" color="primary" class="mb-4">mdi-email-check-outline</v-icon>
    <div class="text-h6 font-weight-bold mb-2">Verify your email</div>
    <div class="text-body-2 mb-6 text-medium-emphasis">
      We sent a verification link to <strong>{{ auth.user?.email || route.query.email }}</strong>.
      Check your inbox and click the link.
    </div>

    <v-alert v-if="verified" type="success" class="mb-4">Email verified! Redirecting…</v-alert>
    <v-alert v-if="resent" type="info" class="mb-4">Verification email resent.</v-alert>
    <v-alert v-if="error" type="error" class="mb-4">{{ error }}</v-alert>

    <v-btn color="primary" block :loading="loading" @click="resend" class="mb-3">
      Resend verification email
    </v-btn>
    <v-btn variant="text" @click="auth.logout().then(() => router.push('/login'))">
      Sign out
    </v-btn>
  </v-card>
</template>

<script setup lang="ts">
definePageMeta({ layout: 'auth' })

const auth = useAuthStore()
const router = useRouter()
const route = useRoute()

const loading = ref(false)
const resent = ref(false)
const verified = ref(false)
const error = ref('')

onMounted(async () => {
  if (route.query.verified === '1') {
    try {
      await auth.me()
      verified.value = true
      setTimeout(() => router.push(`/${useUiStore().activeVariant}/day`), 1500)
    } catch (e: any) {
      error.value = e?.data?.message ?? 'Verification failed'
    }
  }
})

async function resend() {
  error.value = ''
  loading.value = true
  try {
    await auth.resendVerification()
    resent.value = true
  } catch (e: any) {
    error.value = e?.data?.message ?? 'Failed to resend'
  } finally {
    loading.value = false
  }
}
</script>
