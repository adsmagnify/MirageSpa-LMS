import { reactive } from 'vue'

export const session = reactive({
  token: localStorage.getItem('mirage_token') || '',
  user: null,
  ready: false,
})

export const ui = reactive({ assistant: false, notices: false, account: false })

export const notices = reactive([])

export function toast(message, tone = 'ok') {
  const id = crypto.randomUUID()
  notices.push({ id, message, tone })
  setTimeout(() => {
    const index = notices.findIndex((item) => item.id === id)
    if (index >= 0) notices.splice(index, 1)
  }, 3800)
}

export async function api(path, { method = 'GET', body, form } = {}) {
  const headers = {}
  if (session.token) headers.Authorization = `Bearer ${session.token}`
  if (body && !form) headers['Content-Type'] = 'application/json'
  const response = await fetch(`/api${path}`, {
    method,
    headers,
    body: form ? body : body ? JSON.stringify(body) : undefined,
  })
  const data = await response.json().catch(() => ({}))
  if (response.status === 401 && !path.startsWith('/auth/')) {
    logout()
  }
  if (!response.ok) {
    const error = new Error(data.detail || 'Something went wrong.')
    error.status = response.status
    throw error
  }
  return data
}

export function setSession(token, user) {
  session.token = token
  session.user = user
  localStorage.setItem('mirage_token', token)
}

export async function login(email, password) {
  const data = await api('/auth/login', { method: 'POST', body: { email, password } })
  setSession(data.token, data.user)
  return data.user
}

export async function signup(payload) {
  const data = await api('/auth/signup', { method: 'POST', body: payload })
  setSession(data.token, data.user)
  return data.user
}

export function logout() {
  session.token = ''
  session.user = null
  localStorage.removeItem('mirage_token')
}

export async function hydrate() {
  if (!session.token) {
    session.ready = true
    return
  }
  try {
    session.user = await api('/auth/me')
  } catch {
    logout()
  } finally {
    session.ready = true
  }
}

export function isStaff(user = session.user) {
  return ['admin', 'instructor', 'moderator'].includes(user?.role)
}

export function canTeach(user = session.user) {
  return ['admin', 'instructor'].includes(user?.role)
}

export function isAdmin(user = session.user) {
  return user?.role === 'admin'
}

export function homePath(user = session.user) {
  if (!user) return '/login'
  return '/'
}
