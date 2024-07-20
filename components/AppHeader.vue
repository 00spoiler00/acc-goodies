<!-- AppHeader.vue -->
<template>
    <v-app-bar app class="elevation-4">
        <v-toolbar-title>
            <div class="flex items-center justify-center">
                <a href="https://www.twitch.tv/a_tot_drap">
                    <v-avatar size="36" my-1>
                        <v-img src='./images/icons/android-chrome-192x192.png' />
                    </v-avatar>
                </a>
                <span class="ml-4 font-bold">
                    PitSkill A Tot Drap
                </span>
            </div>
        </v-toolbar-title>
        <v-spacer></v-spacer>
        <div class="mr-4 hidden sm:block">
            <a href="https://www.pitskill.io">
                <img src='./images/icons/pitskill.png' style="height: 16px" />
            </a>
        </div>
        <v-progress-circular
            :rotate="-90"
            :size="40"
            :width="4"
            :value="countdownValue"
            color="primary">
            {{ countdownTime }}
        </v-progress-circular>
    </v-app-bar>
</template>

<script>
export default {
    props: ['countdownValue', 'countdownTime']
};
</script>