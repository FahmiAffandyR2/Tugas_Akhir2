const storageKey = 'googleOAuthRequest'
const maxAge = 10 * 60 * 1000
const portals = ['all', 'internal', 'customer']

export function beginGoogleOAuth(portal) {
  if (!portals.includes(portal)) throw new Error('Portal login tidak valid.')
  const bytes = new Uint8Array(32)
  window.crypto.getRandomValues(bytes)
  const state = Array.from(bytes, value => value.toString(16).padStart(2, '0')).join('')
  window.sessionStorage.setItem(storageKey, JSON.stringify({ state, portal, createdAt: Date.now() }))
  return state
}

export function consumeGoogleOAuth(state) {
  const stored = window.sessionStorage.getItem(storageKey)
  window.sessionStorage.removeItem(storageKey)
  let request
  try {
    request = JSON.parse(stored)
  } catch (_) {
    request = null
  }
  const age = request ? Date.now() - request.createdAt : NaN
  if (!request || typeof state !== 'string' || !/^[a-f0-9]{64}$/.test(state)
    || state !== request.state || !portals.includes(request.portal)
    || !Number.isFinite(age) || age < 0 || age > maxAge) {
    throw new Error('Sesi login Google tidak valid atau kedaluwarsa. Silakan login kembali.')
  }
  return request.portal
}
