<template>
  <tr class="new-row">
    <td>
      <v-select
        v-model="form.clientId"
        :items="contractor.clients"
        item-title="name"
        item-value="id"
        density="compact"
        variant="underlined"
        placeholder="Client"
        hide-details
        clearable
        @update:model-value="form.taskId = null"
      />
    </td>
    <td>
      <v-select
        v-model="form.taskId"
        :items="taskOptions"
        item-title="name"
        item-value="id"
        density="compact"
        variant="underlined"
        placeholder="Task"
        hide-details
        clearable
        :disabled="!taskOptions.length"
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
        @keydown.enter.prevent="focusNext('subdesc')"
      />
    </td>
    <td>
      <v-text-field
        v-model="form.subDescription"
        density="compact"
        variant="underlined"
        placeholder="Sub-description"
        hide-details
        ref="subdescRef"
        @keydown.enter.prevent="focusNext('start')"
      />
    </td>
    <td>
      <v-text-field
        v-model="form.start"
        type="time"
        density="compact"
        variant="underlined"
        hide-details
        ref="startRef"
        @keydown.enter.prevent="focusNext('finish')"
      />
    </td>
    <td>
      <v-text-field
        v-model="form.finish"
        type="time"
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
      <v-btn size="x-small" color="primary" :disabled="!canSave" @click="save" >Save</v-btn>
    </td>
  </tr>
</template>

<script setup lang="ts">
const props = defineProps<{ prefillStart: string }>()

const contractor = useContractorStore()
const ui = useUiStore()

const descRef = ref()
const subdescRef = ref()
const startRef = ref()
const finishRef = ref()

const form = reactive({
  clientId: null as string | null,
  taskId:   null as string | null,
  description:    '',
  subDescription: '',
  start:    '',
  finish:   '',
  duration: '0:00',
  durationMinutes: 0,
})

const taskOptions = computed(() =>
  contractor.clients.find(c => c.id === form.clientId)?.tasks ?? []
)

watch(() => props.prefillStart, (v) => { if (!form.start) form.start = v })

function calcDuration() {
  if (form.start && form.finish) {
    const diff = timeToMinutes(form.finish) - timeToMinutes(form.start)
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

const canSave = computed(() => !!form.description && !!form.start && !!form.finish)

function focusNext(field: string) {
  const map: Record<string, any> = { subdesc: subdescRef, start: startRef, finish: finishRef }
  map[field]?.value?.$el?.querySelector('input')?.focus()
}

async function save() {
  if (!canSave.value) return

  const task = contractor.clients
    .find(c => c.id === form.clientId)?.tasks
    ?.find(t => t.id === form.taskId)

  await contractor.create({
    date:            ui.currentDate,
    clientId:        form.clientId ?? undefined,
    clientTaskId:    form.taskId ?? null,
    task:            task?.name ?? '',
    description:     form.description,
    subDescription:  form.subDescription,
    start:           form.start,
    finish:          form.finish,
    duration:        form.duration,
    durationMinutes: form.durationMinutes,
  })

  const savedFinish = form.finish
  form.clientId       = null
  form.taskId         = null
  form.description    = ''
  form.subDescription = ''
  form.start          = savedFinish
  form.finish         = ''
  form.duration       = '0:00'
  form.durationMinutes = 0
}
</script>

<style scoped>
.new-row td { background: rgba(0,0,0,.02); }
</style>
