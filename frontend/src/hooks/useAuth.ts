import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api, ApiError, type User } from '../api/client'

const ME_KEY = ['me'] as const

/** Current user; `null` when signed out (401), `undefined` while loading. */
export function useAuth() {
  const queryClient = useQueryClient()

  const me = useQuery({
    queryKey: ME_KEY,
    queryFn: async (): Promise<User | null> => {
      try {
        return (await api.me()).user
      } catch (e) {
        if (e instanceof ApiError && e.status === 401) return null
        throw e
      }
    },
    staleTime: 5 * 60_000,
    retry: false,
  })

  const setUser = (user: User | null) => queryClient.setQueryData(ME_KEY, user)

  const login = useMutation({
    mutationFn: (v: { email: string; password: string }) => api.login(v.email, v.password),
    onSuccess: ({ user }) => setUser(user),
  })

  const register = useMutation({
    mutationFn: (v: { email: string; password: string; displayName?: string }) =>
      api.register(v.email, v.password, v.displayName),
    onSuccess: ({ user }) => setUser(user),
  })

  const logout = useMutation({
    mutationFn: () => api.logout(),
    onSuccess: () => {
      setUser(null)
      // Order data belongs to the previous user.
      queryClient.removeQueries({ queryKey: ['orders'] })
    },
  })

  return {
    user: me.data,
    isLoading: me.isPending,
    login,
    register,
    logout,
  }
}
