<template>
  <div class="d-flex align-center" style="min-width:96px" @click.stop>
    <template v-if="!editing">
      <ActiveStatusIcon :active="active" />
      <v-tooltip v-if="!syncActive" location="top" text="Not returned by the latest Replicon sync — must be re-synced before it can be reactivated">
        <template #activator="{ props }">
          <span v-bind="props">
            <v-btn icon="mdi-pencil" size="x-small" variant="text" density="compact" disabled />
          </span>
        </template>
      </v-tooltip>
      <v-btn v-else icon="mdi-pencil" size="x-small" variant="text" density="compact" @click="start" />
    </template>
    <template v-else>
      <v-switch v-model="pending" density="compact" hide-details color="primary" class="mt-0 flex-grow-0" />
      <v-btn icon="mdi-check" size="x-small" variant="text" color="success" density="compact" :loading="saving" @click="confirm" />
      <v-btn icon="mdi-close" size="x-small" variant="text" density="compact" :disabled="saving" @click="cancel" />
    </template>
  </div>
</template>

<script setup lang="ts">
const props = defineProps<{
  active: boolean
  syncActive: boolean
  save: (value: boolean) => Promise<void>
}>()

const editing = ref(false)
const pending = ref(props.active)
const saving = ref(false)

function start() {
  pending.value = props.active
  editing.value = true
}

function cancel() {
  editing.value = false
}

async function confirm() {
  saving.value = true
  try {
    await props.save(pending.value)
    editing.value = false
  } finally {
    saving.value = false
  }
}
</script>
