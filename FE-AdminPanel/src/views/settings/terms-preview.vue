<template>
  <div class="px-6 py-4">
    <vue-element-loading :active="isLoading" />
    <v-alert v-if="error" type="error" text>
      {{ error }}
    </v-alert>
    <div v-else v-html="terms"></div>
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
      terms: null,
      error: null,
    };
  },
  mounted() {
    this.getTerms();
  },
  methods: {
    getTerms() {
      this.isLoading = true;
      this.error = null;
      axios
        .get("/docs/terms")
        .then((response) => {
          this.terms = response.data.terms;
        })
        .catch((error) => {
          this.error = "Syarat dan ketentuan belum dapat dimuat. Silakan coba kembali.";
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
