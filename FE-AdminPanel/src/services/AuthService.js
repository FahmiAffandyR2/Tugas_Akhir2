import axios from "axios";

import firebase, { isFirebaseEnabled } from '@/firebaseConfig';
import { Keys } from '@/config';
import { beginGoogleOAuth, consumeGoogleOAuth } from '@/utils/googleOAuthState';
import Router from '../router/index'

const passwordLoginEndpoint = '/auth/login'
const useFirebaseAuth = Keys.VUE_APP_AUTH_PROVIDER === 'firebase' && isFirebaseEnabled
const canUseGoogleAuth = Boolean(Keys.GOOGLE_CLIENT_ID)

function getDeviceName() {
  if (!window.vm || !window.vm.$browserDetect || !window.vm.$browserDetect.meta) return 'web';

  const { name, version } = window.vm.$browserDetect.meta;
  return `${name || 'browser'}- v${version || 'unknown'}`;
}

function tokenKey(portal) {
  return portal === 'customer' ? 'customerToken' : 'internalToken'
}

function roleKey(portal) {
  return portal === 'customer' ? 'customerRole' : 'internalRole'
}

function portalForRole(role) {
  return Number(role) === 1 ? 'customer' : 'internal'
}

function storeToken(token, portal) {
  // Laravel Sanctum expects the complete "id|plain-text-token" value.
  // Removing the id makes the first authenticated request fail.
  localStorage.setItem(tokenKey(portal), token)
}

function storeRole(user, portal) {
  localStorage.setItem(roleKey(portal), String(user && user.role))
  if (portal === 'internal') localStorage.setItem('userRole', String(user && user.role))
}

function clearPortalSession(portal) {
  localStorage.removeItem(tokenKey(portal))
  localStorage.removeItem(roleKey(portal))
  if (portal === 'internal') localStorage.removeItem('userRole')
}

function clearAllSessions() {
  clearPortalSession('internal')
  clearPortalSession('customer')
  localStorage.removeItem('freshToken')
}


