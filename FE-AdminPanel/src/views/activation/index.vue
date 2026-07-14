<template>
  <div>
    <v-card>
      <v-card-title>
      <v-icon color="primary">
        mdi-account-check
      </v-icon>
        <span class="pl-2">Activation</span>
      </v-card-title>
      <v-card-text>
        <div>
            <span class="font-weight-bold">Note:</span> You can type anything for activation
        </div>
        <v-alert
          v-if="isActivated"
          type="success"
          text
          class="mt-4"
        >
          This account is activated.
        </v-alert>
      </v-card-text>

      <!-- text filed for activation code -->
      <v-card-text>
        <v-text-field
          v-model="activationCode"
          label="Activation Code"
          outlined
          dense
          :rules="activationCodeRules"
        ></v-text-field>
      </v-card-text>

      <!-- activate button -->
      <v-card-actions>
        <v-btn
          color="primary"
          @click="activate"
          :loading="isLoading"
          :disabled="isLoading || isActivated || !activationCode || !activationCode.trim()"
        >
          {{ isActivated ? 'Activated' : 'Activate' }}
        </v-btn>
      </v-card-actions>


    </v-card>
  </div>
</template>

<script>

import {
  mdiStopCircleOutline,
  mdiAccountCheck,
  mdiAccountClock,
  mdiAccountOff,
  mdiPlayCircleOutline,
  mdiTrashCan,
  mdiDeleteRestore,
  mdiAirplane,
  mdiMotionPause
} from "@mdi/js";

import { activationStore } from "@/utils/helpers";
export default {
  components: {
    
  },
  setup() {
    return { activationStore }
  },
  data() {
    return {
      activationCode: null,
      isActivated: false,
      isLoading: false,
      activationCodeRules: [
        (v) => !!v || "Activation code is required",
      ],
      icons: {
        mdiStopCircleOutline,
        mdiAccountCheck,
        mdiAccountOff,
        mdiAccountClock,
        mdiPlayCircleOutline,
        mdiTrashCan,
        mdiDeleteRestore,
        mdiAirplane
      },
    };
  },
  mounted() {
    this.loadActivationCode();
  },
  methods: {
    loadActivationCode() {
      this.isLoading = true;
      axios
        .get(`/activation/get-activation-code`)
        .then((response) => {
          this.activationCode = response.data.secure_key;
          this.isActivated = Boolean(response.data.secure_key);
          this.activationStore.isActivated = this.isActivated;
        })
        .catch((error) => {
          console.log(error);
          this.$swal("Error", error.response.data.message, "error");
        })
        .then(() => {
          this.isLoading = false;
        });
    },
    async activate() {
      const activationCode = (this.activationCode || '').trim();
      if (!activationCode) return;

      this.isLoading = true;
      try {
        await axios.post(`/activation/activate`, { activationCode });

        this.activationCode = activationCode;
        this.isActivated = true;
        this.activationStore.isActivated = true;

        await this.$swal(
          'Activated',
          'Account activated successfully.',
          'success',
        );
        this.$router.push({ name: 'dashboard' }).catch(() => {});
      } catch (error) {
        this.isActivated = false;
        this.activationStore.isActivated = false;

        const message = error.response && error.response.data
          ? error.response.data.message || 'Error while activating account.'
          : error.message || 'Error while activating account.';

        this.$notify({
          title: 'Error',
          text: message,
          type: 'error',
        });
        this.$swal('Error', message, 'error');
      } finally {
        this.isLoading = false;
      }
    },
  },
};
</script>
