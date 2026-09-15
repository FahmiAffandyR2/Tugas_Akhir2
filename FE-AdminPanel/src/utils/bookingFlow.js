export const draftKey = 'busBookingDraft'

export function busOptions(types) {
  return types.map(type => {
    const count = Math.max(0, Number(type.available_buses) || 0)
    return { ...type, text: `${type.name} (${count} unit)`, disabled: count < 1 || type.is_available === false }
  })
}

export function nextDate(date) {
  if (!date) return ''
  const value = new Date(`${date}T12:00:00`)
  value.setDate(value.getDate() + 1)
  return localDate(value)
}

export function localDate(date = new Date()) {
  return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`
}

export function effectiveReturnDate(form) {
  if (form.tripStyle === 'day_trip') return form.departureDate
  if (form.rentalDays === undefined) return form.returnDate
  const days = Number(form.rentalDays)
  if (!form.departureDate || !Number.isInteger(days) || days < 2 || days > 365) return ''
  const date = new Date(`${form.departureDate}T12:00:00`)
  date.setDate(date.getDate() + days - 1)
  return localDate(date)
}

export function validateJourney(form, today = localDate()) {
  if (!form.departureDate || form.departureDate < today || !form.origin.trim() || !form.departureTime) return 'Lengkapi data pergi dan pilih tanggal yang belum lewat.'
  if (!form.destination.trim() || !form.returnTime) return 'Lengkapi data pulang dan tempat tujuan.'
  if (form.tripStyle === 'overnight' && form.rentalDays !== undefined && (!Number.isInteger(Number(form.rentalDays)) || Number(form.rentalDays) < 2 || Number(form.rentalDays) > 365)) return 'Isi lama penggunaan bus antara 2 dan 365 hari.'
  if (form.tripStyle === 'overnight' && (!effectiveReturnDate(form) || effectiveReturnDate(form) <= form.departureDate)) return 'Tanggal pulang Menginap harus setelah tanggal berangkat.'
  if (form.tripStyle === 'day_trip' && form.returnTime <= form.departureTime) return 'Jam pulang harus setelah jam penjemputan.'
  if (!form.busTypeId) return 'Pilih bus terlebih dahulu.'
  return ''
}
