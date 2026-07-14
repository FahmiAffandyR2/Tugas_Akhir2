import axios from "axios";
import {Keys} from '/src/config.js'

axios.defaults.baseURL = Keys.VUE_APP_API_URL + "/api"; // change this if you want to use a different url for APIs
//axios.defaults.withCredentials = true;

// Always read the latest token. This also prevents a race immediately after
// login and guarantees every module uses the same Axios authorization state.
axios.interceptors.request.use((config) => {
  const token = localStorage.getItem('freshToken');

  if (token && token !== 'null' && token.includes('|')) {
    config.headers.Authorization = `Bearer ${token}`;
  } else if (config.headers) {
    delete config.headers.Authorization;
  }

  return config;
});

window.axios = axios;
