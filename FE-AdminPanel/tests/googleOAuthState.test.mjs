import { test } from 'node:test'
import assert from 'node:assert/strict'
import { readFile } from 'node:fs/promises'
import { webcrypto } from 'node:crypto'
import { runInNewContext } from 'node:vm'

const source = await readFile(new URL('../src/utils/googleOAuthState.js', import.meta.url), 'utf8')
const { beginGoogleOAuth, consumeGoogleOAuth } = await import(`data:text/javascript;base64,${Buffer.from(source).toString('base64')}`)
const values = new Map()
globalThis.window = {
  crypto: webcrypto,
  sessionStorage: {
    getItem: key => values.get(key) ?? null,
    setItem: (key, value) => values.set(key, value),
    removeItem: key => values.delete(key),
  },
}

test('state is unpredictable, preserves portal and can only be used once', () => {
  const first = beginGoogleOAuth('customer')
  assert.match(first, /^[a-f0-9]{64}$/)
  assert.equal(consumeGoogleOAuth(first), 'customer')
  assert.throws(() => consumeGoogleOAuth(first))
  assert.notEqual(beginGoogleOAuth('customer'), first)
})

test('rejects missing, forged and old portal-only state', () => {
  for (const invalid of [null, '', 'a'.repeat(64), '{"portal":"all"}']) {
    beginGoogleOAuth('internal')
    assert.throws(() => consumeGoogleOAuth(invalid))
  }
})

test('rejects expired and corrupted sessions', () => {
  const state = beginGoogleOAuth('all')
  const request = JSON.parse(values.get('googleOAuthRequest'))
  request.createdAt -= 11 * 60 * 1000
  values.set('googleOAuthRequest', JSON.stringify(request))
  assert.throws(() => consumeGoogleOAuth(state))
  values.set('googleOAuthRequest', 'broken json')
  assert.throws(() => consumeGoogleOAuth(state))
})

test('callback rejects invalid state before HTTP and accepts a valid state only once', async () => {
  const serviceSource = await readFile(new URL('../src/services/AuthService.js', import.meta.url), 'utf8')
  const tokens = new Map()
  const requests = []
  const service = runInNewContext(serviceSource.replace(/^import .*;?\r?\n/gm, '').replace('export default', 'result ='), {
    beginGoogleOAuth,
    consumeGoogleOAuth,
    Keys: { VUE_APP_AUTH_PROVIDER: 'password', GOOGLE_CLIENT_ID: 'test-client' },
    window: globalThis.window,
    localStorage: {
      setItem: (key, value) => tokens.set(key, value),
      removeItem: key => tokens.delete(key),
    },
    axios: { post: async (url, payload) => {
      requests.push({ url, payload })
      return { data: { token: '1|test-token', user_data: { role: 1 } } }
    } },
  })
  const state = beginGoogleOAuth('customer')
  assert.equal((await service.handleGoogleCallback('code', state)).success, true)
  assert.equal(requests[0].payload.portal, 'customer')
  assert.equal(tokens.get('customerToken'), '1|test-token')
  assert.equal((await service.handleGoogleCallback('code', state)).success, false)
  assert.equal((await service.handleGoogleCallback('code', '{"portal":"all"}')).success, false)
  assert.equal(requests.length, 1)
})
