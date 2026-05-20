<template>
  <div class="d-flex justify-center">
    <v-col cols="12" sm="8" md="6" lg="5">

      <!-- Avatar -->
      <v-card rounded="lg" class="mb-4">
        <v-card-text class="d-flex flex-column align-center py-6 gap-3">
          <div
            class="avatar-drop-zone"
            :class="{ 'is-dragging': isDragging }"
            @click="avatarInput?.click()"
            @dragover.prevent="isDragging = true"
            @dragleave.prevent="isDragging = false"
            @drop.prevent="onDrop"
          >
            <v-avatar
              size="100"
              :image="auth.user?.avatar_url || undefined"
              :icon="auth.user?.avatar_url ? undefined : 'mdi-account'"
              :color="auth.user?.avatar_url ? undefined : 'primary'"
            />
            <div class="avatar-overlay">
              <v-icon size="24" color="white">mdi-camera</v-icon>
              <span class="text-caption text-white mt-1">{{ auth.user?.avatar_url ? 'Change' : 'Upload' }}</span>
            </div>
            <v-progress-circular v-if="avatarLoading" indeterminate color="white" size="28" width="2" class="avatar-loader" />
          </div>

          <div class="text-center">
            <div class="text-body-1 font-weight-medium">{{ auth.user?.name }}</div>
            <div class="text-caption text-medium-emphasis">{{ auth.user?.email }}</div>
          </div>

          <div class="d-flex gap-2 align-center">
            <v-btn size="small" variant="tonal" color="primary" prepend-icon="mdi-upload" :loading="avatarLoading" @click="avatarInput?.click()">
              Upload photo
            </v-btn>
            <v-btn v-if="auth.user?.avatar_url" size="small" variant="text" color="error" :loading="avatarLoading" @click="removeAvatar">
              Remove
            </v-btn>
          </div>

          <div class="text-caption text-disabled">Max 512 KB · JPG, PNG, GIF, WEBP</div>
          <v-alert v-if="avatarError" type="error" density="compact" class="w-100">{{ avatarError }}</v-alert>
        </v-card-text>
        <input ref="avatarInput" type="file" accept="image/*" class="d-none" @change="onAvatarChange" />
      </v-card>

      <!-- Name -->
      <v-card rounded="lg" class="mb-4">
        <v-card-title class="text-subtitle-1 font-weight-bold pt-4 px-4">Display name</v-card-title>
        <v-card-text class="px-4 pb-4">
          <v-form @submit.prevent="saveName">
            <v-text-field v-model="name" label="Name" variant="outlined" density="compact" class="mb-3" :disabled="nameLoading" />
            <v-alert v-if="nameSuccess" type="success" density="compact" class="mb-3">Name updated.</v-alert>
            <v-alert v-if="nameError" type="error" density="compact" class="mb-3">{{ nameError }}</v-alert>
            <v-btn type="submit" color="primary" size="small" :loading="nameLoading" :disabled="name === auth.user?.name">Save</v-btn>
          </v-form>
        </v-card-text>
      </v-card>

      <!-- Password -->
      <v-card rounded="lg">
        <v-card-title class="text-subtitle-1 font-weight-bold pt-4 px-4">Change password</v-card-title>
        <v-card-text class="px-4 pb-4">
          <v-form @submit.prevent="savePassword">
            <v-text-field
              v-model="currentPassword" label="Current password" :type="showCurrent ? 'text' : 'password'"
              variant="outlined" density="compact" class="mb-3" :disabled="pwLoading"
              :append-inner-icon="showCurrent ? 'mdi-eye-off' : 'mdi-eye'"
              @click:append-inner="showCurrent = !showCurrent"
            />
            <v-text-field
              v-model="newPassword" label="New password" :type="showNew ? 'text' : 'password'"
              variant="outlined" density="compact" class="mb-3" :disabled="pwLoading"
              :append-inner-icon="showNew ? 'mdi-eye-off' : 'mdi-eye'"
              @click:append-inner="showNew = !showNew"
            />
            <v-text-field
              v-model="confirmPassword" label="Confirm new password" :type="showConfirm ? 'text' : 'password'"
              variant="outlined" density="compact" class="mb-3" :disabled="pwLoading"
              :append-inner-icon="showConfirm ? 'mdi-eye-off' : 'mdi-eye'"
              @click:append-inner="showConfirm = !showConfirm"
            />
            <v-alert v-if="pwSuccess" type="success" density="compact" class="mb-3">Password updated.</v-alert>
            <v-alert v-if="pwError" type="error" density="compact" class="mb-3">{{ pwError }}</v-alert>
            <v-btn type="submit" color="primary" size="small" :loading="pwLoading">Update password</v-btn>
          </v-form>
        </v-card-text>
      </v-card>

    </v-col>
  </div>