export default {
  async login2(payload) {
    await axios.get("/sanctum/csrf-cookie");
    return axios.post("/login", payload);
  },
  canUseGoogleLogin() {
    return canUseGoogleAuth
  },
  isUserLoggedIn(portal = 'internal') {
    let isAuthenticated = false

    if (useFirebaseAuth) {
      const firebaseCurrentUser = firebase.auth().currentUser

      if (firebaseCurrentUser) isAuthenticated = true
      else isAuthenticated = false
    }

    if (portal === 'all') {
      return this.isUserLoggedIn('internal') || this.isUserLoggedIn('customer')
    }

    const token = localStorage.getItem(tokenKey(portal))
      || (portal === 'internal' ? localStorage.getItem('freshToken') : null);

    // Current Sanctum tokens use "id|secret". Tokens saved by the old
    // frontend were truncated and must not be treated as authenticated.
    return Boolean(token && token !== 'null' && token.includes('|'));
  },
  async login (payload) {
    const portal = payload.portal || 'internal'

    if (portal === 'all') {
      clearAllSessions()
    } else if (this.isUserLoggedIn(portal)) {
      if (payload.notify) {
        payload.notify({
          title: 'Login Attempt',
          text: 'You are already logged in!',
          type: 'warning'
        })
      }
      return false
    }
    // Try to sigin
    try {
      let response;

      if (useFirebaseAuth) {
        const result = await firebase.auth().signInWithEmailAndPassword(payload.email, payload.password);
        const token = await result.user.getIdToken(true);

        response = await axios.post('/auth/loginViaToken', {
          device_name: getDeviceName(),
          token
        });
      } else {
        response = await axios.post(passwordLoginEndpoint, {
          email: payload.email.trim().toLowerCase(),
          password: payload.password,
          device_name: getDeviceName(),
          portal,
        });
      }

      const role = Number(response.data.user_data && response.data.user_data.role)
      const actualPortal = portal === 'all' ? portalForRole(role) : portal
      const allowed = portal === 'all'
        ? [0, 1, 2, 3].includes(role)
        : (portal === 'customer' ? role === 1 : (role === 0 || role === 2 || role === 3))
      if (!allowed)
      {
        const error = Error(
          portal === 'customer'
            ? 'Akun ini bukan akun customer.'
            : 'Akun ini tidak memiliki akses ke portal internal.'
        );
        error.name = "Not admin";
        throw error;
      }
      storeToken(response.data.token, actualPortal)
      storeRole(response.data.user_data, actualPortal)
      return true;
    } catch (error) {
      const portals = portal === 'all' ? ['internal', 'customer'] : [portal]
      portals.forEach(item => clearPortalSession(item))
      if (portal === 'all') localStorage.removeItem('freshToken')
      const message = error.response && error.response.data && error.response.data.message
        ? error.response.data.message
        : error.message
      if (payload.notify) {
        payload.notify({
          title: 'Error',
          text: message,
          type: 'error'
        })
      }
      return { success: false, message }
    }
  },
  async loginWithGoogle(payload = {}) {
    const portal = payload.portal || 'all'

    if (!canUseGoogleAuth) {
      return {
        success: false,
        message: 'Login Google belum dikonfigurasi.'
      }
    }

    if (portal === 'all') {
      clearAllSessions()
    } else if (this.isUserLoggedIn(portal)) {
      if (payload.notify) {
        payload.notify({
          title: 'Login Attempt',
          text: 'You are already logged in!',
          type: 'warning'
        })
      }
      return false
    }

    // Build Google OAuth2 authorization URL
    const clientId = Keys.GOOGLE_CLIENT_ID
    const redirectUri = window.location.origin + '/auth/google/callback'
    const scope = 'email profile openid'
    const state = beginGoogleOAuth(portal)

    const authUrl = 'https://accounts.google.com/o/oauth2/v2/auth'
      + '?client_id=' + encodeURIComponent(clientId)
      + '&redirect_uri=' + encodeURIComponent(redirectUri)
      + '&response_type=code'
      + '&scope=' + encodeURIComponent(scope)
      + '&state=' + encodeURIComponent(state)
      + '&prompt=select_account'

    window.location.href = authUrl
    return 'redirecting'
  },
  async handleGoogleCallback(code, state) {
    let portal
    try {
      portal = consumeGoogleOAuth(state)
      if (!code || typeof code !== 'string') throw new Error('Kode login Google tidak valid.')
    } catch (error) {
      return { success: false, message: error.message }
    }
    try {
      const response = await axios.post('/auth/google-login', {
        code,
        portal,
        device_name: getDeviceName(),
      })

      const role = Number(response.data.user_data && response.data.user_data.role)
      const actualPortal = portal === 'all' ? portalForRole(role) : portal
      const allowed = portal === 'all'
        ? [0, 1, 2, 3].includes(role)
        : (portal === 'customer' ? role === 1 : (role === 0 || role === 2 || role === 3))

      if (!allowed) {
        const error = Error(
          portal === 'customer'
            ? 'Akun ini bukan akun customer.'
            : 'Akun ini tidak memiliki akses ke portal internal.'
        )
        error.name = 'Not authorized'
        throw error
      }

      storeToken(response.data.token, actualPortal)
      storeRole(response.data.user_data, actualPortal)
      return { success: true, portal: actualPortal }
    } catch (error) {
      const portals = portal === 'all' ? ['internal', 'customer'] : [portal]
      portals.forEach(item => clearPortalSession(item))
      if (portal === 'all') localStorage.removeItem('freshToken')

      const message = error.response && error.response.data && error.response.data.message
        ? error.response.data.message
        : error.message

      return { success: false, message }
    }
  },
  async logout(portal = 'internal') {

    if (useFirebaseAuth) {
      const firebaseCurrentUser = firebase.auth().currentUser

      if (firebaseCurrentUser) {
        await firebase.auth().signOut();
      }
    }

    localStorage.removeItem(tokenKey(portal))
    localStorage.removeItem(roleKey(portal))
    if (portal === 'internal') {
      localStorage.removeItem('freshToken')
      localStorage.removeItem('userRole')
    }

    // If user clicks on logout -> redirect
    Router.push('/login').catch(() => {})
  },
  logout2() {
    return axios.post("/logout");
  },
  async forgotPassword(payload) {
    return axios.post('/auth/forgot-password', {
      email: payload.email,
    })
  },
  getAuthUser() {
    return axios.post("/auth/verify-user");
  },
  updateProfile(payload) {
    return axios.post('/users/update-profile', payload)
  },
  async resetPassword(payload) {
    return axios.post('/auth/reset-password', {
      email: payload.email,
      token: payload.token,
      password: payload.password,
      password_confirmation: payload.password_confirmation,
    })
  },
  registerDriver(payload) {
    return axios.post('/auth/register-driver', payload)
  },
  registerCustomer(payload) {
    return axios.post('/auth/register-customer', payload)
  },
  updatePassword(payload) {
    return axios.put("/user/password", payload);
  },
  async registerUser(payload) {
    await axios.get("/sanctum/csrf-cookie");
    return axios.post("/register", payload);
  },
  sendVerification(payload) {
    return axios.post("/email/verification-notification", payload);
  },
  updateUser(payload) {
    return axios.put("/user/profile-information", payload);
  },
  checkError(error, router, swal, portal = 'internal') {
    const status = error && error.response ? error.response.status : null;
    const responseMessage = error && error.response && error.response.data
      ? error.response.data.message
      : null;
    const message = responseMessage
      || (typeof error === 'string' ? error : error && error.message)
      || 'An unexpected error occurred.';

    console.error('API request failed', {
      status,
      url: error && error.config ? error.config.url : null,
      message,
    });

    if (status === 401 || message.includes('Unauthenticated')) {
      this.logout(portal);
      if (router.currentRoute.name !== 'login') {
        router.push({ name: 'login' }).catch(() => {});
      }
      return;
    }

    if (typeof swal === 'function') {
      swal('Error', message, 'error');
    }
  }
};
