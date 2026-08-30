<template>
  <v-card flat class="playback-player pa-4">
    <div class="d-flex align-center mb-3">
      <v-icon color="primary" class="mr-2">mdi-history</v-icon>
      <span class="text-h6 font-weight-bold">Playback Perjalanan</span>
      <v-spacer />
      <v-chip small color="primary" outlined>
        <v-icon small left>mdi-map-marker-path</v-icon>
        {{ currentIndex + 1 }} / {{ totalPoints }} titik
      </v-chip>
    </div>

    <!-- Progress Bar -->
    <v-slider
      v-model="currentIndex"
      :max="totalPoints - 1"
      :disabled="totalPoints === 0"
      color="primary"
      track-color="grey lighten-2"
      @input="onSliderChange"
      class="mb-2"
    >
      <template v-slot:prepend>
        <v-icon small @click="skipBackward">mdi-skip-backward</v-icon>
      </template>
      <template v-slot:append>
        <v-icon small @click="skipForward">mdi-skip-forward</v-icon>
      </template>
    </v-slider>

    <!-- Time Display -->
    <div class="d-flex justify-space-between mb-3">
      <span class="caption grey--text">{{ currentTime }}</span>
      <span class="caption grey--text">{{ totalTime }}</span>
    </div>

    <!-- Controls -->
    <div class="d-flex justify-center align-center mb-3">
      <v-btn icon small @click="skipBackward" :disabled="totalPoints === 0">
        <v-icon>mdi-rewind-30</v-icon>
      </v-btn>

      <v-btn icon small @click="previousPoint" :disabled="totalPoints === 0">
        <v-icon>mdi-skip-previous</v-icon>
      </v-btn>

      <v-btn
        fab
        :color="isPlaying ? 'error' : 'primary'"
        class="mx-3"
        @click="togglePlay"
        :disabled="totalPoints === 0"
      >
        <v-icon large>{{ isPlaying ? 'mdi-pause' : 'mdi-play' }}</v-icon>
      </v-btn>

      <v-btn icon small @click="nextPoint" :disabled="totalPoints === 0">
        <v-icon>mdi-skip-next</v-icon>
      </v-btn>

      <v-btn icon small @click="skipForward" :disabled="totalPoints === 0">
        <v-icon>mdi-fast-forward-30</v-icon>
      </v-btn>
    </div>

    <!-- Speed Control -->
    <div class="d-flex justify-center align-center mb-3">
      <span class="caption grey--text mr-3">Kecepatan:</span>
      <v-btn-toggle v-model="speed" mandatory dense>
        <v-btn x-small value="1">1x</v-btn>
        <v-btn x-small value="2">2x</v-btn>
        <v-btn x-small value="4">4x</v-btn>
        <v-btn x-small value="8">8x</v-btn>
      </v-btn-toggle>
    </div>

    <!-- Stats -->
    <v-divider class="my-3" />
    <v-row dense>
      <v-col cols="6" sm="3">
        <div class="text-center">
          <div class="caption grey--text">Jarak</div>
          <div class="subtitle-1 font-weight-bold">{{ totalDistance }} km</div>
        </div>
      </v-col>
      <v-col cols="6" sm="3">
        <div class="text-center">
          <div class="caption grey--text">Kecepatan Max</div>
          <div class="subtitle-1 font-weight-bold">{{ maxSpeed }} km/h</div>
        </div>
      </v-col>
      <v-col cols="6" sm="3">
        <div class="text-center">
          <div class="caption grey--text">Kecepatan Avg</div>
          <div class="subtitle-1 font-weight-bold">{{ avgSpeed }} km/h</div>
        </div>
      </v-col>
      <v-col cols="6" sm="3">
        <div class="text-center">
          <div class="caption grey--text">Durasi</div>
          <div class="subtitle-1 font-weight-bold">{{ duration }}</div>
        </div>
      </v-col>
    </v-row>

    <!-- Export Button -->
    <div class="d-flex justify-end mt-3">
      <v-btn
        outlined
        color="primary"
        small
        @click="$emit('export')"
        :disabled="!tripId"
      >
        <v-icon small left>mdi-download</v-icon>
        Export CSV
      </v-btn>
    </div>
  </v-card>
