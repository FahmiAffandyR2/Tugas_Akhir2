<template>
  <div>
    <!-- Filter Bar -->
    <v-card class="mb-4">
      <v-card-text>
        <v-row align="center">
          <v-col cols="12" sm="3">
            <v-text-field v-model="filters.startDate" label="Tanggal Mulai" type="date" outlined dense />
          </v-col>
          <v-col cols="12" sm="3">
            <v-text-field v-model="filters.endDate" label="Tanggal Selesai" type="date" outlined dense />
          </v-col>
          <v-col cols="12" sm="3">
            <v-btn color="primary" @click="fetchData" :loading="loading">
              <v-icon left>mdi-magnify</v-icon>Filter
            </v-btn>
            <v-btn text @click="resetFilters" class="ml-2">Reset</v-btn>
          </v-col>
          <v-col cols="12" sm="3" class="text-right">
            <v-btn color="success" outlined @click="exportCSV" :loading="exporting">
              <v-icon left>mdi-download</v-icon>Export CSV
            </v-btn>
          </v-col>
        </v-row>
      </v-card-text>
    </v-card>

    <!-- Summary Cards -->
    <v-row class="mb-4" v-if="data">
      <v-col cols="12" sm="6" md="3">
        <v-card color="primary" dark>
          <v-card-text class="text-center">
            <div class="text-h4 font-weight-bold">{{ totalDrivers }}</div>
            <div>Active Drivers</div>
          </v-card-text>
        </v-card>
      </v-col>
      <v-col cols="12" sm="6" md="3">
        <v-card color="success" dark>
          <v-card-text class="text-center">
            <div class="text-h4 font-weight-bold">{{ formatCurrency(totalEarnings) }}</div>
            <div>Total Earnings</div>
          </v-card-text>
        </v-card>
      </v-col>
      <v-col cols="12" sm="6" md="3">
        <v-card color="info" dark>
          <v-card-text class="text-center">
            <div class="text-h4 font-weight-bold">{{ totalTrips }}</div>
            <div>Total Trips</div>
          </v-card-text>
        </v-card>
      </v-col>
      <v-col cols="12" sm="6" md="3">
        <v-card :color="avgSafetyScore >= 80 ? 'success' : avgSafetyScore >= 60 ? 'warning' : 'error'" dark>
          <v-card-text class="text-center">
            <div class="text-h4 font-weight-bold">{{ avgSafetyScore }}</div>
            <div>Avg Safety Score</div>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>

    <!-- New Summary Cards -->
    <v-row class="mb-4" v-if="data">
      <v-col cols="12" sm="4" md="2">
        <v-card color="teal" dark>
          <v-card-text class="text-center">
            <div class="text-h5 font-weight-bold">{{ avgOnTime }}%</div>
            <div class="caption">Avg On-Time</div>
          </v-card-text>
        </v-card>
      </v-col>
      <v-col cols="12" sm="4" md="2">
        <v-card color="indigo" dark>
          <v-card-text class="text-center">
            <div class="text-h5 font-weight-bold">{{ avgUtilization }}%</div>
            <div class="caption">Avg Utilization</div>
          </v-card-text>
        </v-card>
      </v-col>
      <v-col cols="12" sm="4" md="2">
        <v-card color="brown" dark>
          <v-card-text class="text-center">
            <div class="text-h5 font-weight-bold">{{ avgTripsPerWeek }}</div>
            <div class="caption">Trips/Week</div>
          </v-card-text>
        </v-card>
      </v-col>
      <v-col cols="12" sm="4" md="2">
        <v-card color="cyan" dark>
          <v-card-text class="text-center">
            <div class="text-h5 font-weight-bold">{{ avgDuration }}m</div>
            <div class="caption">Avg Duration</div>
          </v-card-text>
        </v-card>
      </v-col>
      <v-col cols="12" sm="4" md="2">
        <v-card color="pink" dark>
          <v-card-text class="text-center">
            <div class="text-h5 font-weight-bold">{{ avgRevenuePerKm }}</div>
            <div class="caption">Revenue/KM</div>
          </v-card-text>
        </v-card>
      </v-col>
      <v-col cols="12" sm="4" md="2">
        <v-card :color="avgPerformance >= 70 ? 'success' : avgPerformance >= 50 ? 'warning' : 'error'" dark>
          <v-card-text class="text-center">
            <div class="text-h5 font-weight-bold">{{ avgPerformance }}</div>
            <div class="caption">Overall Score</div>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>

    <!-- Tabs -->
    <v-card v-if="data">
      <v-tabs v-model="activeTab" background-color="primary" dark>
        <v-tab href="#earnings">Pendapatan</v-tab>
        <v-tab href="#trips">Perjalanan</v-tab>
        <v-tab href="#safety">Keamanan</v-tab>
        <v-tab href="#efficiency">Efisiensi</v-tab>
        <v-tab href="#punctuality">Ketepatan</v-tab>
        <v-tab href="#leaderboard">Leaderboard</v-tab>
      </v-tabs>

      <v-tabs-items v-model="activeTab">
        <!-- Tab Earnings -->
        <v-tab-item value="earnings">
          <v-card flat>
            <v-card-text>
              <v-row>
                <v-col cols="12" md="8">
                  <h4 class="mb-3">Pendapatan Harian</h4>
                  <vue-apex-charts type="line" height="350" :options="earningsChartOptions" :series="earningsChartSeries" />
                </v-col>
                <v-col cols="12" md="4">
                  <h4 class="mb-3">Pendapatan per Rute</h4>
                  <vue-apex-charts type="donut" height="350" :options="routeDonutOptions" :series="routeDonutSeries" />
                </v-col>
              </v-row>
              <h4 class="mt-4 mb-3">Detail Pendapatan Driver</h4>
              <v-simple-table dense>
                <template v-slot:default>
                  <thead>
                    <tr>
                      <th>Driver</th>
                      <th class="text-right">Jumlah Trip</th>
                      <th class="text-right">Total Pendapatan</th>
                      <th class="text-right">Rata-rata per Trip</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="d in data.driver_earnings" :key="d.id">
                      <td>{{ d.name }}</td>
                      <td class="text-right">{{ d.trip_count }}</td>
                      <td class="text-right">{{ formatCurrency(d.total_earnings) }}</td>
                      <td class="text-right">{{ formatCurrency(d.trip_count > 0 ? d.total_earnings / d.trip_count : 0) }}</td>
                    </tr>
                    <tr v-if="data.driver_earnings.length === 0">
                      <td colspan="4" class="text-center grey--text">Tidak ada data</td>
                    </tr>
                  </tbody>
                </template>
              </v-simple-table>
            </v-card-text>
          </v-card>
        </v-tab-item>

        <!-- Tab Trips -->
        <v-tab-item value="trips">
          <v-card flat>
            <v-card-text>
              <h4 class="mb-3">Statistik Perjalanan Driver</h4>
              <v-simple-table dense>
                <template v-slot:default>
                  <thead>
                    <tr>
                      <th>Driver</th>
                      <th class="text-right">Total Trip</th>
                      <th class="text-right">Selesai</th>
                      <th class="text-right">Completion Rate</th>
                      <th class="text-right">Penumpang</th>
                      <th>Progress</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="d in data.driver_trips" :key="d.id">
                      <td>{{ d.name }}</td>
                      <td class="text-right">{{ d.total_trips }}</td>
                      <td class="text-right">{{ d.completed_trips }}</td>
                      <td class="text-right">{{ d.completion_rate }}%</td>
                      <td class="text-right">{{ d.total_passengers }}</td>
                      <td style="min-width: 150px;">
                        <v-progress-linear :value="d.completion_rate" :color="d.completion_rate >= 80 ? 'success' : d.completion_rate >= 50 ? 'warning' : 'error'" height="20" rounded>
                          <template v-slot:default>
                            <small class="white--text">{{ d.completion_rate }}%</small>
                          </template>
                        </v-progress-linear>
                      </td>
                    </tr>
                    <tr v-if="data.driver_trips.length === 0">
                      <td colspan="6" class="text-center grey--text">Tidak ada data</td>
                    </tr>
                  </tbody>
                </template>
              </v-simple-table>
            </v-card-text>
          </v-card>
        </v-tab-item>

        <!-- Tab Safety -->
        <v-tab-item value="safety">
          <v-card flat>
            <v-card-text>
              <v-row>
                <v-col cols="12" md="8">
                  <h4 class="mb-3">GPS Alerts per Driver</h4>
                  <vue-apex-charts type="bar" height="350" :options="safetyChartOptions" :series="safetyChartSeries" />
                </v-col>
                <v-col cols="12" md="4">
                  <h4 class="mb-3">Distribusi Alert</h4>
                  <vue-apex-charts type="pie" height="350" :options="alertPieOptions" :series="alertPieSeries" />
                </v-col>
              </v-row>
              <h4 class="mt-4 mb-3">Detail Keamanan Driver</h4>
              <v-simple-table dense>
                <template v-slot:default>
                  <thead>
                    <tr>
                      <th>Driver</th>
                      <th class="text-right">Speeding</th>
                      <th class="text-right">GPS Offline</th>
                      <th class="text-right">Out of Route</th>
                      <th class="text-right">Komplain</th>
                      <th class="text-right">Safety Score</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="d in data.driver_safety" :key="d.id">
                      <td>{{ d.name }}</td>
                      <td class="text-right">
                        <v-chip x-small :color="d.speeding > 0 ? 'error' : 'success'" dark>{{ d.speeding }}</v-chip>
                      </td>
                      <td class="text-right">
                        <v-chip x-small :color="d.offline > 0 ? 'warning' : 'success'" dark>{{ d.offline }}</v-chip>
                      </td>
                      <td class="text-right">
                        <v-chip x-small :color="d.out_of_route > 0 ? 'orange' : 'success'" dark>{{ d.out_of_route }}</v-chip>
                      </td>
                      <td class="text-right">
                        <v-chip x-small :color="d.complaints > 0 ? 'red' : 'success'" dark>{{ d.complaints }}</v-chip>
                      </td>
                      <td class="text-right">
                        <v-chip :color="d.safety_score >= 80 ? 'success' : d.safety_score >= 60 ? 'warning' : 'error'" dark small>
                          {{ d.safety_score }}
                        </v-chip>
                      </td>
                    </tr>
                    <tr v-if="data.driver_safety.length === 0">
                      <td colspan="6" class="text-center grey--text">Tidak ada data</td>
                    </tr>
                  </tbody>
                </template>
              </v-simple-table>
            </v-card-text>
          </v-card>
        </v-tab-item>

        <!-- Tab Efficiency -->
        <v-tab-item value="efficiency">
          <v-card flat>
            <v-card-text>
              <v-row>
                <v-col cols="12" md="6">
                  <h4 class="mb-3">Utilization Rate</h4>
                  <vue-apex-charts type="bar" height="300" :options="utilizationChartOptions" :series="utilizationChartSeries" />
                </v-col>
                <v-col cols="12" md="6">
                  <h4 class="mb-3">Revenue per KM</h4>
                  <vue-apex-charts type="bar" height="300" :options="revenuePerKmChartOptions" :series="revenuePerKmChartSeries" />
                </v-col>
              </v-row>
              <h4 class="mt-4 mb-3">Detail Efisiensi</h4>
              <v-simple-table dense>
                <template v-slot:default>
                  <thead>
                    <tr>
                      <th>Driver</th>
                      <th class="text-right">Utilization %</th>
                      <th class="text-right">Revenue/KM</th>
                      <th class="text-right">Fuel Score</th>
                      <th class="text-right">Total Penumpang</th>
                      <th class="text-right">Total Kapasitas</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="d in mergedEfficiency" :key="d.id">
                      <td>{{ d.name }}</td>
                      <td class="text-right">
                        <v-chip x-small :color="d.avg_utilization >= 70 ? 'success' : d.avg_utilization >= 40 ? 'warning' : 'error'" dark>
                          {{ d.avg_utilization }}%
                        </v-chip>
                      </td>
                      <td class="text-right">{{ formatCurrency(d.revenue_per_km) }}</td>
                      <td class="text-right">
                        <v-chip x-small :color="d.fuel_efficiency_score >= 70 ? 'success' : 'warning'" dark>
                          {{ d.fuel_efficiency_score }}
                        </v-chip>
                      </td>
                      <td class="text-right">{{ d.total_passengers }}</td>
                      <td class="text-right">{{ d.total_capacity }}</td>
                    </tr>
                    <tr v-if="mergedEfficiency.length === 0">
                      <td colspan="6" class="text-center grey--text">Tidak ada data</td>
                    </tr>
                  </tbody>
                </template>
              </v-simple-table>
            </v-card-text>
          </v-card>
        </v-tab-item>

        <!-- Tab Punctuality -->
        <v-tab-item value="punctuality">
          <v-card flat>
            <v-card-text>
              <v-row>
                <v-col cols="12" md="6">
                  <h4 class="mb-3">On-Time Performance</h4>
                  <vue-apex-charts type="bar" height="300" :options="onTimeChartOptions" :series="onTimeChartSeries" />
                </v-col>
                <v-col cols="12" md="6">
                  <h4 class="mb-3">Trip Frequency (per minggu)</h4>
                  <vue-apex-charts type="bar" height="300" :options="frequencyChartOptions" :series="frequencyChartSeries" />
                </v-col>
              </v-row>
              <h4 class="mt-4 mb-3">Detail Ketepatan</h4>
              <v-simple-table dense>
                <template v-slot:default>
                  <thead>
                    <tr>
                      <th>Driver</th>
                      <th class="text-right">On-Time %</th>
                      <th class="text-right">Avg Duration (menit)</th>
                      <th class="text-right">Total Durasi (jam)</th>
                      <th class="text-right">Trips/Minggu</th>
                      <th class="text-right">Trips/Hari</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="d in mergedPunctuality" :key="d.id">
                      <td>{{ d.name }}</td>
                      <td class="text-right">
                        <v-chip x-small :color="d.on_time_percentage >= 80 ? 'success' : d.on_time_percentage >= 50 ? 'warning' : 'error'" dark>
                          {{ d.on_time_percentage }}%
                        </v-chip>
                      </td>
                      <td class="text-right">{{ d.avg_duration_minutes }}</td>
                      <td class="text-right">{{ d.total_duration_hours }}</td>
                      <td class="text-right">{{ d.trips_per_week }}</td>
                      <td class="text-right">{{ d.trips_per_day }}</td>
                    </tr>
                    <tr v-if="mergedPunctuality.length === 0">
                      <td colspan="6" class="text-center grey--text">Tidak ada data</td>
                    </tr>
                  </tbody>
                </template>
              </v-simple-table>
            </v-card-text>
          </v-card>
        </v-tab-item>

        <!-- Tab Leaderboard -->
        <v-tab-item value="leaderboard">
          <v-card flat>
            <v-card-text>
              <v-row>
                <v-col cols="12" md="4">
                  <v-card outlined>
                    <v-card-title class="subtitle-1">
                      <v-icon left color="gold">mdi-trophy</v-icon>Top Pendapatan
                    </v-card-title>
                    <v-list dense>
                      <v-list-item v-for="(d, i) in topEarners" :key="d.id">
                        <v-list-item-avatar size="32" color="primary">
                          <span class="white--text text-body-2">{{ i + 1 }}</span>
                        </v-list-item-avatar>
                        <v-list-item-content>
                          <v-list-item-title>{{ d.name }}</v-list-item-title>
                          <v-list-item-subtitle>{{ d.trip_count }} trips</v-list-item-subtitle>
                        </v-list-item-content>
                        <v-list-item-action>
                          <span class="font-weight-bold success--text">{{ formatCurrency(d.total_earnings) }}</span>
                        </v-list-item-action>
                      </v-list-item>
                      <v-list-item v-if="topEarners.length === 0">
                        <v-list-item-content class="text-center grey--text">Tidak ada data</v-list-item-content>
                      </v-list-item>
                    </v-list>
                  </v-card>
                </v-col>
                <v-col cols="12" md="4">
                  <v-card outlined>
                    <v-card-title class="subtitle-1">
                      <v-icon left color="blue">mdi-check-decagram</v-icon>Top Completion Rate
                    </v-card-title>
                    <v-list dense>
                      <v-list-item v-for="(d, i) in topCompletion" :key="d.id">
                        <v-list-item-avatar size="32" color="info">
                          <span class="white--text text-body-2">{{ i + 1 }}</span>
                        </v-list-item-avatar>
                        <v-list-item-content>
                          <v-list-item-title>{{ d.name }}</v-list-item-title>
                          <v-list-item-subtitle>{{ d.completed_trips }}/{{ d.total_trips }} trips</v-list-item-subtitle>
                        </v-list-item-content>
                        <v-list-item-action>
                          <span class="font-weight-bold info--text">{{ d.completion_rate }}%</span>
                        </v-list-item-action>
                      </v-list-item>
                      <v-list-item v-if="topCompletion.length === 0">
                        <v-list-item-content class="text-center grey--text">Tidak ada data</v-list-item-content>
                      </v-list-item>
                    </v-list>
                  </v-card>
                </v-col>
                <v-col cols="12" md="4">
                  <v-card outlined>
                    <v-card-title class="subtitle-1">
                      <v-icon left color="green">mdi-shield-check</v-icon>Top Safety Score
                    </v-card-title>
                    <v-list dense>
                      <v-list-item v-for="(d, i) in topSafety" :key="d.id">
                        <v-list-item-avatar size="32" color="success">
                          <span class="white--text text-body-2">{{ i + 1 }}</span>
                        </v-list-item-avatar>
                        <v-list-item-content>
                          <v-list-item-title>{{ d.name }}</v-list-item-title>
                          <v-list-item-subtitle>{{ d.total_alerts }} alerts, {{ d.complaints }} complaints</v-list-item-subtitle>
                        </v-list-item-content>
                        <v-list-item-action>
                          <v-chip small :color="d.safety_score >= 80 ? 'success' : 'warning'" dark>{{ d.safety_score }}</v-chip>
                        </v-list-item-action>
                      </v-list-item>
                      <v-list-item v-if="topSafety.length === 0">
                        <v-list-item-content class="text-center grey--text">Tidak ada data</v-list-item-content>
                      </v-list-item>
                    </v-list>
                  </v-card>
                </v-col>
              </v-row>

              <v-row class="mt-4">
                <v-col cols="12" md="6">
                  <v-card outlined>
                    <v-card-title class="subtitle-1">
                      <v-icon left color="teal">mdi-clock-check</v-icon>Top On-Time
                    </v-card-title>
                    <v-list dense>
                      <v-list-item v-for="(d, i) in topOnTime" :key="d.id">
                        <v-list-item-avatar size="32" color="teal">
                          <span class="white--text text-body-2">{{ i + 1 }}</span>
                        </v-list-item-avatar>
                        <v-list-item-content>
                          <v-list-item-title>{{ d.name }}</v-list-item-title>
                          <v-list-item-subtitle>{{ d.on_time_trips }}/{{ d.total_trips_with_time }} trips tepat waktu</v-list-item-subtitle>
                        </v-list-item-content>
                        <v-list-item-action>
                          <span class="font-weight-bold teal--text">{{ d.on_time_percentage }}%</span>
                        </v-list-item-action>
                      </v-list-item>
                      <v-list-item v-if="topOnTime.length === 0">
                        <v-list-item-content class="text-center grey--text">Tidak ada data</v-list-item-content>
                      </v-list-item>
                    </v-list>
                  </v-card>
                </v-col>
                <v-col cols="12" md="6">
                  <v-card outlined>
                    <v-card-title class="subtitle-1">
                      <v-icon left color="purple">mdi-star</v-icon>Top Overall Score
                    </v-card-title>
                    <v-list dense>
                      <v-list-item v-for="(d, i) in topPerformance" :key="d.id">
                        <v-list-item-avatar size="32" color="purple">
                          <span class="white--text text-body-2">{{ i + 1 }}</span>
                        </v-list-item-avatar>
                        <v-list-item-content>
                          <v-list-item-title>{{ d.name }}</v-list-item-title>
                          <v-list-item-subtitle>Completion: {{ d.completion_rate }}% | Safety: {{ d.safety_score }}</v-list-item-subtitle>
                        </v-list-item-content>
                        <v-list-item-action>
                          <v-chip small :color="d.overall_score >= 70 ? 'success' : d.overall_score >= 50 ? 'warning' : 'error'" dark>
                            {{ d.overall_score }}
                          </v-chip>
                        </v-list-item-action>
                      </v-list-item>
                      <v-list-item v-if="topPerformance.length === 0">
                        <v-list-item-content class="text-center grey--text">Tidak ada data</v-list-item-content>
                      </v-list-item>
                    </v-list>
                  </v-card>
                </v-col>
              </v-row>
            </v-card-text>
          </v-card>
        </v-tab-item>
      </v-tabs-items>
    </v-card>

    <!-- Loading -->
    <div v-if="loading" class="text-center py-12">
      <v-progress-circular indeterminate color="primary" size="64" />
      <p class="mt-4 grey--text">Memuat data...</p>
    </div>

    <!-- Empty State -->
    <v-card v-if="!loading && !data" class="text-center py-12">
      <v-icon size="80" color="grey lighten-1">mdi-chart-bar</v-icon>
      <h3 class="mt-4 grey--text">Pilih tanggal lalu klik Filter untuk melihat laporan</h3>
    </v-card>
  </div>
