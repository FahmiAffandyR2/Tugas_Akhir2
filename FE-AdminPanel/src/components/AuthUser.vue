<template>
  <div v-if="authUser">
    <transition name="fade">
      <img
        v-if="authUser.avatar"
        :src="authUser.avatar"
        class="w-16 h-16 rounded-full lg:w-20 lg:h-20"
        alt=""
      />
    </transition>
    <ul>
      <li class="mb-1 font-bold">{{ authUser.name }}</li>
      <li>Email: {{ authUser.email }}</li>
      <li v-if="authUser.emailVerified" class="text-gray-500">
        Emailed Verified
      </li>
    </ul>
  </div>
</template>

<script>
import { mapGetters } from "vuex";

export default {
  name: "AuthUser",
  computed: {
    ...mapGetters("auth", ["authUser"]),
  },
};
</script>
