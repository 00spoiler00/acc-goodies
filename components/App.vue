<!-- App.vue -->
<template>
  <v-app dark>
    <v-main>
      <app-header :countdownValue="countdownValue" :countdownTime="countdownTime"></app-header>
      <v-tabs v-model="tab">
        <v-tab>
          Registres a curses
        </v-tab>
        <v-tab>
          Ranking de pilots
        </v-tab>
      </v-tabs>
      <v-tabs-items v-model="tab" :touch="{}">
        <v-tab-item class="mx-4">
          <registration-table :registrations="filteredRegistrations"></registration-table>
        </v-tab-item>
        <v-tab-item class="mx-4">
          <driver-table :drivers="filteredDrivers"></driver-table>
        </v-tab-item>
      </v-tabs-items>
    </v-main>
  </v-app>
</template>

<script>
import AppHeader from './AppHeader.vue';
import RegistrationTable from './RegistrationTable.vue';
import DriverTable from './DriverTable.vue';

export default {
  components: {
    AppHeader,
    RegistrationTable,
    DriverTable
  },
  data() {
    return {
      tab: null,
      driversSearch: '',
      registrationsSearch: '',
      progress: 100,
      interval: null,
      drivers: [],
      registrations: [],
      countdownValue: 100,
      countdownTime: 10
    };
  },
  computed: {
    filteredDrivers() {
      return this.drivers.filter(driver => {
        const searchTerm = this.driversSearch.toLowerCase();
        return Object.values(driver).some(value =>
          value.toString().toLowerCase().includes(searchTerm)
        );
      });
    },
    filteredRegistrations() {
      return this.registrations.filter(registration => {
        const searchTerm = this.registrationsSearch.toLowerCase();
        return Object.values(registration).some(value =>
          value.toString().toLowerCase().includes(searchTerm)
        );
      });
    }
  },
  created() {
    this.fetchData();
    this.startCountdown();
  },
  methods: {
    fetchData() {
      fetch('./data.json')
        .then(response => response.json())
        .then(data => {
          this.drivers = data.drivers.data;
          this.registrations = data.registrations.data;
        })
        .catch(error => console.error('Error fetching data:', error));
    },
    startCountdown() {
      setInterval(() => {
        if (this.countdownTime > 0) {
          this.countdownTime--;
          this.countdownValue = (this.countdownTime / 10) * 100;
        } else {
          this.countdownTime = 10;
          this.fetchData();
        }
      }, 1000);
    }
  },
  beforeDestroy() {
    clearInterval(this.interval);
  }
};
</script>