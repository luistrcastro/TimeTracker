<template>
  <v-dialog v-model="model" max-width="500">
    <v-card>
      <v-card-title>Copy from another day</v-card-title>
      <v-card-text>
        <div class="d-flex align-center gap-2 mb-4">
          <v-btn icon="mdi-chevron-left" variant="text" size="small" @click="shiftDate(-1)" />
          <v-text-field v-model="pickerDate" type="date" density="compact" variant="outlined" hide-details style="max-width:160px" />
          <v-btn icon="mdi-chevron-right" variant="text" size="small" @click="shiftDate(1)" />
        </div>

        <p v-if="!dayEntries.length" class="text-medium-emphasis">No entries on this date.</p>

        <div v-else>
          <div class="d-flex justify-space-between mb-2">
            <v-btn size="small" variant="text" @click="toggleAll">{{ allSelected ? 'Deselect all' : 'Select all' }}</v-btn>
          </div>
          <v-list density="compact" lines="two">
            <v-list-item
              v-for="e in dayEntries" :key="e.id"
              :prepend-icon="selected.has(e.id) ? 'mdi-checkbox-marked' : 'mdi-checkbox-blank-outline'"
              :title="`${e.clientName ?? ''} · ${e.task ?? ''}`"
              :subtitle="`${e.description}${e.subDescription ? ' — ' + e.subDescription : ''}`"
              @click="toggle(e.id)"
            />
          </v-list>
          <p class="text-caption text-medium-emphasis mt-2">Times will not be copied</p>
        </div>
      </v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" @click="model = false">Cancel</v-btn>
        <v-btn color="primary" :disabled="!selected.size" @click="confirm">
          Copy selected ({{ selected.size }})
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup lang="ts">
import type { TimeEntry } from '~/types'

interface EntryWithClientName extends TimeEntry {
  clientName?: string
}

const props = defineProps<{
  modelValue: boolean
  entriesByDate: Record<string, EntryWithClientName[]>
}>()

const emit = defineEmits<{
  'update:modelValue': [boolean]
  copy: [ids: string[], date: string]
}>()

const model = computed({
  get: () => props.modelValue,
  set: (v) => emit('update:modelValue', v),
})

const ui = useUiStore()

const pickerDate = ref('')
const selected = ref(new Set<string>())

watch(() => props.modelValue, (open) => {
  if (open) {
    const yesterday = new Date(ui.currentDate + 'T00:00:00')
    yesterday.setDate(yesterday.getDate() - 1)
    pickerDate.value = yesterday.toISOString().slice(0, 10)
    selected.value = new Set()
  }
})

const dayEntries = computed(() => {
  const entries = (props.entriesByDate[pickerDate.value] ?? [])
    .slice()
    .sort((a, b) => (a.start ?? '') > (b.start ?? '') ? 1 : -1)
  return entries.map(e => ({
    ...e,
    clientName: e.clientName ?? '',
  }))
})

const allSelected = computed(() =>
  dayEntries.value.length > 0 && dayEntries.value.every(e => selected.value.has(e.id))
)

function toggle(id: string) {
  const next = new Set(selected.value)
  if (next.has(id)) next.delete(id)
  else next.add(id)
  selected.value = next
}

function toggleAll() {
  if (allSelected.value) selected.value = new Set()
  else selected.value = new Set(dayEntries.value.map(e => e.id))
}

function shiftDate(delta: number) {
  const d = new Date(pickerDate.value + 'T00:00:00')
  d.setDate(d.getDate() + delta)
  pickerDate.value = d.toISOString().slice(0, 10)
}

function confirm() {
  emit('copy', [...selected.value], pickerDate.value)
}
</script>
