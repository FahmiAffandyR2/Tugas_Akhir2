// Local browser smoke test. All API and external requests are intercepted with fixtures.
// Run after building to ../.booking-check-build: node tests/bookingBrowser.cjs
const http = require('node:http')
const fs = require('node:fs')
const path = require('node:path')
const { spawn } = require('node:child_process')
const assert = require('node:assert/strict')
const root = path.resolve(__dirname, '../..')
const build = path.join(root, '.booking-check-build')
const output = path.join(root, '.booking-check-images')
const profile = path.join(root, `.booking-browser-profile-${process.pid}`)
const sleep = ms => new Promise(resolve => setTimeout(resolve, ms))
const types = [
  { id: 1, name: 'Big Bus', slug: 'big', available_buses: 4, capacity: 45 },
  { id: 2, name: 'Medium Bus', slug: 'medium', available_buses: 2, capacity: 30 },
  { id: 3, name: 'Luxury Bus', slug: 'luxury', available_buses: 1, capacity: 25 },
].map(item => ({ ...item, base_price: 2000000, estimated_price: 2500000, is_available: true, is_active: true }))
const paidBooking = { id: 1, reference_code: 'REQ-DEMO', origin: 'Jakarta', destination: 'Bandung', departure_date: '2030-10-10', quoted_price: 2500000, payment_status: 'paid', status: 'approved', requested_bus_count: 1, price_breakdown: { base_price: 2000000 }, bus_type: { name: 'Large Bus' } }
let browser, socket, server
;(async () => {
  fs.mkdirSync(output, { recursive: true })
  server = http.createServer((req, res) => {
    const pathname = decodeURIComponent(new URL(req.url, 'http://localhost').pathname)
    let filename = path.resolve(build, '.' + pathname)
    if (!filename.startsWith(build + path.sep)) filename = path.join(build, 'index.html')
    if (!fs.existsSync(filename) || !fs.statSync(filename).isFile()) filename = path.join(build, 'index.html')
    const mime = { '.js': 'application/javascript', '.css': 'text/css', '.html': 'text/html', '.png': 'image/png', '.svg': 'image/svg+xml', '.woff2': 'font/woff2' }
    res.setHeader('Content-Type', mime[path.extname(filename)] || 'application/octet-stream')
    fs.createReadStream(filename).pipe(res)
  })
  await new Promise(resolve => server.listen(0, '127.0.0.1', resolve))
  const origin = `http://127.0.0.1:${server.address().port}`
  browser = spawn('C:/Program Files/Google/Chrome/Application/chrome.exe', ['--headless=new', '--disable-gpu', '--no-first-run', '--disable-background-networking', '--disable-extensions', '--remote-debugging-port=0', `--user-data-dir=${profile}`, 'about:blank'], { windowsHide: true, stdio: 'ignore' })
  let port
  for (let i = 0; i < 100; i++) {
    try { port = fs.readFileSync(path.join(profile, 'DevToolsActivePort'), 'utf8').split('\n')[0]; break } catch (_) { await sleep(100) }
  }
  if (!port) throw new Error('Chrome debugging port unavailable')
  console.log('Chrome headless started')
  const target = await (await fetch(`http://127.0.0.1:${port}/json/new?about:blank`, { method: 'PUT' })).json()
  socket = new WebSocket(target.webSocketDebuggerUrl)
  await new Promise((resolve, reject) => {
    const timer = setTimeout(() => reject(new Error('Debugger connection timed out')), 10000)
    socket.addEventListener('open', () => { clearTimeout(timer); resolve() }, { once: true })
  })
  let counter = 0
  const pending = new Map(), runtimeErrors = []
  const call = (method, params = {}) => new Promise((resolve, reject) => {
    const id = ++counter
    const timer = setTimeout(() => { pending.delete(id); reject(new Error('Debugger timed out: ' + method)) }, 15000)
    pending.set(id, { resolve: result => { clearTimeout(timer); resolve(result) }, reject: error => { clearTimeout(timer); reject(error) } })
    socket.send(JSON.stringify({ id, method, params }))
  })
  socket.addEventListener('message', async event => {
    const message = JSON.parse(event.data)
    if (message.id) {
      const item = pending.get(message.id)
      pending.delete(message.id)
      if (item) message.error ? item.reject(new Error(JSON.stringify(message.error))) : item.resolve(message.result)
    }
    if (message.method === 'Runtime.exceptionThrown') runtimeErrors.push(message.params.exceptionDetails.text)
    if (message.method === 'Fetch.requestPaused') {
      const { requestId, request } = message.params
      try {
        if (request.url.startsWith(origin)) return await call('Fetch.continueRequest', { requestId })
        if (request.url.includes('/api/')) {
          let data = {}
          if (request.url.includes('options')) data = { bus_types: types, service_areas: [{ id: 1, name: 'Jakarta' }, { id: 2, name: 'Bandung' }] }
          else if (request.url.includes('/mine')) data = { bookings: [paidBooking] }
          else if (request.method === 'POST' && request.url.endsWith('/charter-bookings')) {
            const payload = JSON.parse(request.postData)
            assert.equal(payload.requested_bus_count, 1)
            assert.equal(payload.return_date, payload.departure_date)
            assert.equal(payload.passenger_count, undefined)
            data = { message: 'Booking tersimpan', booking: paidBooking }
          } else data = { data: { name: 'Customer Demo', role: 1 }, user_data: { role: 1 }, bookings: [] }
          await call('Fetch.fulfillRequest', { requestId, responseCode: 200, responseHeaders: [{ name: 'Content-Type', value: 'application/json' }, { name: 'Access-Control-Allow-Origin', value: origin }, { name: 'Access-Control-Allow-Headers', value: 'Authorization, Content-Type, X-Requested-With' }, { name: 'Access-Control-Allow-Methods', value: 'GET, POST, OPTIONS' }], body: Buffer.from(JSON.stringify(data)).toString('base64') })
        } else await call('Fetch.failRequest', { requestId, errorReason: 'BlockedByClient' })
      } catch (error) { runtimeErrors.push(error.message) }
    }
  })
  await call('Page.enable')
  console.log('Debugger connected')
  await call('Runtime.enable')
  await call('Network.setBypassServiceWorker', { bypass: true })
  await call('Fetch.enable', { patterns: [{ urlPattern: '*' }] })
  await call('Page.addScriptToEvaluateOnNewDocument', { source: `localStorage.setItem('ezbusPwaInstallDismissedAt',String(Date.now())); navigator.serviceWorker.register=async()=>({addEventListener(){}}); window.L={map:()=>({on(){},remove(){},invalidateSize(){},setView(){}}),tileLayer:()=>({addTo(){}})}; window.findVm=(test)=>{const scan=v=>test(v)?v:v.$children.map(scan).find(Boolean);return scan(window.vm)};` })
  const evaluate = async expression => {
    const result = await call('Runtime.evaluate', { expression, returnByValue: true, awaitPromise: true })
    if (result.exceptionDetails) throw new Error(result.exceptionDetails.exception?.description || result.exceptionDetails.text)
    return result.result.value
  }
  const waitFor = async expression => { for (let i = 0; i < 100; i++) { try { if (await evaluate(expression)) return } catch (_) {} await sleep(100) } throw new Error('Timed out: ' + expression) }
  const screenshot = async filename => { await sleep(700); const image = await call('Page.captureScreenshot', { format: 'png' }); fs.writeFileSync(path.join(output, filename), Buffer.from(image.data, 'base64')) }
  await call('Emulation.setDeviceMetricsOverride', { width: 1440, height: 1000, deviceScaleFactor: 1, mobile: false })
  await call('Page.navigate', { url: origin })
  console.log('Local page loaded')
  await waitFor('window.vm && window.findVm(v=>v.continueBooking)?.items.length===3')
  assert.equal(await evaluate(`document.querySelector('#booking-start').getBoundingClientRect().left > document.querySelector('.hero-text').getBoundingClientRect().left`), true)
  assert.equal(await evaluate(`Array.from(document.querySelectorAll('h3')).find(e=>e.textContent==='Data Pulang').offsetParent===null`), true)
  await screenshot('landing-desktop.png')
  console.log('Landing layout passed')
  await evaluate(`Object.assign(window.findVm(v=>v.continueBooking).form, ${JSON.stringify({ tripStyle: 'day_trip', busTypeId: 1, departureDate: '2030-10-10', departureTime: '08:00', origin: 'Jakarta', destination: 'Bandung', returnTime: '19:00' })}); true`)
  await waitFor('!window.findVm(v=>v.continueBooking).loading')
  await waitFor(`Array.from(document.querySelectorAll('h3')).find(e=>e.textContent==='Data Pulang').offsetParent!==null`)
  await evaluate(`window.findVm(v=>v.continueBooking).continueBooking()`)
  await waitFor(`location.pathname==='/login'`)
  assert.ok(await evaluate(`sessionStorage.getItem('busBookingDraft')`))
  await evaluate(`localStorage.setItem('customerToken','1|test');localStorage.setItem('customerRole','1');window.vm.$router.push('/customer/pesan'); true`)
  await waitFor(`window.findVm(v=>v.optionsRequestId!==undefined)?.busItems.length===3`)
  assert.equal(await evaluate(`window.findVm(v=>v.optionsRequestId!==undefined).form.origin`), 'Jakarta')
  await evaluate(`Object.assign(window.findVm(v=>v.optionsRequestId!==undefined).form,{originAreaId:1,destinationAreaId:2}); true`)
  await waitFor(`!window.findVm(v=>v.optionsRequestId!==undefined).loadingOptions`)
  await evaluate(`window.findVm(v=>v.optionsRequestId!==undefined).next()`)
  await waitFor(`window.findVm(v=>v.optionsRequestId!==undefined).step===2`)
  assert.equal(await evaluate(`document.querySelectorAll('.v-stepper__step').length`), 2)
  await screenshot('confirmation-desktop.png')
  await call('Emulation.setDeviceMetricsOverride', { width: 390, height: 844, deviceScaleFactor: 1, mobile: true })
  await sleep(400)
  assert.equal(await evaluate(`document.documentElement.scrollWidth <= window.innerWidth`), true)
  await screenshot('confirmation-mobile.png')
  await evaluate(`window.findVm(v=>v.optionsRequestId!==undefined).next()`)
  await waitFor(`location.pathname==='/customer/pemesanan'`)
  await waitFor(`document.body.innerText.includes('Total Pembayaran')`)
  assert.equal(await evaluate(`document.body.innerText.includes('Estimasi Harga')`), false)
  assert.equal(await evaluate(`document.body.innerText.includes('Harga Dasar Bus')`), true)
  assert.equal(await evaluate(`sessionStorage.getItem('busBookingDraft')`), null)
  await screenshot('paid-booking-mobile.png')
  await evaluate(`window.vm.$router.push('/customer/beranda'); true`)
  await waitFor(`window.findVm(v=>v.continueBooking)?.items.length===3`)
  assert.equal(await evaluate(`document.documentElement.scrollWidth <= window.innerWidth`), true)
  await screenshot('home-mobile.png')
  await call('Emulation.setDeviceMetricsOverride', { width: 1440, height: 1000, deviceScaleFactor: 1, mobile: false })
  await sleep(400)
  assert.equal(await evaluate(`document.querySelector('#booking-start').getBoundingClientRect().left > document.querySelector('.hero-copy').getBoundingClientRect().left`), true)
  await screenshot('home-desktop.png')
  await call('Emulation.setDeviceMetricsOverride', { width: 390, height: 844, deviceScaleFactor: 1, mobile: true })
  await evaluate(`window.vm.$router.push('/'); true`)
  await waitFor(`document.querySelector('.landing-page') && window.findVm(v=>v.continueBooking)?.items.length===3`)
  assert.equal(await evaluate(`document.documentElement.scrollWidth <= window.innerWidth`), true, 'Public landing must fit mobile width')
  await screenshot('landing-mobile.png')
  assert.deepEqual(runtimeErrors, [])
  console.log('PASS: desktop/mobile layout, staged fields, login draft, two-step confirmation, submission, paid pricing. Screenshots:', output)
})().catch(error => { console.error(error); process.exitCode = 1 }).finally(() => {
  if (socket) socket.close()
  if (browser) browser.kill()
  if (server) { server.closeAllConnections(); server.close() }
})
