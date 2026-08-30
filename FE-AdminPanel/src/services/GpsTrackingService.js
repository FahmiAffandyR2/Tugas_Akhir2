import GpsQueue from './GpsQueue';

const http = () => window.axios;
const SEND_INTERVAL_MS = 10000;
const MAX_RETRIES = 5;
const RETRY_INTERVAL_MS = 30000;

class GpsTrackingService {
  constructor() {
    this.watchId = null;
    this.tripId = null;
    this.lastSentAt = 0;
    this.positionListeners = [];
    this.statusListeners = [];
    this.isTracking = false;
    this.wakeLock = null;
    this.wakeLockSupported = 'wakeLock' in navigator;
    this.retryTimer = null;
    this.isPaused = false;
    this.currentPosition = null;
    this._onVisibilityChange = this._handleVisibilityChange.bind(this);
    this._onOnline = this._handleOnline.bind(this);
    this._onOffline = this._handleOffline.bind(this);
  }

  start(tripId) {
    if (this.isTracking && this.tripId === tripId) return;
    if (this.isTracking) this.stop();

    this.tripId = tripId;
    this.isTracking = true;
    this.isPaused = false;

    document.addEventListener('visibilitychange', this._onVisibilityChange);
    window.addEventListener('online', this._onOnline);
    window.addEventListener('offline', this._onOffline);

    this._startWatchPosition();
    this._requestWakeLock();
    this._startRetryTimer();
    this._emitStatus('tracking');
  }

  stop() {
    this.isTracking = false;
    this.tripId = null;
    this.isPaused = false;

    document.removeEventListener('visibilitychange', this._onVisibilityChange);
    window.removeEventListener('online', this._onOnline);
    window.removeEventListener('offline', this._onOffline);

    this._stopWatchPosition();
    this._releaseWakeLock();
    this._stopRetryTimer();
    this._emitStatus('stopped');
  }

  onPosition(callback) {
    this.positionListeners.push(callback);
    return () => {
      this.positionListeners = this.positionListeners.filter(cb => cb !== callback);
    };
  }

  onStatus(callback) {
    this.statusListeners.push(callback);
    return () => {
      this.statusListeners = this.statusListeners.filter(cb => cb !== callback);
    };
  }

  getCurrentPosition() {
    return this.currentPosition;
  }

  getQueueCount() {
    return GpsQueue.getCount();
  }

  _startWatchPosition() {
    if (!navigator.geolocation) {
      this._emitStatus('error', 'Perangkat ini tidak mendukung GPS.');
      return;
    }

    this.watchId = navigator.geolocation.watchPosition(
      (position) => this._onPositionReceived(position),
      (error) => this._onPositionError(error),
      { enableHighAccuracy: true, maximumAge: 5000, timeout: 20000 }
    );
  }

  _stopWatchPosition() {
    if (this.watchId !== null && navigator.geolocation) {
      navigator.geolocation.clearWatch(this.watchId);
    }
    this.watchId = null;
  }

  _onPositionReceived(position) {
    const pos = {
      lat: position.coords.latitude,
      lng: position.coords.longitude,
      speed: position.coords.speed || 0,
      heading: position.coords.heading || null,
      accuracy: position.coords.accuracy || null,
    };

    this.currentPosition = pos;
    this._emitPosition(pos);

    if (this.isPaused) return;
    if (Date.now() - this.lastSentAt < SEND_INTERVAL_MS) return;

    this.lastSentAt = Date.now();
    this._sendPosition(pos);
  }

  _onPositionError(error) {
    let message;
    switch (error.code) {
      case 1:
        message = 'Izin lokasi ditolak. Aktifkan izin GPS untuk memulai perjalanan.';
        break;
      case 2:
        message = 'Lokasi GPS tidak dapat diperoleh. Pastikan GPS aktif.';
        break;
      case 3:
        message = 'Timeout mendapatkan lokasi GPS. Coba lagi.';
        break;
      default:
        message = 'Terjadi kesalahan pada GPS.';
    }
    this._emitStatus('error', message);
  }

