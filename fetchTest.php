<?php

require 'vendor/autoload.php';

use Carbon\Carbon;

class PitskillDataFetcher
{
    private $ids = [];
    private $drivers = [];
    private $registrations = [];
    private $stats = [];
    private $changes = [];

    private $driverColumns = [
        'Driver Id' => null,
        'Image' => 'payload.sigma_user_data.discord_avatar',
        'Driver Name' => 'payload.tpc_driver_data.name',
        'Nickname' => 'payload.sigma_user_data.profile_data.nickname',
        'Licence' => 'payload.tpc_driver_data.licence_class',
        'PitRep' => 'payload.tpc_driver_data.currentPitRep',
        'PitSkill' => 'payload.tpc_driver_data.currentPitSkill',
        // 'Last Race Date' => 'payload.tpc_driver_data.stats.lastRaceDate',
        // 'Daily Races' => 'payload.tpc_driver_data.daily_race_count',
        // 'VIP Level' => 'payload.sigma_user_data.vip_level',
        // 'Signup Date' => 'payload.sigma_user_data.signupDate',
    ];

    private $registrationColumns = [
        'Driver' => null,
        'On Date' => 'start_date',
        'Upcoming Event' => 'event_name',
        'Enroll Link' => 'event_id',
        'Server' => 'event_registrations.0.vehicle_registration.server.server_name',
        'Server SoF' => 'event_registrations.0.vehicle_registration.server.server_strength_of_field',
        'Server Splits' => 'event_registrations.0.vehicle_registration.server.server_split_index',
        'Car' => 'event_registrations.0.car.name',
        'Track' => 'track.track_name_long',
        'Circuit Image' => 'track.thumbnail',
        'Registration' => 'registration_count',
        'Broadcasted' => 'broadcasters'
    ];

    private function fetchData() : void
    {
        // $this->ids = json_decode(file_get_contents('ids.json'));
        $this->ids = [17789,11993,1422];

        $this->loadStats();

        return;

        foreach ($this->ids as $id) {

            // Driver
            $data = $this->getDataFromUrl("https://api.pitskill.io/api/pitskill/getdriverinfo?id=$id");

            // Temp debug
            // if($id == 1422) {
                //     file_put_contents('json/exampleSourceDriver.json', json_encode($data));
                // }
                
                // Create statistics
                $this->createStats($id, $data);
                
                $driver['Driver Id'] = $id;
                foreach ($this->driverColumns as $column => $path) {
                    if (!$path) continue;
                    $driver[$column] = $this->transformValue($column, $this->getValue($data, $path));
                }
                $driver['Stats'] = $this->stats[$id] ?? [];
                $this->drivers[] = $driver;
                
                // Registrations
                $data = $this->getDataFromUrl("https://api.pitskill.io/api/events/upcomingRegistrations?id=$id");
                
            // Temp debug
            // if($id == 1422) {
            //     file_put_contents('json/exampleSourceRegistrations.json', json_encode($data));
            // }

            if (array_key_exists('payload', $data) && $data['payload'] !== null) {
                foreach ($data['payload'] as $eventIndex => $event) {
                    $registration['Driver'] = $driver['Driver Name'];
                    foreach ($this->registrationColumns as $column => $path) {
                        if (!$path) continue;
                        $path = 'payload.' . $eventIndex . '.' . $path;
                        $registration[$column] = $this->transformValue($column, $this->getValue($data, $path));
                    }
                    $this->registrations[] = $registration;
                }
            }
        }
    }

    private function createStats(int $id, array $data) : void
    {
        $currentPitRep = $this->getValue($data, 'payload.tpc_driver_data.currentPitRep');
        $currentPitSkill = $this->getValue($data, 'payload.tpc_driver_data.currentPitSkill');

        if (!isset($this->stats[$id])) {
            $this->stats[$id] = ['PitRep' => [], 'PitSkill' => []];
        }

        $lastPitRep = end($this->stats[$id]['PitRep']);
        $lastPitSkill = end($this->stats[$id]['PitSkill']);

        if ($lastPitRep !== $currentPitRep) {
            $this->stats[$id]['PitRep'][time()] = $currentPitRep;
        }

        if ($lastPitSkill !== $currentPitSkill) {
            $this->stats[$id]['PitSkill'][time()] = $currentPitSkill;
        }

        // $this->saveStats();
    }

