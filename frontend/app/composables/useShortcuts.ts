export function useShortcuts() {
  const ui = useUiStore()
  const router = useRouter()
  const route = useRoute()

  function handleKey(e: KeyboardEvent) {
    const tag = (e.target as HTMLElement)?.tagName
    const inInput = ['INPUT', 'SELECT', 'TEXTAREA'].includes(tag)
    if (inInput) return

    const variant = route.path.startsWith('/contractor') ? 'contractor' : 'replicon'

    switch (e.key) {
      case 'ArrowLeft':
      case '[': {
        const d = new Date(ui.currentDate); d.setDate(d.getDate() - 1)
        ui.setDate(d.toISOString().slice(0, 10))
        break
      }
      case 'ArrowRight':
      case ']': {
        const d = new Date(ui.currentDate); d.setDate(d.getDate() + 1)
        ui.setDate(d.toISOString().slice(0, 10))
        break
      }
      case 't':
      case 'T':
        ui.setDate(new Date().toISOString().slice(0, 10))
        break
      case '1': router.push(`/${variant}/day`); break
      case '2': router.push(`/${variant}/week`); break
      case '3': router.push(`/${variant}/compiled`); break
      case '4': router.push(`/${variant}/settings`); break
      case '5': if (variant === 'contractor') router.push('/contractor/invoicing'); break
    }
  }

  onMounted(() => window.addEventListener('keydown', handleKey))
  onUnmounted(() => window.removeEventListener('keydown', handleKey))
}
