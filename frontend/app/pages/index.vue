<script setup lang="ts">
const ui = useUiStore()
const auth = useAuthStore()

if (auth.isLoggedIn && auth.isVerified) {
  await navigateTo(`/${ui.activeVariant}/day`)
} else if (auth.isLoggedIn) {
  await navigateTo('/verify-email')
} else {
  await navigateTo('/login')
}
</script>
<template><div /></template>
