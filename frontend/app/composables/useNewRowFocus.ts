const focusHandler = ref<(() => void) | null>(null)

export function useNewRowFocus() {
  function register(fn: () => void) {
    focusHandler.value = fn
  }
  function unregister() {
    focusHandler.value = null
  }
  function trigger() {
    focusHandler.value?.()
  }
  return { register, unregister, trigger }
}
