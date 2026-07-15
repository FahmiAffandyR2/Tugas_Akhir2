let googleMapsKey = process.env.VUE_APP_GOOGLE_MAPS_API_KEY
let apiUrl = (process.env.VUE_APP_API_URL || '').replace(/\/+$/, '')
let systemName = process.env.VUE_APP_SYSTEM_NAME
let originLat = process.env.VUE_APP_ORIGIN_LAT
let originLng = process.env.VUE_APP_ORIGIN_LNG
let authProvider = (process.env.VUE_APP_AUTH_PROVIDER || 'password').toLowerCase()
export const Keys = {
    GOOGLE_MAPS_API_KEY: googleMapsKey,
    VUE_APP_API_URL: apiUrl,
    VUE_APP_SYSTEM_NAME: systemName,
    VUE_APP_ORIGIN_LAT: originLat,
    VUE_APP_ORIGIN_LNG: originLng,
    VUE_APP_AUTH_PROVIDER: authProvider
};
