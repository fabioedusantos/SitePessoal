import { useAuth } from './auth'

const API_BASE = import.meta.env.VITE_API_BASE || 'http://localhost:8080/api/v1'

export class ApiError extends Error {
  constructor(
    message: string,
    public readonly status: number,
    public readonly fieldErrors: Record<string, string> = {}
  ) {
    super(message)
    this.name = 'ApiError'
  }
}

function fallbackMessage(status: number) {
  if (status === 400) return 'Os dados enviados não puderam ser processados. Revise o formulário.'
  if (status === 403) return 'Você não tem permissão para executar esta ação.'
  if (status === 404) return 'O conteúdo solicitado não foi encontrado.'
  if (status === 409) return 'Já existe um registro com essas informações. Revise os campos e tente novamente.'
  if (status === 413) return 'O arquivo enviado é maior que o limite permitido.'
  if (status === 422) return 'Há informações inválidas ou obrigatórias não preenchidas. Revise o formulário.'
  if (status === 429) return 'Muitas tentativas em pouco tempo. Aguarde um momento e tente novamente.'
  if (status >= 500) return 'O servidor não conseguiu concluir esta operação. Tente novamente.'
  return 'Não foi possível concluir a operação.'
}

export async function api<T>(path: string, options: RequestInit = {}): Promise<T> {
  const { csrfToken, clearSession } = useAuth()
  const headers = new Headers(options.headers)
  const method = (options.method || 'GET').toUpperCase()
  const isFormData = typeof FormData !== 'undefined' && options.body instanceof FormData

  if (!headers.has('Content-Type') && options.body && !isFormData) {
    headers.set('Content-Type', 'application/json')
  }

  if (!['GET', 'HEAD', 'OPTIONS'].includes(method) && csrfToken.value) {
    headers.set('X-CSRF-Token', csrfToken.value)
  }

  let response: Response
  try {
    response = await fetch(`${API_BASE}${path}`, {
      ...options,
      headers,
      credentials: 'include'
    })
  } catch {
    throw new ApiError('Não foi possível conectar ao servidor. Verifique sua conexão e tente novamente.', 0)
  }

  const contentType = response.headers.get('content-type') || ''
  let data: any = {}
  if (contentType.includes('application/json')) {
    data = await response.json().catch(() => ({}))
  } else {
    await response.text().catch(() => '')
  }

  if (response.status === 401 && !path.includes('/auth/login')) {
    clearSession()
    window.location.href = '/login'
    throw new ApiError('Sua sessão expirou. Entre novamente.', 401)
  }

  if (!response.ok) {
    const fieldErrors = data && typeof data.errors === 'object' && data.errors
      ? data.errors as Record<string, string>
      : {}
    throw new ApiError(String(data.message || fallbackMessage(response.status)), response.status, fieldErrors)
  }

  return data as T
}
