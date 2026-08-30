<template>
  <div class="px-6 py-4">
    <vue-element-loading :active="isLoading" />
    <v-alert v-if="error" type="error" text>
      {{ error }}
    </v-alert>
    <div v-else v-html="privacyPolicy"></div>
  </div>
</template>

<script>

import VueElementLoading from "vue-element-loading";

export default {
  components: {
    VueElementLoading,
  },
  data() {
    return {
      isLoading: false,
      privacyPolicy: null,
      error: null,
    };
  },
  mounted() {
    this.getPrivacy();
  },
  methods: {
    getPrivacy() {
      this.isLoading = true;
      this.error = null;
      axios
        .get("/docs/privacy-policy")
        .then((response) => {
          this.privacyPolicy = response.data.privacy;
        })
        .catch((error) => {
          this.error = "Kebijakan privasi belum dapat dimuat. Silakan coba kembali.";
          this.$notify({
            title: "Error",
            text: this.error,
            type: "error",
          });
        })
        .then(() => {
          this.isLoading = false;
        });
    },
  },
};
</script>