  async _sendPosition(pos) {
    if (!this.tripId) return;

    const payload = {
      planned_trip_id: this.tripId,
      lat: pos.lat,
      lng: pos.lng,
      speed: pos.speed,
      heading: pos.heading,
      accuracy: pos.accuracy,
    };

    let setLastPositionOk = false;
    let gpsPhoneOk = false;

    try {
      await http().post('/planned-trips/set-last-position', {
        planned_trip_id: payload.planned_trip_id,
        lat: payload.lat,
        lng: payload.lng,
        speed: payload.speed,
      });
      setLastPositionOk = true;
    } catch (e) {
      console.warn('[GPS] set-last-position failed:', e.message);
    }

    try {
      await http().post('/gps/phone', {
        lat: payload.lat,
        lng: payload.lng,
        speed: payload.speed,
        heading: payload.heading,
        accuracy: payload.accuracy,
      });
      gpsPhoneOk = true;
    } catch (e) {
      console.warn('[GPS] /gps/phone failed:', e.message);
    }

    if (setLastPositionOk) {
      this._emitStatus('sent');
    } else {
      await GpsQueue.savePosition(payload);
      const count = await GpsQueue.getCount();
      this._emitStatus('queued', `${count} GPS menunggu pengiriman`);
    }
  }

  async _retryQueuedPositions() {
    if (!navigator.onLine || !this.isTracking) return;

    const pending = await GpsQueue.getPendingPositions();
    if (pending.length === 0) return;

    for (const record of pending) {
      if (record.retries >= MAX_RETRIES) {
        await GpsQueue.removePosition(record.id);
        continue;
      }

      try {
        await http().post('/planned-trips/set-last-position', {
          planned_trip_id: record.trip_id,
          lat: record.lat,
          lng: record.lng,
          speed: record.speed,
        });
        await GpsQueue.removePosition(record.id);
      } catch (e) {
        await GpsQueue.incrementRetry(record.id);
      }
    }

    const remaining = await GpsQueue.getCount();
    if (remaining > 0) {
      this._emitStatus('queued', `${remaining} GPS menunggu pengiriman`);
    }
  }

  _handleVisibilityChange() {
    if (!this.isTracking) return;

    if (document.hidden) {
      this.isPaused = true;
      this._emitStatus('background');
    } else {
      this.isPaused = false;
      this._emitStatus('foreground');
      this._retryQueuedPositions();
    }
  }

  _handleOnline() {
    if (!this.isTracking) return;
    this._emitStatus('online');
    this._retryQueuedPositions();
  }

  _handleOffline() {
    if (!this.isTracking) return;
    this._emitStatus('offline');
  }

  async _requestWakeLock() {
    if (!this.wakeLockSupported) return;
    try {
      this.wakeLock = await navigator.wakeLock.request('screen');
      this.wakeLock.addEventListener('release', () => {
        this.wakeLock = null;
      });
    } catch (e) {
      console.warn('[GPS] Wake Lock request failed:', e.message);
    }
  }

  _releaseWakeLock() {
    if (this.wakeLock) {
      this.wakeLock.release();
      this.wakeLock = null;
    }
  }

  _startRetryTimer() {
    this._stopRetryTimer();
    this.retryTimer = setInterval(() => {
      this._retryQueuedPositions();
    }, RETRY_INTERVAL_MS);
  }

  _stopRetryTimer() {
    if (this.retryTimer) {
      clearInterval(this.retryTimer);
      this.retryTimer = null;
    }
  }

  _emitPosition(pos) {
    this.positionListeners.forEach(cb => {
      try { cb(pos); } catch (e) { console.error('[GPS] Position listener error:', e); }
    });
  }

  _emitStatus(status, message) {
    this.statusListeners.forEach(cb => {
      try { cb(status, message); } catch (e) { console.error('[GPS] Status listener error:', e); }
    });
  }
}

const instance = new GpsTrackingService();
export default instance;
