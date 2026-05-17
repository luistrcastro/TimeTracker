export function useTimeFormat() {
  const ui = useUiStore()

  function minutesToHHMM(minutes: number): string {
    return `${Math.floor(minutes / 60)}:${String(minutes % 60).padStart(2, '0')}`
  }

  function minutesToDecimal(minutes: number): number {
    return Math.round(minutes / 60 / 0.25) * 0.25
  }

  function formatTime(hhmm: string | null | undefined): string {
    if (!hhmm) return ''
    if (!ui.use12h) return hhmm
    const [hStr, mStr] = hhmm.split(':')
    let h = parseInt(hStr, 10)
    const ampm = h >= 12 ? 'PM' : 'AM'
    h = h % 12 || 12
    return `${h}:${mStr} ${ampm}`
  }

  return { minutesToHHMM, minutesToDecimal, formatTime }
}
