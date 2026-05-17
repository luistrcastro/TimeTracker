<template>
  <tr class="new-row">
    <td>
      <RepliconProjectSelect
        v-model="form.projectId"
        variant="underlined"
        placeholder="Project"
        hide-details
        @update:model-value="form.taskId = null"
      />
    </td>
    <td>
      <RepliconSubProjectSelect
        v-model="form.taskId"
        :project-id="form.projectId"
        variant="underlined"
        placeholder="Sub-project"
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
      <v-btn size="x-small" variant="text" @click="clear(null)">Clear</v-btn>
    </td>
  </tr>
</template>

<script setup lang="ts">
interface PrefillData { projectId: string | null; taskId: string | null; description: string; subDescription: string }
const props = defineProps<{ prefillStart: string; prefill?: PrefillData | null }>()

const replicon = useRepliconStore()
const ui = useUiStore()

const descRef = ref()
const subdescRef = ref()
const startRef = ref()
const finishRef = ref()

const form = reactive({
  projectId: null as string | null,
  taskId:    null as string | null,
  description:    '',
  subDescription: '',
  start:    '',
  finish:   '',
  duration: '0:00',
  durationMinutes: 0,
})

watch(() => props.prefillStart, (v) => { if (!form.start) form.start = v })
watch(() => props.prefill, (p) => {
  if (!p) return
  form.projectId      = p.projectId
  form.taskId         = p.taskId
  form.description    = p.description
  form.subDescription = p.subDescription
})

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

  const project = replicon.projects.find(p => p.id === form.projectId)
  const task    = project?.tasks.find(t => t.id === form.taskId)

  await replicon.create({
    date:            ui.currentDate,
    project:         project?.code ?? '',
    subProject:      task?.name ?? '',
    repliconTaskId:  form.taskId ?? null,
    description:     form.description,
    subDescription:  form.subDescription,
    start:           form.start,
    finish:          form.finish,
    duration:        form.duration,
    durationMinutes: form.durationMinutes,
    logged:          false,
  })

  clear(form.finish)
}

function clear(startTime: string|null) {
  const nextStart = !!startTime
    ? startTime
    : (props.prefillStart || form.start)
  form.projectId      = null
  form.taskId         = null
  form.description    = ''
  form.subDescription = ''
  form.start          = nextStart
  form.finish         = ''
  form.duration       = '0:00'
  form.durationMinutes = 0
}
</script>

<style scoped>
.new-row td { background: rgba(0,0,0,.02); }
</style>
