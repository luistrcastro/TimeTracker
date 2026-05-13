import type { TimeEntry } from '~/types'

export interface GapRow { type: 'gap'; minutes: number; afterId: string }
export interface OverlapMark { id: string }

export function useGapOverlap() {
  function detectGapsAndOverlaps(entries: TimeEntry[]) {
    const gaps: GapRow[] = []
    const overlaps = new Set<string>()

    const withTimes = entries
      .filter(e => e.start && e.finish)
      .sort((a, b) => (a.start! > b.start! ? 1 : -1))

    for (let i = 0; i < withTimes.length - 1; i++) {
      const curr = withTimes[i]
      const next = withTimes[i + 1]
      const currFinishMin = timeToMinutes(curr.finish!)
      const nextStartMin = timeToMinutes(next.start!)

      if (currFinishMin < nextStartMin) {
        gaps.push({ type: 'gap', minutes: nextStartMin - currFinishMin, afterId: curr.id })
      } else if (currFinishMin > nextStartMin) {
        overlaps.add(curr.id)
        overlaps.add(next.id)
      }
    }

    return { gaps, overlaps }
  }

  function timeToMinutes(hhmm: string): number {
    const [h, m] = hhmm.split(':').map(Number)
    return h * 60 + m
  }

  return { detectGapsAndOverlaps, timeToMinutes }
}
