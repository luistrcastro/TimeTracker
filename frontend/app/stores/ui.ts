export const useUiStore = defineStore('ui', {
  state: () => ({
    theme: 'light' as 'light' | 'dark',
    use12h: false,
    currentDate: new Date().toISOString().slice(0, 10),
    sortCol: null as 'project' | 'subProject' | 'start' | null,
    sortDir: null as 'asc' | 'desc' | null,
    jiraPattern: 'PROJ-\\d+',
    activeVariant: 'replicon' as 'replicon' | 'contractor',
  }),

  actions: {
    toggleTheme() {
      this.theme = this.theme === 'light' ? 'dark' : 'light'
    },
    toggleTimeFormat() {
      this.use12h = !this.use12h
    },
    setDate(date: string) {
      this.currentDate = date
      this.sortCol = null
      this.sortDir = null
    },
    cycleSort(col: 'project' | 'subProject' | 'start') {
      if (this.sortCol !== col) {
        this.sortCol = col; this.sortDir = 'asc'
      } else if (this.sortDir === 'asc') {
        this.sortDir = 'desc'
      } else {
        this.sortCol = null; this.sortDir = null
      }
    },
  },

  persist: {
    storage: 'localStorage',
    pick: ['theme', 'use12h', 'jiraPattern', 'activeVariant'],
    key: 'tt_ui',
  },
})