</template>

<script setup lang="ts">
definePageMeta({ layout: 'default', title: 'Profile' })

const auth = useAuthStore()

// Avatar
const avatarInput = ref<HTMLInputElement | null>(null)
const avatarLoading = ref(false)
const avatarError = ref('')
const isDragging = ref(false)

async function uploadFile(file: File) {
  if (file.size > 512 * 1024) {
    avatarError.value = 'Image must be under 512 KB.'
    return
  }
  avatarError.value = ''
  avatarLoading.value = true
  try {
    await auth.uploadAvatar(file)
  } catch (err: any) {
    avatarError.value = err?.data?.message ?? 'Upload failed.'
  } finally {
    avatarLoading.value = false
    if (avatarInput.value) avatarInput.value.value = ''
  }
}

async function onAvatarChange(e: Event) {
  const file = (e.target as HTMLInputElement).files?.[0]
  if (file) await uploadFile(file)
}

async function onDrop(e: DragEvent) {
  isDragging.value = false
  const file = e.dataTransfer?.files?.[0]
  if (!file) return
  if (!file.type.startsWith('image/')) {
    avatarError.value = 'Please drop an image file.'
    return
  }
  await uploadFile(file)
}

async function removeAvatar() {
  avatarError.value = ''
  avatarLoading.value = true
  try {
    await auth.deleteAvatar()
  } catch (err: any) {
    avatarError.value = err?.data?.message ?? 'Failed to remove avatar.'
  } finally {
    avatarLoading.value = false
  }
}

// Name
const name = ref(auth.user?.name ?? '')
const nameLoading = ref(false)
const nameSuccess = ref(false)
const nameError = ref('')

async function saveName() {
  nameError.value = ''
  nameSuccess.value = false
  nameLoading.value = true
  try {
    await auth.updateProfile(name.value)
    nameSuccess.value = true
    setTimeout(() => { nameSuccess.value = false }, 3000)
  } catch (err: any) {
    nameError.value = err?.data?.message ?? 'Failed to update name.'
  } finally {
    nameLoading.value = false
  }
}

// Password
const currentPassword = ref('')
const newPassword = ref('')
const confirmPassword = ref('')
const showCurrent = ref(false)
const showNew = ref(false)
const showConfirm = ref(false)
const pwLoading = ref(false)
const pwSuccess = ref(false)
const pwError = ref('')

async function savePassword() {
  pwError.value = ''
  pwSuccess.value = false
  if (newPassword.value !== confirmPassword.value) {
    pwError.value = 'Passwords do not match.'
    return
  }
  pwLoading.value = true
  try {
    await auth.updatePassword(currentPassword.value, newPassword.value, confirmPassword.value)
    pwSuccess.value = true
    currentPassword.value = ''
    newPassword.value = ''
    confirmPassword.value = ''
    setTimeout(() => { pwSuccess.value = false }, 3000)
  } catch (err: any) {
    pwError.value = err?.data?.message ?? 'Failed to update password.'
  } finally {
    pwLoading.value = false
  }
}
</script>

<style scoped>
.avatar-drop-zone {
  position: relative;
  width: 100px;
  height: 100px;
  border-radius: 50%;
  cursor: pointer;
  transition: transform 0.15s ease;
}

.avatar-drop-zone:hover {
  transform: scale(1.03);
}

.avatar-overlay {
  position: absolute;
  inset: 0;
  border-radius: 50%;
  background: rgba(0, 0, 0, 0.45);
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  opacity: 0;
  transition: opacity 0.18s ease;
  pointer-events: none;
  z-index: 1;
}

.avatar-drop-zone:hover .avatar-overlay,
.avatar-drop-zone.is-dragging .avatar-overlay {
  opacity: 1;
}

.avatar-drop-zone.is-dragging {
  box-shadow: 0 0 0 3px rgb(var(--v-theme-primary));
}

.avatar-loader {
  position: absolute;
  inset: 0;
  margin: auto;
  z-index: 2;
}
</style>
