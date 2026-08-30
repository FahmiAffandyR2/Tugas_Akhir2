require("./bootstrap");

import "@/plugins/vue-composition-api";
import "@/styles/styles.scss";

import Vue from "vue";
import App from "./App.vue";
import vuetify from "./plugins/vuetify";
import router from "./router";
import store from "./store";
import registerServiceWorker from "./registerServiceWorker";

import "./axios";
import "./firebaseConfig";

import Notifications from "vue-notification";
import VueSweetalert2 from "vue-sweetalert2";
import VueProgressBar from "vue-progressbar";
import browserDetect from "vue-browser-detect-plugin";

import "sweetalert2/dist/sweetalert2.min.css";

Vue.use(Notifications);
Vue.use(VueSweetalert2);
Vue.use(require("vue-moment"));
Vue.use(browserDetect);

const progressBarOptions = {
  color: "#9155fd",
  failedColor: "#874b4b",
  thickness: "2px",
  transition: {
    speed: "0.5s",
    opacity: "0.6s",
    termination: 500,
  },
  autoRevert: true,
  location: "top",
  inverse: false,
};

Vue.use(VueProgressBar, progressBarOptions);

Vue.config.productionTip = false;

const app = new Vue({
  router,
  store,
  vuetify,
  render: (h) => h(App),
}).$mount("#app");

window.vm = app;

registerServiceWorker();