</template>

<script>
export default {
  name: 'PlaybackPlayer',
  props: {
    logs: {
      type: Array,
      default: () => [],
    },
    tripId: {
      type: [Number, String],
      default: null,
    },
    summary: {
      type: Object,
      default: () => ({}),
    },
  },
  data() {
    return {
      isPlaying: false,
      currentIndex: 0,
      speed: '1',
      playInterval: null,
    };
  },
  computed: {
    totalPoints() {
      return this.logs.length;
    },
    currentLog() {
      return this.logs[this.currentIndex] || null;
    },
    currentTime() {
      if (!this.currentLog) return '00:00:00';
      return new Date(this.currentLog.recorded_at).toLocaleTimeString('id-ID');
    },
    totalTime() {
      if (this.totalPoints === 0) return '00:00:00';
      const lastLog = this.logs[this.totalPoints - 1];
      return new Date(lastLog.recorded_at).toLocaleTimeString('id-ID');
    },
    totalDistance() {
      return this.summary?.total_distance_km?.toFixed(1) || '0.0';
    },
    maxSpeed() {
      return Math.round(this.summary?.max_speed || 0);
    },
    avgSpeed() {
      return Math.round(this.summary?.avg_speed || 0);
    },
    duration() {
      if (this.totalPoints < 2) return '-';
      const first = new Date(this.logs[0].recorded_at);
      const last = new Date(this.logs[this.totalPoints - 1].recorded_at);
      const diffMs = last - first;
      const hours = Math.floor(diffMs / 3600000);
      const minutes = Math.floor((diffMs % 3600000) / 60000);
      if (hours > 0) return `${hours}j ${minutes}m`;
      return `${minutes}m`;
    },
    intervalMs() {
      const speeds = { '1': 1000, '2': 500, '4': 250, '8': 125 };
      return speeds[this.speed] || 1000;
    },
  },
  watch: {
    isPlaying(val) {
      if (val) {
        this.startPlayback();
      } else {
        this.stopPlayback();
      }
    },
    speed() {
      if (this.isPlaying) {
        this.stopPlayback();
        this.startPlayback();
      }
    },
  },
  beforeDestroy() {
    this.stopPlayback();
  },
  methods: {
    togglePlay() {
      if (this.currentIndex >= this.totalPoints - 1) {
        this.currentIndex = 0;
      }
      this.isPlaying = !this.isPlaying;
    },
    startPlayback() {
      this.playInterval = setInterval(() => {
        if (this.currentIndex < this.totalPoints - 1) {
          this.currentIndex++;
          this.emitPosition();
        } else {
          this.isPlaying = false;
        }
      }, this.intervalMs);
    },
    stopPlayback() {
      if (this.playInterval) {
        clearInterval(this.playInterval);
        this.playInterval = null;
      }
    },
    previousPoint() {
      if (this.currentIndex > 0) {
        this.currentIndex--;
        this.emitPosition();
      }
    },
    nextPoint() {
      if (this.currentIndex < this.totalPoints - 1) {
        this.currentIndex++;
        this.emitPosition();
      }
    },
    skipBackward() {
      const skip = Math.min(30, this.currentIndex);
      this.currentIndex = Math.max(0, this.currentIndex - skip);
      this.emitPosition();
    },
    skipForward() {
      const skip = Math.min(30, this.totalPoints - 1 - this.currentIndex);
      this.currentIndex = Math.min(this.totalPoints - 1, this.currentIndex + skip);
      this.emitPosition();
    },
    onSliderChange() {
      this.emitPosition();
    },
    emitPosition() {
      if (this.currentLog) {
        this.$emit('position-change', {
          lat: parseFloat(this.currentLog.latitude),
          lng: parseFloat(this.currentLog.longitude),
          speed: this.currentLog.speed,
          time: this.currentLog.recorded_at,
          index: this.currentIndex,
        });
      }
    },
  },
};
</script>

<style scoped>
.playback-player {
  border: 1px solid rgba(58, 53, 65, 0.08);
  border-radius: 16px;
}
</style>
