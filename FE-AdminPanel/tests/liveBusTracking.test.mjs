import { test } from 'node:test'
import assert from 'node:assert/strict'
import { readFile } from 'node:fs/promises'
const source = await readFile(new URL('../src/utils/liveBusTracking.js', import.meta.url), 'utf8')
const { runningBuses, busMarkers } = await import(`data:text/javascript;base64,${Buffer.from(source).toString('base64')}`)
const trip = { id: 1, started_at: '2030-01-01T08:00:00Z', updated_at: '2030-01-01T08:05:00Z', last_position_lat: '-6.2', last_position_lng: '106.8', bus: { id: 1, license: 'B 123' } }
test('only running buses appear, without duplicate buses or finished trips', () => {
 const buses = runningBuses([trip, {...trip,id:2}, {...trip,ended_at:'2030-01-01T09:00:00Z',bus:{id:2}}, {...trip,started_at:null,bus:{id:3}}])
 assert.equal(buses.length,1)
 assert.equal(buses[0].id,1)
})
test('GPS uses newer device coordinates and rejects coordinates from before departure', () => {
 const bus = {...trip.bus,current_lat:-7,current_lng:110,last_gps_at:'2030-01-01T08:10:00Z'}
 assert.equal(runningBuses([{...trip,bus}])[0].position.lat,-7)
 bus.last_gps_at='2030-01-01T07:00:00Z'
 assert.equal(runningBuses([{...trip,bus}])[0].position.lat,-6.2)
})
test('missing GPS is not plotted and popup content is escaped', () => {
 const buses=runningBuses([{...trip,last_position_lat:null,last_position_lng:null}])
 assert.equal(buses.length,1)
 assert.equal(busMarkers(buses).length,0)
 const markers=busMarkers(runningBuses([{...trip,driver:{name:'<img src=x onerror=alert(1)>'}}]),Date.parse('2030-01-01T09:00:00Z'))
 assert.ok(markers[0].infoText.includes('&lt;img'))
 assert.ok(markers[0].infoText.includes('Lokasi terakhir'))
})