</template>

<script>
import VueApexCharts from "vue-apexcharts";

export default {
  components: {
    VueApexCharts,
  },
  data() {
    return {
      loading: false,
      exporting: false,
      activeTab: "earnings",
      data: null,
      filters: {
        startDate: new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().split("T")[0],
        endDate: new Date().toISOString().split("T")[0],
      },
    };
  },
  computed: {
    totalDrivers() {
      return this.data ? this.data.driver_earnings.length : 0;
    },
    totalEarnings() {
      if (!this.data) return 0;
      return this.data.driver_earnings.reduce((sum, d) => sum + d.total_earnings, 0);
    },
    totalTrips() {
      if (!this.data) return 0;
      return this.data.driver_trips.reduce((sum, d) => sum + d.total_trips, 0);
    },
    avgSafetyScore() {
      if (!this.data || this.data.driver_safety.length === 0) return 0;
      const sum = this.data.driver_safety.reduce((s, d) => s + d.safety_score, 0);
      return Math.round(sum / this.data.driver_safety.length);
    },
    avgOnTime() {
      if (!this.data || this.data.driver_on_time.length === 0) return 0;
      const sum = this.data.driver_on_time.reduce((s, d) => s + d.on_time_percentage, 0);
      return Math.round(sum / this.data.driver_on_time.length);
    },
    avgUtilization() {
      if (!this.data || this.data.driver_utilization.length === 0) return 0;
      const sum = this.data.driver_utilization.reduce((s, d) => s + d.avg_utilization, 0);
      return Math.round(sum / this.data.driver_utilization.length);
    },
    avgTripsPerWeek() {
      if (!this.data || this.data.driver_trip_frequency.length === 0) return 0;
      const sum = this.data.driver_trip_frequency.reduce((s, d) => s + d.trips_per_week, 0);
      return (sum / this.data.driver_trip_frequency.length).toFixed(1);
    },
    avgDuration() {
      if (!this.data || this.data.driver_duration.length === 0) return 0;
      const validDurations = this.data.driver_duration.filter(d => d.avg_duration_minutes > 0);
      if (validDurations.length === 0) return 0;
      const sum = validDurations.reduce((s, d) => s + d.avg_duration_minutes, 0);
      return Math.round(sum / validDurations.length);
    },
    avgRevenuePerKm() {
      if (!this.data || this.data.driver_revenue_per_km.length === 0) return 0;
      const sum = this.data.driver_revenue_per_km.reduce((s, d) => s + d.revenue_per_km, 0);
      return this.formatCurrencyShort(sum / this.data.driver_revenue_per_km.length);
    },
    avgPerformance() {
      if (!this.data || this.data.driver_performance.length === 0) return 0;
      const sum = this.data.driver_performance.reduce((s, d) => s + d.overall_score, 0);
      return Math.round(sum / this.data.driver_performance.length);
    },
    mergedEfficiency() {
      if (!this.data) return [];
      return this.data.driver_utilization.map(d => {
        const rpk = this.data.driver_revenue_per_km.find(r => r.id === d.id) || {};
        const fuel = this.data.driver_fuel_efficiency.find(f => f.id === d.id) || {};
        return { ...d, revenue_per_km: rpk.revenue_per_km || 0, fuel_efficiency_score: fuel.fuel_efficiency_score || 0 };
      });
    },
    mergedPunctuality() {
      if (!this.data) return [];
      return this.data.driver_on_time.map(d => {
        const dur = this.data.driver_duration.find(r => r.id === d.id) || {};
        const freq = this.data.driver_trip_frequency.find(f => f.id === d.id) || {};
        return { ...d, avg_duration_minutes: dur.avg_duration_minutes || 0, total_duration_hours: dur.total_duration_hours || 0, trips_per_week: freq.trips_per_week || 0, trips_per_day: freq.trips_per_day || 0 };
      });
    },
    topEarners() {
      return this.data ? this.data.driver_earnings.slice(0, 10) : [];
    },
    topCompletion() {
      if (!this.data) return [];
      return [...this.data.driver_trips].filter(d => d.total_trips > 0).sort((a, b) => b.completion_rate - a.completion_rate).slice(0, 10);
    },
    topSafety() {
      return this.data ? [...this.data.driver_safety].sort((a, b) => b.safety_score - a.safety_score).slice(0, 10) : [];
    },
    topOnTime() {
      return this.data ? [...this.data.driver_on_time].filter(d => d.total_trips_with_time > 0).sort((a, b) => b.on_time_percentage - a.on_time_percentage).slice(0, 10) : [];
    },
    topPerformance() {
      return this.data ? [...this.data.driver_performance].sort((a, b) => b.overall_score - a.overall_score).slice(0, 10) : [];
    },
    earningsChartSeries() {
      if (!this.data) return [];
      const dates = Object.keys(this.data.daily_earnings).sort();
      const values = dates.map(d => this.data.daily_earnings[d]);
      return [{ name: "Pendapatan Driver", data: values }];
    },
    earningsChartOptions() {
      if (!this.data) return {};
      const dates = Object.keys(this.data.daily_earnings).sort();
      return {
        chart: { type: "area", toolbar: { show: false } },
        colors: ["#5A8DEE"],
        dataLabels: { enabled: false },
        stroke: { curve: "smooth", width: 2 },
        fill: { type: "gradient", gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.1 } },
        xaxis: { categories: dates.map(d => { const parts = d.split("-"); return parts[2] + "/" + parts[1]; }) },
        yaxis: { labels: { formatter: v => this.formatCurrencyShort(v) } },
        tooltip: { y: { formatter: v => this.formatCurrency(v) } },
        grid: { strokeDashArray: 12 },
      };
    },
    routeDonutSeries() {
      if (!this.data) return [];
      return this.data.route_breakdown.map(r => r.total_earnings);
    },
    routeDonutOptions() {
      if (!this.data) return {};
      return {
        chart: { type: "donut" },
        labels: this.data.route_breakdown.map(r => r.route),
        colors: ["#5A8DEE", "#FF5B5C", "#FFC542", "#1BC5BD", "#7B61FF", "#FF6B9D"],
        plotOptions: { pie: { donut: { labels: { show: true, total: { show: true, label: "Total", formatter: () => this.formatCurrency(this.totalEarnings) } } } } },
        legend: { position: "bottom" },
      };
    },
    safetyChartSeries() {
      if (!this.data) return [];
      const drivers = this.data.driver_safety.filter(d => d.total_alerts > 0).slice(0, 15);
      return [
        { name: "Speeding", data: drivers.map(d => d.speeding) },
        { name: "GPS Offline", data: drivers.map(d => d.offline) },
        { name: "Out of Route", data: drivers.map(d => d.out_of_route) },
      ];
    },
    safetyChartOptions() {
      if (!this.data) return {};
      const drivers = this.data.driver_safety.filter(d => d.total_alerts > 0).slice(0, 15);
      return {
        chart: { type: "bar", stacked: true, toolbar: { show: false } },
        colors: ["#FF5B5C", "#FFC542", "#FF6B9D"],
        plotOptions: { bar: { columnWidth: "50%" } },
        xaxis: { categories: drivers.map(d => d.name) },
        yaxis: { labels: { formatter: v => Math.round(v) } },
        legend: { position: "top" },
        grid: { strokeDashArray: 12 },
      };
    },
    alertPieSeries() {
      if (!this.data) return [];
      const totals = this.data.driver_safety.reduce((acc, d) => {
        acc.speeding += d.speeding;
        acc.offline += d.offline;
        acc.outOfRoute += d.out_of_route;
        return acc;
      }, { speeding: 0, offline: 0, outOfRoute: 0 });
      return [totals.speeding, totals.offline, totals.outOfRoute].filter(v => v > 0);
    },
    alertPieOptions() {
      if (!this.data) return {};
      const totals = this.data.driver_safety.reduce((acc, d) => {
        acc.speeding += d.speeding;
        acc.offline += d.offline;
        acc.outOfRoute += d.out_of_route;
        return acc;
      }, { speeding: 0, offline: 0, outOfRoute: 0 });
      const labels = [];
      if (totals.speeding > 0) labels.push("Speeding");
      if (totals.offline > 0) labels.push("GPS Offline");
      if (totals.outOfRoute > 0) labels.push("Out of Route");
      return {
        chart: { type: "pie" },
        labels: labels,
        colors: ["#FF5B5C", "#FFC542", "#FF6B9D"],
        legend: { position: "bottom" },
      };
    },
    utilizationChartSeries() {
      if (!this.data) return [];
      const drivers = this.data.driver_utilization.filter(d => d.trips_measured > 0).slice(0, 15);
      return [{ name: "Utilization %", data: drivers.map(d => d.avg_utilization) }];
    },
    utilizationChartOptions() {
      if (!this.data) return {};
      const drivers = this.data.driver_utilization.filter(d => d.trips_measured > 0).slice(0, 15);
      return {
        chart: { type: "bar", toolbar: { show: false } },
        colors: ["#1BC5BD"],
        plotOptions: { bar: { columnWidth: "50%" } },
        xaxis: { categories: drivers.map(d => d.name) },
        yaxis: { labels: { formatter: v => v + "%" }, max: 100 },
        legend: { show: false },
        grid: { strokeDashArray: 12 },
      };
    },
    revenuePerKmChartSeries() {
      if (!this.data) return [];
      const drivers = this.data.driver_revenue_per_km.filter(d => d.estimated_distance_km > 0).slice(0, 15);
      return [{ name: "Revenue/KM", data: drivers.map(d => d.revenue_per_km) }];
    },
    revenuePerKmChartOptions() {
      if (!this.data) return {};
      const drivers = this.data.driver_revenue_per_km.filter(d => d.estimated_distance_km > 0).slice(0, 15);
      return {
        chart: { type: "bar", toolbar: { show: false } },
        colors: ["#7B61FF"],
        plotOptions: { bar: { columnWidth: "50%" } },
        xaxis: { categories: drivers.map(d => d.name) },
        yaxis: { labels: { formatter: v => this.formatCurrencyShort(v) } },
        legend: { show: false },
        grid: { strokeDashArray: 12 },
      };
    },
    onTimeChartSeries() {
      if (!this.data) return [];
      const drivers = this.data.driver_on_time.filter(d => d.total_trips_with_time > 0).slice(0, 15);
      return [{ name: "On-Time %", data: drivers.map(d => d.on_time_percentage) }];
    },
    onTimeChartOptions() {
      if (!this.data) return {};
      const drivers = this.data.driver_on_time.filter(d => d.total_trips_with_time > 0).slice(0, 15);
      return {
        chart: { type: "bar", toolbar: { show: false } },
        colors: ["#00BFA5"],
        plotOptions: { bar: { columnWidth: "50%" } },
        xaxis: { categories: drivers.map(d => d.name) },
        yaxis: { labels: { formatter: v => v + "%" }, max: 100 },
        legend: { show: false },
        grid: { strokeDashArray: 12 },
      };
    },
    frequencyChartSeries() {
      if (!this.data) return [];
      const drivers = this.data.driver_trip_frequency.slice(0, 15);
      return [{ name: "Trips/Minggu", data: drivers.map(d => d.trips_per_week) }];
    },
    frequencyChartOptions() {
      if (!this.data) return {};
      const drivers = this.data.driver_trip_frequency.slice(0, 15);
      return {
        chart: { type: "bar", toolbar: { show: false } },
        colors: ["#FF6B9D"],
        plotOptions: { bar: { columnWidth: "50%" } },
        xaxis: { categories: drivers.map(d => d.name) },
        yaxis: { labels: { formatter: v => Math.round(v) } },
        legend: { show: false },
        grid: { strokeDashArray: 12 },
      };
    },
  },
  mounted() {
    this.fetchData();
  },
  methods: {
    async fetchData() {
      this.loading = true;
      try {
        const response = await axios.get("/reports/drivers", { params: this.filters });
        this.data = response.data;
      } catch (error) {
        console.error("Failed to fetch report:", error);
        this.$swal({
          icon: "error",
          title: "Gagal memuat laporan",
          text: error.response?.data?.message || "Terjadi kesalahan",
        });
      } finally {
        this.loading = false;
      }
    },
    resetFilters() {
      this.filters.startDate = new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().split("T")[0];
      this.filters.endDate = new Date().toISOString().split("T")[0];
      this.fetchData();
    },
    async exportCSV() {
      this.exporting = true;
      try {
        const response = await axios.get("/reports/drivers/export", {
          params: this.filters,
          responseType: "blob",
        });
        const url = window.URL.createObjectURL(new Blob([response.data]));
        const link = document.createElement("a");
        link.href = url;
        link.setAttribute("download", `driver-analytics-${this.filters.startDate}-to-${this.filters.endDate}.csv`);
        document.body.appendChild(link);
        link.click();
        link.remove();
      } catch (error) {
        console.error("Failed to export:", error);
      } finally {
        this.exporting = false;
      }
    },
    formatCurrency(value) {
      if (value === null || value === undefined) return "Rp 0";
      return new Intl.NumberFormat("id-ID", { style: "currency", currency: "IDR", minimumFractionDigits: 0 }).format(value);
    },
    formatCurrencyShort(value) {
      if (value >= 1000000) return (value / 1000000).toFixed(1) + "jt";
      if (value >= 1000) return (value / 1000).toFixed(0) + "rb";
      return Math.round(value);
    },
  },
};
</script>
