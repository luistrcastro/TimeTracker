export function useApi() {
  const auth = useAuthStore()
  const router = useRouter()
  const { public: { apiBase } } = useRuntimeConfig()

  return $fetch.create({
    baseURL: apiBase,
    onRequest({ options }) {
      options.headers = {
        Accept: 'application/json',
        ...(auth.token ? { Authorization: `Bearer ${auth.token}` } : {}),
        ...(options.headers as Record<string, string> ?? {}),
      }
    },
    async onResponseError({ response }) {
      if (response.status === 401) {
        auth.token = null
        auth.user = null
        await router.push('/login')
      }
    },
  })
}
