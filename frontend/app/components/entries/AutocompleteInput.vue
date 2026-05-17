<template>
  <div class="ac-wrap" style="position:relative">
    <v-text-field
      v-bind="$attrs"
      v-model="inputVal"
      :autocomplete="'off'"
      @input="onInput"
      @keydown="onKeydown"
      @focus="onFocus"
      @blur="onBlur"
    />
    <div v-if="show && filtered.length" class="ac-list" :style="listStyle">
      <div
        v-for="(item, i) in filtered"
        :key="item"
        class="ac-item"
        :class="{ active: i === activeIdx }"
        @mousedown.prevent="select(item)"
      >{{ item }}</div>
    </div>
  </div>
</template>

<script setup lang="ts">
defineOptions({ inheritAttrs: false })

const props = defineProps<{
  modelValue: string
  suggestions: string[]
}>()

const emit = defineEmits<{
  'update:modelValue': [string]
}>()

const inputVal = computed({
  get: () => props.modelValue,
  set: (v) => emit('update:modelValue', v),
})

const show = ref(false)
const activeIdx = ref(-1)
const listStyle = ref('top:100%;z-index:9999;')

const filtered = computed(() => {
  const q = (inputVal.value || '').toLowerCase()
  if (!q) return props.suggestions.slice(0, 8)
  return props.suggestions.filter(s => s.toLowerCase().includes(q)).slice(0, 8)
})

function onInput() { show.value = true; activeIdx.value = -1 }
function onFocus() { show.value = !!filtered.value.length }
function onBlur() { setTimeout(() => { show.value = false }, 150) }

function onKeydown(e: KeyboardEvent) {
  if (!show.value) return
  if (e.key === 'ArrowDown') { e.preventDefault(); activeIdx.value = Math.min(activeIdx.value + 1, filtered.value.length - 1) }
  else if (e.key === 'ArrowUp') { e.preventDefault(); activeIdx.value = Math.max(activeIdx.value - 1, 0) }
  else if (e.key === 'Enter' && activeIdx.value >= 0) { e.stopImmediatePropagation(); select(filtered.value[activeIdx.value]) }
  else if (e.key === 'Escape') { show.value = false }
}

function select(val: string) {
  inputVal.value = val
  show.value = false
  activeIdx.value = -1
}
</script>

<style scoped>
.ac-list {
  position: absolute;
  background: var(--v-theme-surface, #fff);
  border: 1px solid rgba(0,0,0,.12);
  border-radius: 4px;
  box-shadow: 0 4px 12px rgba(0,0,0,.15);
  min-width: 100%;
  max-height: 260px;
  overflow-y: auto;
}
.ac-item { padding: 8px 12px; cursor: pointer; font-size: 14px; }
.ac-item:hover, .ac-item.active { background: rgba(91,106,245,.12); }
</style>
