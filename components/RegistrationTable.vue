<!-- RegistrationTable.vue -->
<template>
    <v-card flat>
        <v-card-title>
            <v-text-field v-model="search" label="Cerca registres" append-icon="mdi-magnify" single-line hide-details></v-text-field>
        </v-card-title>
        <v-data-table :headers="headers" :items="filteredRegistrations" class="elevation-4 mt-5" disable-pagination :hide-default-footer="true" dense>
            <template v-slot:item="props">
                <tr class="whitespace-nowrap">
                    <td>
                        <v-progress-circular
                            :value="getInterest(props.item['Upcoming Event']) * (100 / 10)"
                            :rotate="0"
                            :size="32"
                            :width="4"
                            color="primary">
                            {{ getInterest(props.item['Upcoming Event']) }}
                        </v-progress-circular>
                    </td>
                    <td>{{ props.item.Driver }}</td>
                    <td v-html="timeUntil(props.item['On Date'])"></td>
                    <td>
                        <v-tooltip bottom>
                            <template v-slot:activator="{ on, attrs }">
                                <span v-bind="attrs" v-on="on">{{ props.item['Upcoming Event'] }}</span>
                            </template>
                            <span>{{ props.item.Server }}</span>
                        </v-tooltip>
                    </td>
                    <td>
                        <v-btn :href="'https://pitskill.io/event/' + props.item['Enroll Link']" target="_blank" icon>
                            <v-icon>mdi-open-in-new</v-icon>
                        </v-btn>
                    </td>
                    <td>{{ Math.round(parseFloat(props.item['Server SoF']) * 10) / 10 }}</td>
                    <td>{{ props.item.Car }}</td>
                    <td>
                        <span>{{ props.item.Track }}</span>
                        <v-btn icon @click="showTrackDialog(props.item)">
                            <v-icon>mdi-magnify-plus</v-icon>
                        </v-btn>
                        <v-dialog v-model="trackDialog" max-width="600">
                            <v-card>
                                <v-card-title>
                                    Track Image
                                </v-card-title>
                                <v-card-text>
                                    <v-img :src="'https://cdn.pitskill.io/public/TrackPhoto-' + selectedTrack.CircuitImage" max-width="600"></v-img>
                                </v-card-text>
                                <v-card-actions>
                                    <v-btn text @click="trackDialog = false">Close</v-btn>
                                </v-card-actions>
                            </v-card>
                        </v-dialog>
                    </td>
                    <td>{{ props.item.Registration }}</td>
                    <td v-html="props.item.Broadcasted"></td>
                    <td>{{ props.item['Server Splits'] }}</td>
                </tr>
            </template>
        </v-data-table>
    </v-card>
</template>

<script>
export default {
    props: ['registrations'],
    data() {
        return {
            search: '',
            trackDialog: false,
            selectedTrack: {}
        };
    },
    computed: {
        headers() {
            return [
                { text: '#', value: '', align: 'center' },
                { text: 'Pilot', value: 'Driver' },
                { text: 'Data', value: 'On Date' },
                { text: 'Esdevenimment', value: 'Upcoming Event' },
                { text: 'Inscripció', value: 'Enroll Link', class: 'whitespace-nowrap', align: 'center' },
                { text: 'SoF', value: 'Server SoF' },
                { text: 'Cotxe', value: 'Car' },
                { text: 'Circuit', value: 'Track' },
                { text: 'Inscrits', value: 'Registration' },
                { text: 'Retransmissió', value: 'Broadcasted' },
                { text: 'Splits', value: 'Server Splits' }
            ];
        },
        filteredRegistrations() {
            return this.registrations.filter(registration => {
                const searchTerm = this.search.toLowerCase();
                return Object.values(registration).some(value =>
                    value.toString().toLowerCase().includes(searchTerm)
                );
            });
        }
    },
    methods: {
        getInterest(upcomingEvent) {
            const eventCount = this.registrations.filter(reg => reg['Upcoming Event'] === upcomingEvent).length;
            return Math.min(10, eventCount);
        },
        timeUntil(dateString) {
            return dateString + ' <b>(' + moment(dateString, "DD/MM/YY HH:mm").fromNow() + ')</b>';
        },
        showTrackDialog(item) {
            this.selectedTrack = item;
            this.trackDialog = true;
        }
    }
};
</script>