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
        $this->ids = json_decode(file_get_contents('ids.json'));
        
        $this->loadStats();

        foreach ($this->ids as $id) {

            // Driver
            $driverJson = $this->getDataFromUrl("https://api.pitskill.io/api/pitskill/getdriverinfo?id=$id");

            // Temp debug
            // if($id == 18098) file_put_contents('json/exampleSourceDriver.json', json_encode($driver));
                
            // Create statistics
            $this->createStats($id, $driverJson);
            
            $driver['Driver Id'] = $id;
            foreach ($this->driverColumns as $column => $path) {
                if (!$path) continue;
                $driver[$column] = $this->transformValue($column, $this->getValue($driverJson, $path));
            }
            $driver['Stats'] = $this->stats[$id] ?? [];
            $this->drivers[] = $driver;
            
            // Registrations
            $registrationJson = $this->getDataFromUrl("https://api.pitskill.io/api/events/upcomingRegistrations?id=$id");
                
            // Temp debug
            // if($id == 1422) file_put_contents('json/exampleSourceRegistrations.json', json_encode($registrationJson));

            if (array_key_exists('payload', $registrationJson) && $registrationJson['payload'] !== null) {
                foreach ($registrationJson['payload'] as $eventIndex => $event) {
                    $registration['Driver'] = $driver['Driver Name'];
                    foreach ($this->registrationColumns as $column => $path) {
                        if (!$path) continue;
                        $path = 'payload.' . $eventIndex . '.' . $path;
                        $registration[$column] = $this->transformValue($column, $this->getValue($registrationJson, $path));
                    }
                    $this->registrations[] = $registration;
                }
            }
        }

        // Sort drivers
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

    private function sendRegistrationNotifications()
    {
        // Get the notified registrations data
        $notifications = json_decode(file_get_contents('notifications.json'), true);
        $notifications = collect($notifications);

        // For each current registrations
        foreach ($this->registrations as $registration) {
            
            // Check if exists in notifieds
            $exists = $notifications->contains(function($notification) use ($registration){
                return 
                $notification['Driver'] == $registration['Driver']
                &&
                $notification['Enroll Link'] == $registration['Enroll Link']
                ;
            }); 
            
            // If it does not exist in notifieds
            if(!$exists){
                // Notify 
                $sof = intval($registration['Server SoF']);
                $message = "**{$registration['Driver']}** s'ha apuntat a [{$registration['Upcoming Event']}](https://pitskill.io/event/{$registration['Enroll Link']}) *@{$registration['On Date']}* ({$sof} SoF, {$registration['Registration']} pilots)";
                $this->sendDiscordMessage($message);
                // And add to the notifieds
                $notifications[] = [
                    'Driver' => $registration['Driver'],
                    'Enroll Link' => $registration['Enroll Link'],
                ];    
            }
        }

          // Purge the old notifications
          $notifications = $notifications->slice(-100);
    
          // Save the notifications
          file_put_contents('notifications.json', json_encode($notifications->toArray()));
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

        if ($lastPitRep['value'] !== $currentPitRep) {
            $this->stats[$id]['PitRep'][] = [
                'date' => Carbon::now()->timestamp,
                'value' => $currentPitRep,
            ];
        }
        
        if ($lastPitSkill['value'] !== $currentPitSkill) {
            $this->stats[$id]['PitSkill'][] = [
                'date' => Carbon::now()->timestamp,
                'value' => $currentPitSkill,
            ];
        }

        $this->saveStats();
    }

    private function  calculateChanges() {

        $this->loadStats();

        $pitRepChanges = [];
        $pitSkillChanges = [];
    
        foreach ($this->stats as $id => $values) {
            if (count($values['PitRep']) > 1) {
                $lastRepChange = end($values['PitRep'])['value'] - prev($values['PitRep'])['value'];
                $pitRepChanges[$id] = $lastRepChange;
            }
    
            if (count($values['PitSkill']) > 1) {
                $lastSkillChange = end($values['PitSkill'])['value'] - prev($values['PitSkill'])['value'];
                if($lastSkillChange > 1000){
                    $promotions[] = $id;
                }else{
                    $pitSkillChanges[$id] = $lastSkillChange;
                }
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
        
        $this->changes = [
            'PitRepIncreases' =>  $largestRepIncreases,
            'PitRepDecreases' => $largestRepDecreases,
            'PitSkillIncreases' => $largestSkillIncreases,
            'PitSkillDecreases' => $largestSkillDecreases,
            'Promotions' => array_unique($promotions),
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
        file_put_contents('stats.json', json_encode($this->stats));
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

    private function sendDiscordMessage(string $message) {
        // $webhookUrl = 'https://discord.com/api/webhooks/1266792058228834315/eSDfbXpG-c2GHxcLFyEVlf-kDKr67dKghu5fLRyOB5C9JniAW-pKHPsA3tP59f2K075c';
        $webhookUrl = 'https://discord.com/api/webhooks/1266833272697262112/SgaD33o4eRmwmWRa0xG3fChIRgnK4_Y-Jz4hhml0jArIZSlGFOVlIRZfvAwkS5EvwxdG';
        $data = json_encode(["content" => $message]);        
        $ch = curl_init($webhookUrl);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        return $response;
    }

    public function run() : void
    {
        $this->fetchData();
        $this->sendRegistrationNotifications();
        $this->calculateChanges();

        $data = [
            'last_update' => Carbon::now(),
            'drivers' => [
                'columns' => array_keys($this->driverColumns),
                'data' => $this->drivers,
            ],
            'registrations' => [
                'columns' => array_keys($this->registrationColumns),
                'data' => $this->registrations,
            ],
            'changes' => $this->changes,
            'lastUpdate' => $this->transformDate(Carbon::now(), 'd/m/y H:i'),
        ];

        file_put_contents('data.json', json_encode($data));
    }
}

try {
    print "Updating data!".PHP_EOL;
    (new PitskillDataFetcher())->run();
    print "Updated data!".PHP_EOL;
} catch (\Throwable $th) {
    print_r($th->getMessage());
}
