<template>
  <tr class="new-row">
    <td>
      <ContractorClientSelect
        v-model="form.clientId"
        variant="underlined"
        placeholder="Client"
        hide-details
        @update:model-value="form.taskId = null"
      />
    </td>
    <td>
      <ContractorTaskSelect
        v-model="form.taskId"
        :client-id="form.clientId"
        variant="underlined"
        placeholder="Task"
        hide-details
      />
    </td>
    <td>
      <v-text-field
        v-model="form.description"
        density="compact"
        variant="underlined"
        placeholder="Description"
        hide-details
        ref="descRef"
        @keydown.enter.prevent="focusNext('start')"
      />
    </td>
    <td>
      <TimeField
        v-model="form.start"
        density="compact"
        variant="underlined"
        hide-details
        ref="startRef"
        @keydown.enter.prevent="focusNext('finish')"
      />
    </td>
    <td>
      <TimeField
        v-model="form.finish"
        density="compact"
        variant="underlined"
        hide-details
        ref="finishRef"
        @input="calcDuration"
        @keydown.enter.prevent="save"
      />
    </td>
    <td class="text-body-2">{{ form.duration }}</td>
    <td></td>
    <td></td>
    <td>
      <v-btn size="x-small" color="primary" :disabled="!canSave || saving" :loading="saving" @click="save">Save</v-btn>
    </td>
  </tr>
</template>

<script setup lang="ts">
interface PrefillData { clientId: string | null; taskId: string | null; description: string }
const props = defineProps<{ prefillStart: string; prefill?: PrefillData | null }>()

const contractor = useContractorStore()
const ui = useUiStore()

const descRef = ref()
const startRef = ref()
const finishRef = ref()

const form = reactive({
  clientId: null as string | null,
  taskId:   null as string | null,
  description: '',
  start:    '',
  finish:   '',
  duration: '0:00',
  durationMinutes: 0,
})

watch(() => props.prefillStart, (v) => { if (!form.start) form.start = v })
watch(() => props.prefill, (p) => {
  if (!p) return
  form.clientId    = p.clientId
  form.taskId      = p.taskId
  form.description = p.description
})

function calcDuration() {
  if (form.start && form.finish) {
    let diff = timeToMinutes(form.finish) - timeToMinutes(form.start)
    if (diff < 0) diff += 24 * 60
    if (diff > 0) {
      form.durationMinutes = diff
      form.duration = `${Math.floor(diff / 60)}:${String(diff % 60).padStart(2, '0')}`
      return
    }
  }
  form.durationMinutes = 0
  form.duration = '0:00'
}

function timeToMinutes(hhmm: string) {
  const [h, m] = hhmm.split(':').map(Number)
  return h * 60 + m
}

const saving = ref(false)

const canSave = computed(() => !!form.description && !!form.start && !!form.finish)

function focusNext(field: string) {
  const map: Record<string, any> = { start: startRef, finish: finishRef }
  map[field]?.value?.$el?.querySelector('input')?.focus()
}

async function save() {
  if (!canSave.value) return
  saving.value = true
  try {
    const task = contractor.clients
      .find(c => c.id === form.clientId)?.tasks
      ?.find(t => t.id === form.taskId)

    await contractor.create({
      date:            ui.currentDate,
      clientId:        form.clientId ?? undefined,
      clientTaskId:    form.taskId ?? null,
      task:            task?.name ?? '',
      description:     form.description,
      subDescription:  '',
      start:           form.start,
      finish:          form.finish,
      duration:        form.duration,
      durationMinutes: form.durationMinutes,
    })

    const savedFinish = form.finish
    form.clientId    = null
    form.taskId      = null
    form.description = ''
    form.start       = savedFinish
    form.finish      = ''
    form.duration    = '0:00'
    form.durationMinutes = 0
  } finally {
    saving.value = false
  }
}
</script>

<style scoped>
.new-row td { background: rgba(0,0,0,.02); }
.new-row :deep(input[type="time"]::-webkit-calendar-picker-indicator) { display: none; }
</style>
