import type { ComputedRef, Ref } from 'vue'

export function useSingleMatchAutocomplete<T>(
  items: Ref<T[]> | ComputedRef<T[]>,
  search: Ref<string>,
  titleKey: (item: T) => string,
) {
  return computed(() => {
    const q = (search.value || '').trim().toLowerCase()
    if (!q) return false
    const matches = items.value.filter(i => titleKey(i).toLowerCase().includes(q))
    return matches.length === 1
  })
}
