<template>
  <v-app>
    <v-app-bar app flat>
      <v-toolbar-title>Portal Staf</v-toolbar-title>
      <v-spacer />
      <v-btn text @click="logout"><v-icon left>mdi-logout</v-icon>Keluar</v-btn>
    </v-app-bar>
    <v-main><v-container class="py-6"><slot /></v-container></v-main>
  </v-app>
</template>

<script>
import AuthService from '@/services/AuthService'
export default {
  methods: {
    logout() { AuthService.logout('internal') },
  },
}
</script>
