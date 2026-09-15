import { test } from 'node:test'
import assert from 'node:assert/strict'
import { readFile } from 'node:fs/promises'
import { runInNewContext } from 'node:vm'

const source = await readFile(new URL('../src/utils/bookingFlow.js', import.meta.url), 'utf8')
const flow = await import(`data:text/javascript;base64,${Buffer.from(source).toString('base64')}`)
const form = { tripStyle: 'day_trip', departureDate: '2030-10-10', departureTime: '08:00', origin: 'Jakarta', destination: 'Bandung', returnDate: '', returnTime: '19:00', busTypeId: 1 }

test('bus choices use actual database names and IDs without synthetic categories', () => {
  const items = flow.busOptions([{ id: 3, name: 'Big Bus 45 Seat', available_buses: 3 }, { id: 1, name: 'Medium Bus 25 Seat', available_buses: 0 }])
  assert.deepEqual(items.map(item => item.text), ['Big Bus 45 Seat (3 unit)', 'Medium Bus 25 Seat (0 unit)'])
  assert.equal(items[0].id, 3)
  assert.equal(items[0].disabled, false)
  assert.equal(items[1].id, 1)
  assert.equal(items[1].disabled, true)
  assert.deepEqual(flow.busOptions([]), [])
})

test('day trip returns on departure date and validates return time', () => {
  assert.equal(flow.effectiveReturnDate(form), form.departureDate)
  assert.equal(flow.validateJourney(form, '2030-10-01'), '')
  assert.ok(flow.validateJourney({ ...form, returnTime: '07:00' }, '2030-10-01'))
})

test('overnight requires a later date; incomplete departure is rejected', () => {
  assert.ok(flow.validateJourney({ ...form, tripStyle: 'overnight', returnDate: form.departureDate }, '2030-10-01'))
  assert.equal(flow.validateJourney({ ...form, tripStyle: 'overnight', returnDate: '2030-10-11', returnTime: '07:00' }, '2030-10-01'), '')
  assert.ok(flow.validateJourney({ ...form, origin: '' }, '2030-10-01'))
  assert.equal(flow.nextDate('2030-12-31'), '2031-01-01')
})

test('paid booking steps display final pricing rather than an estimate', async () => {
  const view = await readFile(new URL('../src/views/customer/Bookings.vue', import.meta.url), 'utf8')
  const script = view.match(/<script>([\s\S]*?)<\/script>/)[1]
  const component = runInNewContext(script.replace(/^import .*\r?\n/gm, '').replace('export default', 'result ='), {})
  const page = { currency: () => 'Rp 100', showAssignment: () => false }
  const paidSteps = component.methods.bookingSteps.call(page, { quoted_price: 100, payment_status: 'paid' })
  assert.ok(paidSteps.every(step => !step.title.toLowerCase().includes('estimasi')))
})

test('map address lookup preserves newer locations and keeps coordinates on failure', async () => {
  const view = await readFile(new URL('../src/views/customer/Booking.vue', import.meta.url), 'utf8')
  const script = view.match(/<script>([\s\S]*?)<\/script>/)[1]
  let finish
  const axios = { get: () => new Promise(resolve => { finish = resolve }) }
  const component = runInNewContext(script.replace(/^import .*\r?\n/gm, '').replace('export default', 'result ='), { axios, BookingJourneyFields: {}, BookingDestinations: {} })
  const page = { form: { pickupLat: -6.2, pickupLng: 106.8, origin: '-6.20000, 106.80000' } }
  const lookup = component.methods.reverseGeocodeLocation.call(page, -6.2, 106.8, 'pickup')
  page.form.origin = 'Alamat baru'
  finish({ data: { display_name: 'Alamat lama' } })
  await lookup
  assert.equal(page.form.origin, 'Alamat baru')
  axios.get = async () => { throw new Error('offline') }
  await component.methods.reverseGeocodeLocation.call(page, -6.2, 106.8, 'pickup')
  assert.equal(page.form.pickupLat, -6.2)
  assert.equal(page.form.origin, 'Alamat baru')
})

