import axios from "axios";

import firebase, { isFirebaseEnabled } from '@/firebaseConfig';
import { Keys } from '@/config';
import Router from '../router/index'

const loginEvent = 'freshToken'
const passwordLoginEndpoint = '/auth/login'
const useFirebaseAuth = Keys.VUE_APP_AUTH_PROVIDER === 'firebase' && isFirebaseEnabled

function getDeviceName() {
  if (!window.vm || !window.vm.$browserDetect) return 'web';

  const { name, version } = window.vm.$browserDetect.meta;
  return `${name}- v${version}`;
}

function storeToken(token) {
  // Laravel Sanctum expects the complete "id|plain-text-token" value.
  // Removing the id makes the first authenticated request fail.
  localStorage.setItem(loginEvent, token)
  axios.defaults.headers.common.Authorization = `Bearer ${token}`
}


export default {
  async login2(payload) {
    await authClient.get("/sanctum/csrf-cookie");
    return authClient.post("/login", payload);
  },
  isUserLoggedIn() {
    let isAuthenticated = false

    if (useFirebaseAuth) {
      const firebaseCurrentUser = firebase.auth().currentUser

      if (firebaseCurrentUser) isAuthenticated = true
      else isAuthenticated = false
    }

    const token = localStorage.getItem(loginEvent);

    // Current Sanctum tokens use "id|secret". Tokens saved by the old
    // frontend were truncated and must not be treated as authenticated.
    return Boolean(token && token !== 'null' && token.includes('|'));
  },
  async login (payload) {
    // If user is already logged in notify and exit
    if (this.isUserLoggedIn()) {
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
          email: payload.email,
          password: payload.password,
          device_name: getDeviceName(),
        });
      }

      const isAdmin = response.data.admin
      if (!isAdmin)
      {
        const error = Error(
          "Your account can not be used here! Only admin accounts."
        );
        error.name = "Not admin";
        throw error;
      }
      storeToken(response.data.token)
      return true;
    } catch (error) {
      localStorage.setItem(loginEvent, null)
      if (payload.notify) {
        payload.notify({
          title: 'Error',
          text: error.message,
          type: 'error'
        })
      }
    }
  },
  async logout() {

    if (useFirebaseAuth) {
      const firebaseCurrentUser = firebase.auth().currentUser

      if (firebaseCurrentUser) {
        await firebase.auth().signOut();
      }
    }

    localStorage.setItem(loginEvent, null)

    // If user clicks on logout -> redirect
    Router.push('/login').catch(() => {})
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
  async resetPassword(payload) {
    return axios
    .post('/auth/reset-password', {
      email: payload.email,
    });
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