    private function  calculateChanges() {
        $this->loadStats();

        $pitRepChanges = [];
        $pitSkillChanges = [];
        
        foreach ($this->stats as $id => $values) {

            if (count($values['PitRep']) > 1) {
                $lastRepChange = end($values['PitRep']) - prev($values['PitRep']);
                $pitRepChanges[$id] = $lastRepChange;
            }
    
            if (count($values['PitSkill']) > 1) {
                $lastSkillChange = end($values['PitSkill']) - prev($values['PitSkill']);
                $pitSkillChanges[$id] = $lastSkillChange;
            }
        }

        
        asort($pitRepChanges);
        asort($pitSkillChanges);
        
        $largestRepIncreases = array_slice($pitRepChanges, -3, 3, true);
        $largestRepDecreases = array_slice($pitRepChanges, 0, 3, true);
        $largestSkillIncreases = array_slice($pitSkillChanges, -3, 3, true);
        $largestSkillDecreases = array_slice($pitSkillChanges, 0, 3, true);
        
        arsort($largestRepIncreases);
        arsort($largestSkillIncreases);

        dd($pitRepChanges, $pitSkillChanges, $largestRepIncreases, $largestSkillIncreases, $largestRepDecreases, $largestSkillDecreases);
        
        $this->changes = [
            'PitRepIncreases' =>  $largestRepIncreases,
            'PitRepDecreases' => $largestRepDecreases,
            'PitSkillIncreases' => $largestSkillIncreases,
            'PitSkillDecreases' => $largestSkillDecreases
        ];
    }

    private function loadStats() : void
    {
        if (file_exists('stats.json')) {
            $this->stats = json_decode(file_get_contents('stats.json'), true);
        }
    }

    private function saveStats() : void
    {
        // file_put_contents('stats.json', json_encode($this->stats));
    }

    private function sortData() : void
    {
        usort($this->drivers, function($a, $b) {
            if ($a['PitSkill'] == $b['PitSkill']) {
                return $a['PitRep'] <=> $b['PitRep'];
            }
            return $b['PitSkill'] <=> $a['PitSkill'];
        });
        
        // Sort Registrations by 'On Date'
        usort($this->registrations, function($a, $b) {
            $dateA = Carbon::createFromFormat('d/m/y H:i', $a['On Date']);
            $dateB = Carbon::createFromFormat('d/m/y H:i', $b['On Date']);
            return $dateA <=> $dateB;
        });
    }

    // TODO: Move this to frontend parsing and flow control
    private function transformValue(string $column, string|array $value): string
    {
        switch ($column) {
                
            case 'Driver Name':
                // Convert the input string to title case
                $name = mb_convert_case($value, MB_CASE_TITLE, "UTF-8");
                
                // Split the name into parts
                $nameParts = explode(' ', $name);
                
                // Get the first initial and the surname
                $initial = mb_substr($nameParts[0], 0, 1) . '.';
                $surname = end($nameParts);
                
                // Return the formatted name
                return $initial . $surname;

            case 'Broadcasted':
                $out = [];
                foreach ($value as $broadcast) {
                    $out[] = "<a href='" . $broadcast['broadcast_url'] . "'>".$broadcast['broadcast_name']."</a>";
                }
                return implode("<br>", $out);
            
            case 'Signup Date':
            case 'On Date':
                return $this->transformDate($value, 'd/m/y H:i');

            default:
                return $value;
        }
    }

    private function transformDate(string $data, string $format)
    {
        try {
            return Carbon::parse($data)
                         ->setTimezone('Europe/Madrid')
                         ->format($format);
        } catch (\Throwable $th) {
            return 'N/A';
        }
    }
    
    private function getDataFromUrl(string $url) : array
    {
        return json_decode(file_get_contents($url), true);
    }

    private function getValue(array $data, string $path)
    {
        $keys = explode('.', $path);
        foreach ($keys as $key) {
            if (!isset($data[$key])) {
                return 'N/A';
            }
            $data = $data[$key];
        }
        return $data;
    }

    public function saveData() : void
    {
        // $this->fetchData();
        $this->calculateChanges();
        // $this->sortData();

        // $data = [
        //     'last_update' => Carbon::now(),
        //     'drivers' => [
        //         'columns' => array_keys($this->driverColumns),
        //         'data' => $this->drivers,
        //     ],
        //     'registrations' => [
        //         'columns' => array_keys($this->registrationColumns),
        //         'data' => $this->registrations,
        //     ],
        //     'changes' => $this->changes,
        // ];

        // file_put_contents('data.json', json_encode($data));
    }
}

try {
    print "Updating data!".PHP_EOL;
    (new PitskillDataFetcher())->saveData();
    print "Updated data!".PHP_EOL;
} catch (\Throwable $th) {
    print_r($th->getMessage());
}
