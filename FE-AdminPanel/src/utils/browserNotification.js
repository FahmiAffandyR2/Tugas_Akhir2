/**
 * Browser Notification Utility
 * Handles browser push notifications for GPS alerts
 */

/**
 * Request permission for browser notifications
 */
export function requestNotificationPermission() {
  if (!('Notification' in window)) {
    console.warn('[BrowserNotification] Notifications not supported');
    return false;
  }

  if (Notification.permission === 'granted') {
    return true;
  }

  if (Notification.permission !== 'denied') {
    Notification.requestPermission().then(permission => {
      console.log('[BrowserNotification] Permission:', permission);
    });
  }

  return false;
}

/**
 * Check if notifications are supported and permitted
 */
export function areNotificationsEnabled() {
  return 'Notification' in window && Notification.permission === 'granted';
}

/**
 * Send a browser notification
 *
 * @param {string} title - Notification title
 * @param {string} body - Notification body
 * @param {string} icon - Notification icon URL (optional)
 * @param {string} tag - Notification tag for grouping (optional)
 */
export function sendBrowserNotification(title, body, icon = null, tag = null) {
  if (!areNotificationsEnabled()) {
    console.warn('[BrowserNotification] Notifications not enabled');
    return null;
  }

  const options = {
    body: body,
    icon: icon || '/img/alert-icon.png',
    badge: '/img/badge-icon.png',
    tag: tag || 'gps-alert-' + Date.now(),
    requireInteraction: true,
    silent: false,
  };

  try {
    const notification = new Notification(title, options);

    notification.onclick = () => {
      window.focus();
      notification.close();
    };

    console.log('[BrowserNotification] Notification sent:', title);
    return notification;
  } catch (error) {
    console.error('[BrowserNotification] Failed to send notification:', error);
    return null;
  }
}

/**
 * Send a GPS alert notification
 *
 * @param {string} alertType - 'gps_offline', 'out_of_route', 'speed_exceeded'
 * @param {string} driverName - Driver name
 * @param {string} message - Alert message
 */
export function sendGpsAlertNotification(alertType, driverName, message) {
  const icons = {
    gps_offline: '🔴',
    out_of_route: '⚠️',
    speed_exceeded: '⚡',
    arrived_at_depot: '🏠',
  };

  const titles = {
    gps_offline: 'GPS Offline',
    out_of_route: 'Keluar Jalur',
    speed_exceeded: 'Kecepatan Berlebih',
    arrived_at_depot: 'Tiba di Pool',
  };

  const icon = icons[alertType] || '⚠️';
  const title = `${icon} ${titles[alertType] || 'GPS Alert'} - ${driverName}`;
  const body = message;

  return sendBrowserNotification(title, body, null, `gps-alert-${alertType}-${driverName}`);
}