test('destination order controls numbering and preserves addresses and coordinates', async () => {
  const view = await readFile(new URL('../src/components/BookingDestinations.vue', import.meta.url), 'utf8')
  const script = view.match(/<script>([\s\S]*?)<\/script>/)[1]
  const pins = []
  const L = { divIcon: o => o, marker: (position, options) => ({ addTo() { pins.push({ position, options }); return { remove() {} } } }) }
  const component = runInNewContext(script.replace(/^import .*\r?\n/gm, '').replace('export default', 'result ='), { window: { L } })
  const page = { ...component.data(), pickup: { lat: null, lng: null }, map: { fitBounds() {} }, $emit: (_, value) => { page.emitted = value } }
  for (const [key, method] of Object.entries(component.methods)) page[key] = method.bind(page)
  page.stops = [{ key: 1, address: 'A', lat: -6, lng: 106 }, { key: 2, address: 'B', lat: -7, lng: 107 }]
  page.activeKey = 1
  page.move(1, -1)
  assert.equal(page.emitted[0].address, 'B')
  assert.equal(page.emitted[0].lat, -7)
  assert.equal(pins[0].options.title, 'Tujuan 1')
  assert.equal(pins[0].position[0], -7)
  page.remove(1)
  assert.equal(page.activeKey, 2)
  assert.equal(page.emitted.length, 1)
  page.remove(0)
  assert.equal(page.stops.length, 1)
})

test('final bus unit price follows the accepted total and booked quantity', async () => {
  const view = await readFile(new URL('../src/views/customer/Bookings.vue', import.meta.url), 'utf8')
  const script = view.match(/<script>([\s\S]*?)<\/script>/)[1]
  const component = runInNewContext(script.replace('export default', 'result ='), {})
  const booking = { quoted_price: '13920480', requested_bus_count: 3, unit_price: '3500000', payment_status: 'paid' }
  assert.equal(component.methods.bookingUnitPrice(booking), 4640160)
  assert.equal(component.methods.bookingUnitPrice({ quoted_price: '5000000' }), 5000000)
  const steps = component.methods.bookingSteps.call({ showAssignment: () => false }, booking)
  assert.equal(steps.find(step => step.key === 'quote').text, '')
})

test('overnight duration determines inclusive return dates across month and year boundaries', () => {
  assert.equal(flow.effectiveReturnDate({ ...form, tripStyle: 'overnight', departureDate: '2030-12-31', rentalDays: 3 }), '2031-01-02')
  assert.equal(flow.validateJourney({ ...form, tripStyle: 'overnight', rentalDays: 3 }, '2030-10-01'), '')
  for (const rentalDays of [null, 1, 2.5, 366]) {
    assert.ok(flow.validateJourney({ ...form, tripStyle: 'overnight', rentalDays }, '2030-10-01'))
  }
  assert.equal(flow.effectiveReturnDate({ ...form, rentalDays: 3 }), form.departureDate)
})

test('cancelled bookings stop approval and payment progress regardless of old payment status', async () => {
  const view = await readFile(new URL('../src/views/customer/Bookings.vue', import.meta.url), 'utf8')
  const script = view.match(/<script>([\s\S]*?)<\/script>/)[1]
  const component = runInNewContext(script.replace('export default', 'result ='), {})
  for (const payment_status of ['unpaid', 'pending_verification', 'rejected', 'paid']) {
    const booking = { status: 'cancelled', payment_status, quoted_price: 3850000, payment_bank_name: 'Bank' }
    const steps = component.methods.bookingSteps(booking)
    assert.equal(steps.length, 1)
    assert.equal(steps[0].title, 'Pemesanan dibatalkan')
    assert.equal(steps[0].state, 'closed')
    assert.equal(component.methods.canPay(booking), false)
    assert.equal(component.methods.canCancelBeforePayment(booking), false)
    assert.equal(component.methods.isClosedBooking(booking), true)
  }
  assert.equal(component.methods.bookingSteps({ status: 'quote_sent', payment_status: 'unpaid', quoted_price: 100 }).length, 3)
})
