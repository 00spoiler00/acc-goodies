<!-- DriverTable.vue -->
<template>
  <v-card flat>
    <v-card-title>
      <v-text-field v-model="search" label="Cerca pilots" append-icon="mdi-magnify" single-line hide-details></v-text-field>
    </v-card-title>
    <v-data-table :headers="headers" :items="filteredDrivers" class="elevation-4" disable-pagination :hide-default-footer="true">
      <template v-slot:item="props">
        <tr>
          <td>
            <v-avatar size="36" my-1>
              <v-img :src='props.item.Image' />
            </v-avatar>
          </td>
          <td>
            <a :href="'https://pitskill.io/driver-license/' + props.item['Driver Id']" target="_blank">
              {{ props.item['Driver Name'] }}
            </a>
          </td>
          <td>
            <div v-if="getLicence(props.item.PitRep, props.item.PitSkill) === 'Provisional'" class="h-8 w-28 font-bold rounded-xl border-2 bg-red-800 border-yellow-400 text-center py-1">Provisional</div>
            <div v-else-if="getLicence(props.item.PitRep, props.item.PitSkill) === 'Bronze'" class="h-8 w-28 font-bold rounded-xl border-2 bg-red-800 border-red-800 text-center py-1">Bronze</div>
            <div v-else-if="getLicence(props.item.PitRep, props.item.PitSkill) === 'Silver'" class="h-8 w-28 font-bold rounded-xl border-2 border-gray-400 bg-gray-400 text-center py-1 text-black">Silver</div>
            <div v-else-if="getLicence(props.item.PitRep, props.item.PitSkill) === 'Platinum'" class="h-8 w-28 font-bold rounded-xl border-2 border-white bg-white text-center py-1 text-black">Platinum</div>
            <div v-else-if="getLicence(props.item.PitRep, props.item.PitSkill) === 'Elite'" class="h-8 w-28 font-bold rounded-xl border-2 border-yellow-400 text-center py-1 text-yellow-400">Elite</div>
            <div v-else class="h-8 w-28 font-bold rounded-xl border-2 border-yellow-400 text-center py-1 text-yellow-400">? {{ props.item.PitRep }}/{{ props.item.PitSkill }}</div>
          </td>
          <td>
            <span>{{ props.item.PitRep }}</span>
            <v-btn icon @click="showPitRepDialog(props.item)">
              <v-icon>mdi-magnify-plus</v-icon>
            </v-btn>
            <v-dialog v-model="pitRepDialog" max-width="600">
              <v-card>
                <v-card-title>
                  PitRep History
                </v-card-title>
                <v-card-text>
                  <v-sparkline auto-draw height="300" width="400" smooth :value="selectedDriver.Stats.PitRep" color="blue"></v-sparkline>
                </v-card-text>
                <v-card-actions>
                  <v-btn text @click="pitRepDialog = false">Close</v-btn>
                </v-card-actions>
              </v-card>
            </v-dialog>
          </td>
          <td>
            <span>{{ props.item.PitSkill }}</span>
            <v-btn icon @click="showPitSkillDialog(props.item)">
              <v-icon>mdi-magnify-plus</v-icon>
            </v-btn>
            <v-dialog v-model="pitSkillDialog" max-width="600">
              <v-card>
                <v-card-title>
                  PitSkill History
                </v-card-title>
                <v-card-text>
                  <v-sparkline auto-draw height="300" width="400" smooth :value="selectedDriver.Stats.PitSkill" color="red"></v-sparkline>
                </v-card-text>
                <v-card-actions>
                  <v-btn text @click="pitSkillDialog = false">Close</v-btn>
                </v-card-actions>
              </v-card>
            </v-dialog>
          </td>
        </tr>
      </template>
    </v-data-table>
  </v-card>
</template>

<script>
export default {
  props: ['drivers'],
  data() {
    return {
      search: '',
      pitRepDialog: false,
      pitSkillDialog: false,
      selectedDriver: {}
    };
  },
  computed: {
    headers() {
      return [
        { text: '', align: 'start', sortable: false, value: 'Image' },
        { text: 'Pilot', value: 'Driver Name' },
        { text: 'Llicència', value: 'Licence' },
        { text: 'PitRep', value: 'PitRep' },
        { text: 'PitSkill', value: 'PitSkill' }
      ];
    },
    filteredDrivers() {
      return this.drivers.filter(driver => {
        const searchTerm = this.search.toLowerCase();
        return Object.values(driver).some(value =>
          value.toString().toLowerCase().includes(searchTerm)
        );
      });
    }
  },
  methods: {
    getLicence(pitRep, pitSkill) {

      if (pitRep <= 5) return 'Provisional';
      if (pitRep > 5 && pitSkill < 1900) return 'Bronze';
      if (pitRep > 10 && pitSkill > 1900) return 'Silver';
      if (pitRep > 15 && pitSkill >= 2750) return 'Platinum';
      if (pitRep > 20 && pitSkill >= 3500) return 'Elite';
    },
    showPitRepDialog(item) {
      this.selectedDriver = item;
      this.pitRepDialog = true;
    },
    showPitSkillDialog(item) {
      this.selectedDriver = item;
      this.pitSkillDialog = true;
    }
  }
};
</script>