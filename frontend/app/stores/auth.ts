import type { User } from '~/types'

export const useAuthStore = defineStore('auth', {
  state: () => ({
    token: null as string | null,
    user: null as User | null,
  }),

  getters: {
    isLoggedIn: (state) => !!state.token,
    isVerified: (state) => !!state.user?.email_verified_at,
  },

  actions: {
    async login(email: string, password: string) {
      const { public: { apiBase } } = useRuntimeConfig()
      const data = await $fetch<{ token: string; user: User }>(`${apiBase}/api/auth/login`, {
        method: 'POST',
        body: { email, password },
      })
      this.token = data.token
      this.user = data.user
      return data
    },

    async register(name: string, email: string, password: string, passwordConfirmation: string) {
      const { public: { apiBase } } = useRuntimeConfig()
      const data = await $fetch<{ token: string; user: User }>(`${apiBase}/api/auth/register`, {
        method: 'POST',
        body: { name, email, password, password_confirmation: passwordConfirmation },
      })
      this.token = data.token
      this.user = data.user
      return data
    },

    async logout() {
      const { public: { apiBase } } = useRuntimeConfig()
      try {
        await $fetch(`${apiBase}/api/auth/logout`, {
          method: 'POST',
          headers: this.token ? { Authorization: `Bearer ${this.token}` } : {},
        })
      } finally {
        this.token = null
        this.user = null
      }
    },

    async me() {
      if (!this.token) return
      const { public: { apiBase } } = useRuntimeConfig()
      try {
        this.user = await $fetch<User>(`${apiBase}/api/me`, {
          headers: { Authorization: `Bearer ${this.token}` },
        })
      } catch {
        this.token = null
        this.user = null
      }
    },

    async resendVerification() {
      const { public: { apiBase } } = useRuntimeConfig()
      await $fetch(`${apiBase}/api/auth/resend-verification`, {
        method: 'POST',
        headers: { Authorization: `Bearer ${this.token}` },
      })
    },

    async verifyEmail(params: Record<string, string>) {
      const { public: { apiBase } } = useRuntimeConfig()
      const qs = new URLSearchParams(params).toString()
      await $fetch(`${apiBase}/api/auth/verify-email/${params.id}/${params.hash}?${qs}`, {
        method: 'GET',
      })
      if (this.user) this.user.email_verified_at = new Date().toISOString()
    },

    async forgotPassword(email: string) {
      const { public: { apiBase } } = useRuntimeConfig()
      await $fetch(`${apiBase}/api/auth/forgot-password`, {
        method: 'POST',
        body: { email },
      })
    },

    async resetPassword(token: string, email: string, password: string, passwordConfirmation: string) {
      const { public: { apiBase } } = useRuntimeConfig()
      await $fetch(`${apiBase}/api/auth/reset-password`, {
        method: 'POST',
        body: { token, email, password, password_confirmation: passwordConfirmation },
      })
    },
  },

  persist: {
    storage: 'localStorage',
    pick: ['token', 'user'],
    key: 'tt_auth',
  },
})
