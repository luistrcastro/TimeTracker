import type { UserCustomization } from '~/types'

type DeepPartial<T> = { [K in keyof T]?: T[K] extends object ? DeepPartial<T[K]> : T[K] }

export function useUserCustomization() {
  const api = useApi()

  const load = () => api<UserCustomization>('/api/user/customization')

  const save = (payload: DeepPartial<UserCustomization>) =>
    api<UserCustomization>('/api/user/customization', { method: 'PUT', body: payload })

  return { load, save }
}
