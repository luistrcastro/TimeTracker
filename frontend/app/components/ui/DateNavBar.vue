<template>
  <div class="d-flex align-center gap-2">
    <v-btn icon="mdi-chevron-left" variant="text" size="small" @click="prevDay" />
    <v-btn variant="text" size="small" @click="goToday">Today</v-btn>
    <v-btn icon="mdi-chevron-right" variant="text" size="small" @click="nextDay" />
    <span class="font-weight-medium">{{ formattedDate }}</span>
    <v-spacer />
    <slot />
  </div>
</template>

<script setup lang="ts">
const ui = useUiStore()

const formattedDate = computed(() => {
  const d = new Date(ui.currentDate + 'T00:00:00')
  return d.toLocaleDateString('en-CA', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })
})

function prevDay() {
  const d = new Date(ui.currentDate + 'T00:00:00')
  d.setDate(d.getDate() - 1)
  ui.setDate(d.toLocaleDateString('en-CA'))
}

function nextDay() {
  const d = new Date(ui.currentDate + 'T00:00:00')
  d.setDate(d.getDate() + 1)
  ui.setDate(d.toLocaleDateString('en-CA'))
}

function goToday() {
  ui.setDate(new Date().toLocaleDateString('en-CA'))
}
</script>
