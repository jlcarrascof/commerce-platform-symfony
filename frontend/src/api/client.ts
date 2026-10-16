import axios, { type AxiosError, type InternalAxiosRequestConfig } from 'axios'

export const AUTH_TOKEN_STORAGE_KEY = 'auth_token'
export const REFRESH_TOKEN_STORAGE_KEY = 'refresh_token'

const apiClient = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL ?? 'http://localhost:8080/api',
})

apiClient.interceptors.request.use((config) => {
  const token = localStorage.getItem(AUTH_TOKEN_STORAGE_KEY)
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }

  return config
})

type RetryableConfig = InternalAxiosRequestConfig & { _retry?: boolean }

// Shared across concurrent 401s so a burst of requests triggers a single
// refresh call instead of one per request.
let refreshPromise: Promise<string> | null = null

async function refreshAccessToken(): Promise<string> {
  const refreshToken = localStorage.getItem(REFRESH_TOKEN_STORAGE_KEY)
  if (!refreshToken) {
    throw new Error('No refresh token available.')
  }

  const response = await axios.post<{ token: string; refresh_token: string }>(
    `${apiClient.defaults.baseURL}/token/refresh`,
    { refresh_token: refreshToken },
  )

  localStorage.setItem(AUTH_TOKEN_STORAGE_KEY, response.data.token)
  localStorage.setItem(REFRESH_TOKEN_STORAGE_KEY, response.data.refresh_token)

  return response.data.token
}

apiClient.interceptors.response.use(
  (response) => response,
  async (error: AxiosError) => {
    const originalRequest = error.config as RetryableConfig | undefined
    const isAuthEndpoint = originalRequest?.url?.includes('/login') || originalRequest?.url?.includes('/token/refresh')

    if (error.response?.status !== 401 || !originalRequest || originalRequest._retry || isAuthEndpoint) {
      return Promise.reject(error)
    }

    originalRequest._retry = true

    try {
      refreshPromise ??= refreshAccessToken().finally(() => {
        refreshPromise = null
      })
      const token = await refreshPromise
      originalRequest.headers.Authorization = `Bearer ${token}`
      return apiClient(originalRequest)
    } catch {
      const { useAuthStore } = await import('../stores/auth')
      useAuthStore().logout()
      window.location.href = '/login?sessionExpired=1'
      return Promise.reject(error)
    }
  },
)

export default apiClient
