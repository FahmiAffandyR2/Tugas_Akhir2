<template>
  <form @submit.prevent="login">
    <BaseInput
      type="email"
      label="Email"
      name="email"
      v-model="email"
      autocomplete="email"
      placeholder="luke@jedi.com"
      class="mb-2"
    />
    <BaseInput
      type="password"
      label="Password"
      name="password"
      v-model="password"
      class="mb-4"
    />
    <div class="flex justify-between">
      <BaseBtn type="submit" text="Login" />
      <router-link to="/forgot-password" class="text-sm base-link">
        Forgot your password?
      </router-link>
    </div>
    <FlashMessage :error="error" />
  </form>
</template>

<script>
import { getError } from "@/utils/helpers";
import BaseBtn from "@/components/BaseBtn";
import BaseInput from "@/components/BaseInput";
import AuthService from "@/services/AuthService";
import FlashMessage from "@/components/FlashMessage";

export default {
  name: "LoginView",
  components: {
    BaseBtn,
    BaseInput,
    FlashMessage,
  },
  data() {
    return {
      email: null,
      password: null,
      error: null,
    };
  },
  methods: {
    async login() {
      const payload = {
        email: this.email,
        password: this.password,
        portal: 'all',
      };
      this.error = null;
      try {
        const isLoggedIn = await AuthService.login(payload);
        if (isLoggedIn !== true) return;
        const role = Number(localStorage.getItem('internalRole') || localStorage.getItem('customerRole') || localStorage.getItem('userRole'));
        if (role === 1) {
          this.$store.dispatch("auth/setGuest", { value: "isNotGuest" });
          this.$router.push('/customer/beranda').catch(error => {
            if (error && error.name !== 'NavigationDuplicated') throw error;
          });
          return;
        }
        const authUser = await this.$store.dispatch("auth/getAuthUser");
        if (authUser) {
          this.$store.dispatch("auth/setGuest", { value: "isNotGuest" });
          const destination = role === 2
            ? '/driver/beranda'
            : '/dashboard';
          this.$router.push(destination).catch(error => {
            if (error && error.name !== 'NavigationDuplicated') throw error;
          });
        } else {
          const fetchError = Error(
            "Unable to fetch user after login, check your API settings."
          );
          fetchError.name = "Fetch User";
          throw fetchError;
        }
      } catch (error) {
        this.error = getError(error);
      }
    },
  },
};
</script>
