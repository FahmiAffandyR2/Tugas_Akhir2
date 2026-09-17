const escapeHtml = value => String(value || '').replace(/[&<>"']/g, char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[char]))
const position = (lat, lng) => {
  if (lat == null || lng == null || lat === '' || lng === '') return null
  const point = { lat: Number(lat), lng: Number(lng) }
  return Number.isFinite(point.lat) && Number.isFinite(point.lng) && Math.abs(point.lat) <= 90 && Math.abs(point.lng) <= 180 ? point : null
}
export function runningBuses(trips) {
  const buses = new Map()
  trips.filter(trip => trip.started_at && !trip.ended_at && trip.bus).forEach(trip => {
    const bus = trip.bus
    const busPosition = position(bus.current_lat, bus.current_lng)
    const tripPosition = position(trip.last_position_lat, trip.last_position_lng)
    const busTime = Date.parse(bus.last_gps_at) || 0
    const tripTime = Date.parse(trip.last_gps_at || trip.updated_at) || 0
    const useBus = busPosition && busTime >= (Date.parse(trip.started_at) || 0) && (!tripPosition || busTime >= tripTime)
    const point = useBus ? busPosition : tripPosition
    const updatedAt = useBus ? bus.last_gps_at : (trip.last_gps_at || trip.updated_at)
    const id = bus.id || trip.bus_id
    const previous = buses.get(id)
    if (previous && (Date.parse(previous.startedAt) || 0) > (Date.parse(trip.started_at) || 0)) return
    buses.set(id, { id, name: bus.fleet_number || bus.license || `Bus #${id}`, license: bus.license, driver: trip.driver && trip.driver.name, position: point, updatedAt, startedAt: trip.started_at })
  })
  return [...buses.values()]
}
export function busMarkers(buses, now = Date.now()) {
  return buses.filter(bus => bus.position).map(bus => {
    const timestamp = Date.parse(bus.updatedAt)
    const stale = !Number.isFinite(timestamp) || now - timestamp > 5 * 60 * 1000
    const updated = Number.isFinite(timestamp) ? new Date(timestamp).toLocaleString('id-ID') : 'Belum diketahui'
    return {
      place_id: `bus-${bus.id}`,
      position: bus.position,
      icon: 'https://cdn-icons-png.flaticon.com/32/3471/3471521.png',
      infoText: `<b>${escapeHtml(bus.name)}</b><br>${escapeHtml(bus.license)}<br>Driver: ${escapeHtml(bus.driver || '-')}<br>${stale ? 'Lokasi terakhir (belum diperbarui)' : 'GPS aktif'}<br>Diperbarui: ${escapeHtml(updated)}`,
    }
  })
}
