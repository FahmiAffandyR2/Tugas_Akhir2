import axios from "axios";

import firebase, { isFirebaseEnabled } from '@/firebaseConfig';
import { Keys } from '@/config';
import Router from '../router/index'

const passwordLoginEndpoint = '/auth/login'
const useFirebaseAuth = Keys.VUE_APP_AUTH_PROVIDER === 'firebase' && isFirebaseEnabled

function getDeviceName() {
  if (!window.vm || !window.vm.$browserDetect) return 'web';

  const { name, version } = window.vm.$browserDetect.meta;
  return `${name}- v${version}`;
}

function tokenKey(portal) {
  return portal === 'customer' ? 'customerToken' : 'internalToken'
}

function roleKey(portal) {
  return portal === 'customer' ? 'customerRole' : 'internalRole'
}

function storeToken(token, portal) {
  // Laravel Sanctum expects the complete "id|plain-text-token" value.
  // Removing the id makes the first authenticated request fail.
  localStorage.setItem(tokenKey(portal), token)
  axios.defaults.headers.common.Authorization = `Bearer ${token}`
}

function storeRole(user, portal) {
  localStorage.setItem(roleKey(portal), String(user && user.role))
  if (portal === 'internal') localStorage.setItem('userRole', String(user && user.role))
}


export default {
  async login2(payload) {
    await authClient.get("/sanctum/csrf-cookie");
    return authClient.post("/login", payload);
  },
  isUserLoggedIn(portal = 'internal') {
    let isAuthenticated = false

    if (useFirebaseAuth) {
      const firebaseCurrentUser = firebase.auth().currentUser

      if (firebaseCurrentUser) isAuthenticated = true
      else isAuthenticated = false
    }

    const token = localStorage.getItem(tokenKey(portal))
      || (portal === 'internal' ? localStorage.getItem('freshToken') : null);

    // Current Sanctum tokens use "id|secret". Tokens saved by the old
    // frontend were truncated and must not be treated as authenticated.
    return Boolean(token && token !== 'null' && token.includes('|'));
  },
  async login (payload) {
    const portal = payload.portal || 'internal'
    // If user is already logged in notify and exit
    if (this.isUserLoggedIn(portal)) {
      payload.notify({
        title: 'Login Attempt',
        text: 'You are already logged in!',
        type: 'warning'
      })
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
      const allowed = portal === 'customer' ? role === 1 : (role === 0 || role === 2)
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
      storeToken(response.data.token, portal)
      storeRole(response.data.user_data, portal)
      return true;
    } catch (error) {
      localStorage.removeItem(tokenKey(portal))
      localStorage.removeItem(roleKey(portal))
      if (portal === 'internal') localStorage.removeItem('userRole')
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
    Router.push(portal === 'customer' ? '/customer/login' : '/login').catch(() => {})
  },
  logout2() {
    return authClient.post("/logout");
  },
  async forgotPassword(payload) {
    await authClient.get("/sanctum/csrf-cookie");
    return authClient.post("/forgot-password", payload);
  },
  getAuthUser() {
    return axios.post("/auth/verify-user");
  },
  updateProfile(payload) {
    return axios.post('/users/update-profile', payload)
  },
  async resetPassword(payload) {
    return axios
    .post('/auth/reset-password', {
      email: payload.email,
    });
  },
  registerDriver(payload) {
    return axios.post('/auth/register-driver', payload)
  },
  registerCustomer(payload) {
    return axios.post('/auth/register-customer', payload)
  },
  updatePassword(payload) {
    return authClient.put("/user/password", payload);
  },
  async registerUser(payload) {
    await authClient.get("/sanctum/csrf-cookie");
    return authClient.post("/register", payload);
  },
  sendVerification(payload) {
    return authClient.post("/email/verification-notification", payload);
  },
  updateUser(payload) {
    return authClient.put("/user/profile-information", payload);
  },
  checkError(error, router, swal) {
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
      this.logout();
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
