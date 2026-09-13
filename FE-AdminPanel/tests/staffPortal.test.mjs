import { test } from 'node:test'
import assert from 'node:assert/strict'
import { readFile } from 'node:fs/promises'
import { runInNewContext } from 'node:vm'

const source = await readFile(new URL('../src/router/index.js', import.meta.url), 'utf8')

function navigate(role, path, meta = {}, authenticated = true) {
  let guard
  const router = runInNewContext(source.replace(/^import .*\r?\n/gm, '').replace('export default router', 'router'), {
    Vue: { use() {} },
    VueRouter: class { beforeEach(callback) { guard = callback } },
    auth: { isUserLoggedIn: () => authenticated },
    process: { env: {} },
    localStorage: { getItem: key => key === 'internalRole' ? String(role) : null },
  })
  let destination
  guard({ path, meta }, {}, value => { destination = value })
  return destination
}

test('staff lands on its dashboard and cannot open admin or driver pages', () => {
  assert.equal(navigate(3, '/dashboard'), '/staff/dashboard')
  assert.equal(navigate(3, '/settings'), '/staff/dashboard')
  assert.equal(navigate(3, '/driver/beranda', { driverOnly: true }), '/staff/dashboard')
  assert.equal(navigate(3, '/staff/dashboard', { staffOnly: true }), undefined)
})

test('staff pages reject other roles and require login', () => {
  assert.equal(navigate(0, '/staff/dashboard', { staffOnly: true }), '/dashboard')
  assert.equal(navigate(2, '/staff/dashboard', { staffOnly: true }), '/driver/beranda')
  assert.equal(navigate(3, '/staff/dashboard', { staffOnly: true }, false), '/login')
  assert.equal(navigate(0, '/dashboard'), undefined)
})

test('Google callback remains reachable without a session', () => {
  assert.equal(navigate(null, '/auth/google/callback', { layout: 'blank' }, false), undefined)
})

test('staff dashboard loads its scoped endpoints and handles failures', async () => {
  const view = await readFile(new URL('../src/views/staff/Dashboard.vue', import.meta.url), 'utf8')
  const script = view.match(/<script>([\s\S]*?)<\/script>/)[1]
  const calls = []
  let fail = false
  const component = runInNewContext(script.replace(/^import .*\r?\n/gm, '').replace('export default', 'result ='), {
    axios: { get: async url => {
      calls.push(url)
      if (fail) throw new Error('Unavailable')
      return { data: { dashboard: { total_bookings: 1 }, bookings: [{ id: 1 }] } }
    } },
    AuthService: { checkError() {} },
  })
  const page = component.data()
  await component.methods.load.call(page)
  assert.deepEqual(calls, ['/staff/dashboard', '/staff/bookings'])
  assert.equal(page.dashboard.total_bookings, 1)
  assert.equal(page.bookings[0].id, 1)
  fail = true
  await component.methods.load.call(page)
  assert.equal(page.loading, false)
  assert.equal(page.bookings.length, 0)
  assert.ok(page.error)
})
