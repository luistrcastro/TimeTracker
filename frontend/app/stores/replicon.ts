import type { TimeEntry } from '~/types'

export interface RepliconProject {
  id: string
  repliconId: string
  code: string
  name: string
  tasks: Array<{ id: string; repliconTaskId: string; name: string }>
}

export interface RepliconCredentials {
  configured: boolean
  base_url: string
  session_id: string
  server_view_state_id: string
  cookie_set: boolean
}

export const useRepliconStore = defineStore('replicon', {
  state: () => ({
    entries: [] as TimeEntry[],
    credentials: null as RepliconCredentials | null,
    projects: [] as RepliconProject[],
    rowMap: {} as Record<string, number>,
    mode: 'free' as 'free' | 'proj',
    credsOk: false,
    deletedEntry: null as TimeEntry | null,
    undoTimer: null as ReturnType<typeof setTimeout> | null,
    syncing: false,
    lastSyncStatus: '' as string,
    submitResults: [] as any[],
  }),

  getters: {
    projectById: (state) => (id: string) => state.projects.find(p => p.id === id),
    taskById: (state) => (projectId: string, taskId: string) => {
      const proj = state.projects.find(p => p.id === projectId)
      return proj?.tasks.find(t => t.id === taskId)
    },
    projectSuggestions: (state) => state.projects.map(p => p.name),
    taskSuggestionsFor: (state) => (projectName: string) => {
      const proj = state.projects.find(p => p.name === projectName)
      return proj?.tasks.map(t => t.name) ?? []
    },
  },

  actions: {
    async loadEntries(date?: string) {
      const api = useApi()
      const params = date ? { date } : {}
      const data = await api<TimeEntry[]>('/api/replicon/entries', { params })
      if (date) {
        this.entries = [
          ...this.entries.filter(e => e.date !== date),
          ...(data as TimeEntry[]),
        ]
      } else {
        this.entries = data as TimeEntry[]
      }
    },

    async create(entry: Omit<TimeEntry, 'id'>) {
      const api = useApi()
      const created = await api<TimeEntry>('/api/replicon/entries', {
        method: 'POST',
        body: this.toApiPayload(entry),
      })
      this.entries.push(created as TimeEntry)
      return created as TimeEntry
    },

    async update(id: string, changes: Partial<TimeEntry>) {
      const api = useApi()
      const updated = await api<TimeEntry>(`/api/replicon/entries/${id}`, {
        method: 'PUT',
        body: this.toApiPayload(changes),
      })
      const idx = this.entries.findIndex(e => e.id === id)
      if (idx !== -1) this.entries[idx] = updated as TimeEntry
      return updated as TimeEntry
    },

    async remove(id: string) {
      const entry = this.entries.find(e => e.id === id)
      if (!entry) return
      this.deletedEntry = entry
      if (this.undoTimer) clearTimeout(this.undoTimer)
      const api = useApi()
      await api(`/api/replicon/entries/${id}`, { method: 'DELETE' })
      this.entries = this.entries.filter(e => e.id !== id)
      this.undoTimer = setTimeout(() => { this.deletedEntry = null }, 5000)
    },

    async undo() {
      if (!this.deletedEntry) return
      if (this.undoTimer) clearTimeout(this.undoTimer)
      const { id, ...rest } = this.deletedEntry
      await this.create(rest as Omit<TimeEntry, 'id'>)
      this.deletedEntry = null
    },

    async duplicate(id: string) {
      const entry = this.entries.find(e => e.id === id)
      if (!entry) return
      const { id: _, ...rest } = entry
      return this.create({ ...rest, start: '', finish: '', duration: '0:00', durationMinutes: 0 } as Omit<TimeEntry, 'id'>)
    },

    toApiPayload(entry: Partial<TimeEntry>): Record<string, unknown> {
      return {
        date:            entry.date,
        project:         entry.project ?? '',
        subProject:      entry.subProject ?? '',
        description:     entry.description ?? '',
        subDescription:  entry.subDescription ?? '',
        furtherInfo:     entry.furtherInfo ?? '',
        start:           entry.start || null,
        finish:          entry.finish || null,
        durationMinutes: entry.durationMinutes ?? 0,
        logged:          entry.logged ?? false,
      }
    },

    async loadCredentials() {
      const api = useApi()
      this.credentials = await api<RepliconCredentials>('/api/replicon/credentials') as RepliconCredentials
      this.credsOk = !!this.credentials?.configured && !!this.credentials?.cookie_set
    },

    async saveCredentials(creds: { base_url: string; session_id: string; server_view_state_id: string; cookie_header: string }) {
      const api = useApi()
      this.credentials = await api<RepliconCredentials>('/api/replicon/credentials', {
        method: 'PUT',
        body: creds,
      }) as RepliconCredentials
      this.credsOk = !!this.credentials?.configured && !!this.credentials?.cookie_set
    },

    async deleteCredentials() {
      const api = useApi()
      await api('/api/replicon/credentials', { method: 'DELETE' })
      this.credentials = null
      this.credsOk = false
    },

    async loadProjects() {
      const api = useApi()
      const data = await api<{ projects: RepliconProject[] }>('/api/replicon/projects') as any
      this.projects = data.projects ?? []
    },

    async loadRowMap() {
      const api = useApi()
      this.rowMap = await api<Record<string, number>>('/api/replicon/row-map') as Record<string, number>
    },

    async saveRowMap(map: Record<string, number>) {
      const api = useApi()
      this.rowMap = await api<Record<string, number>>('/api/replicon/row-map', {
        method: 'PUT',
        body: { map },
      }) as Record<string, number>
    },

    async sync() {
      const api = useApi()
      this.syncing = true
      this.lastSyncStatus = 'Syncing…'
      try {
        const result = await api<{ message: string; projects: RepliconProject[] }>('/api/replicon/sync', { method: 'POST' }) as any
        this.projects = result.projects ?? []
        this.lastSyncStatus = result.message ?? 'Sync complete'
      } catch (e: any) {
        this.lastSyncStatus = e?.data?.message ?? 'Sync failed'
      } finally {
        this.syncing = false
      }
    },

    async submit(rows: any[], date: string) {
      const api = useApi()
      const result = await api<{ results: any[] }>('/api/replicon/submit', {
        method: 'POST',
        body: { rows, date },
      }) as any
      this.submitResults = result.results ?? []
      return this.submitResults
    },
  },
})
