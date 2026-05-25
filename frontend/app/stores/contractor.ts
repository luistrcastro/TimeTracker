import type { Client, CompanySetting, Invoice, TimeEntry, UserCustomization } from '~/types'

export const useContractorStore = defineStore('contractor', {
  state: () => ({
    entries: [] as TimeEntry[],
    jiraPattern: 'PROJ-\\d+',
    clients: [] as Client[],
    invoices: [] as Invoice[],
    company: null as CompanySetting | null,
    deletedEntry: null as TimeEntry | null,
    undoTimer: null as ReturnType<typeof setTimeout> | null,
  }),

  getters: {
    entriesForDate: (state) => (date: string) =>
      state.entries.filter(e => e.date === date),

    clientNames: (state) => state.clients.map(c => c.name),
  },

  actions: {
    async loadEntries(date?: string) {
      const api = useApi()
      const params = date ? { date } : {}
      const data = await api<TimeEntry[]>('/api/contractor/entries', { params })
      if (date) {
        // Merge: replace entries for this date, keep others
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
      const created = await api<TimeEntry>('/api/contractor/entries', {
        method: 'POST',
        body: this.toApiPayload(entry),
      })
      this.entries.push(created as TimeEntry)
      return created as TimeEntry
    },

    async update(id: string, changes: Partial<TimeEntry>) {
      const api = useApi()
      const updated = await api<TimeEntry>(`/api/contractor/entries/${id}`, {
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
      await api(`/api/contractor/entries/${id}`, { method: 'DELETE' })
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
        clientId:        entry.clientId ?? null,
        clientTaskId:    entry.clientTaskId ?? null,
        task:            entry.task ?? '',
        description:     entry.description ?? '',
        subDescription:  entry.subDescription ?? '',
        date:            entry.date,
        start:           entry.start || null,
        finish:          entry.finish || null,
        durationMinutes: entry.durationMinutes ?? 0,
      }
    },

    async createTask(clientId: string, name: string) {
      const api = useApi()
      const task = await api<{ id: string; name: string }>(`/api/contractor/clients/${clientId}/tasks`, {
        method: 'POST',
        body: { name },
      }) as { id: string; name: string }
      const client = this.clients.find(c => c.id === clientId)
      if (client) client.tasks = [...(client.tasks ?? []), task]
      return task
    },

    async loadClients() {
      const api = useApi()
      this.clients = await api<Client[]>('/api/contractor/clients') as Client[]
    },

    async loadInvoices() {
      const api = useApi()
      this.invoices = await api<Invoice[]>('/api/contractor/invoices') as Invoice[]
    },

    async loadCompany() {
      const api = useApi()
      this.company = await api<CompanySetting>('/api/contractor/company') as CompanySetting
    },

    async sendInvoice(id: string) {
      const api = useApi()
      await api(`/api/contractor/invoices/${id}/send`, { method: 'POST' })
      await Promise.all([this.loadInvoices(), this.loadEntries()])
    },

    async revertInvoice(id: string) {
      const api = useApi()
      await api(`/api/contractor/invoices/${id}/revert`, { method: 'POST' })
      await this.loadInvoices()
    },

    async voidInvoice(id: string) {
      const api = useApi()
      await api(`/api/contractor/invoices/${id}/void`, { method: 'POST' })
      await Promise.all([this.loadInvoices(), this.loadEntries()])
    },

    async updateInvoiceStatus(id: string, status: 'approved' | 'paid') {
      const api = useApi()
      await api(`/api/contractor/invoices/${id}`, { method: 'PUT', body: { status } })
      await this.loadInvoices()
    },

    loadCustomization(data: UserCustomization) {
      this.jiraPattern = data.contractor.jiraPattern
    },
    async saveCustomization() {
      const { save } = useUserCustomization()
      await save({ contractor: { jiraPattern: this.jiraPattern } })
    },
  },
})
