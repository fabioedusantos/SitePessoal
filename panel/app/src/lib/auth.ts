import { ref } from 'vue'

const API_BASE = import.meta.env.VITE_API_BASE || 'http://localhost:8080/api/v1'
const CSRF_KEY = 'fbsantos.cms.csrf'

const authenticated = ref(false)
const checked = ref(false)
const csrfToken = ref(sessionStorage.getItem(CSRF_KEY) || '')

export function useAuth() {
  const setSession = (csrf: string) => {
    authenticated.value = true
    csrfToken.value = csrf
    sessionStorage.setItem(CSRF_KEY, csrf)
  }

  const clearSession = () => {
    authenticated.value = false
    csrfToken.value = ''
    sessionStorage.removeItem(CSRF_KEY)
  }

  const checkSession = async () => {
    if (checked.value && authenticated.value) return true

    try {
      const response = await fetch(`${API_BASE}/admin/me`, {
        credentials: 'include'
      })
      if (!response.ok) {
        clearSession()
        checked.value = true
        return false
      }

      const data = await response.json()
      setSession(data.csrf_token || '')
      checked.value = true
      return true
    } catch {
      clearSession()
      checked.value = true
      return false
    }
  }

  return {
    authenticated,
    checked,
    csrfToken,
    setSession,
    clearSession,
    checkSession
  }
}
