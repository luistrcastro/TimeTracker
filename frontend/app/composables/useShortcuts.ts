export function useShortcuts() {
  const ui = useUiStore()
  const router = useRouter()
  const route = useRoute()

  function getTabs() {
    const isReplicon = route.path.startsWith('/replicon')
    const base = isReplicon ? '/replicon' : '/contractor'
    return [
      `${base}/day`,
      `${base}/week`,
      `${base}/compiled`,
      ...(isReplicon ? [] : [`${base}/invoicing`]),
      `${base}/settings`,
    ]
  }

  function handleKey(e: KeyboardEvent) {
    const tag = (e.target as HTMLElement)?.tagName
    const inInput = ['INPUT', 'SELECT', 'TEXTAREA'].includes(tag)
    if (inInput) return

    switch (e.key) {
      case 'ArrowLeft': {
        const d = new Date(ui.currentDate + 'T00:00:00'); d.setDate(d.getDate() - 1)
        ui.setDate(d.toLocaleDateString('en-CA'))
        break
      }
      case 'ArrowRight': {
        const d = new Date(ui.currentDate + 'T00:00:00'); d.setDate(d.getDate() + 1)
        ui.setDate(d.toLocaleDateString('en-CA'))
        break
      }
      case '[': {
        const tabs = getTabs()
        const idx = tabs.indexOf(route.path)
        if (idx > 0) router.push(tabs[idx - 1])
        break
      }
      case ']': {
        const tabs = getTabs()
        const idx = tabs.indexOf(route.path)
        if (idx !== -1 && idx < tabs.length - 1) router.push(tabs[idx + 1])
        break
      }
      case 't':
      case 'T':
        ui.setDate(new Date().toLocaleDateString('en-CA'))
        break
      case '?':
        ui.shortcutsDialog = !ui.shortcutsDialog
        break
    }
  }

  onMounted(() => window.addEventListener('keydown', handleKey))
  onUnmounted(() => window.removeEventListener('keydown', handleKey))
}
