<?php

require 'vendor/autoload.php';

use Carbon\Carbon;

class PitskillDataFetcher
{
    private $ids = [11993, 1422, 9318, 17425, 15071, 17011, 14448, 14233, 15477, 16957, 15087, 15713, 17028, 15095, 17918, 18028, 17558, 18098, 15484];
    private $drivers = [];
    private $registrations = [];
    private $stats = [];

    private $driverColumns = [
        'Driver Id' => null,
        'Image' => 'payload.sigma_user_data.discord_avatar',
        'Driver Name' => 'payload.tpc_driver_data.name',
        'Nickname' => 'payload.sigma_user_data.profile_data.nickname',
        'Licence' => 'payload.tpc_driver_data.licence_class',
        'PitRep' => 'payload.tpc_driver_data.currentPitRep',
        'PitSkill' => 'payload.tpc_driver_data.currentPitSkill',
        'Daily Races' => 'payload.tpc_driver_data.daily_race_count',
        'Last Activity' => 'payload.sigma_user_data.updated_at',
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
        $this->loadStats();

        foreach ($this->ids as $id) {

            // Driver
            $data = $this->getDataFromUrl("https://api.pitskill.io/api/pitskill/getdriverinfo?id=$id");

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
            $this->stats[$id]['PitRep'][] = $currentPitRep;
        }

        if ($lastPitSkill !== $currentPitSkill) {
            $this->stats[$id]['PitSkill'][] = $currentPitSkill;
        }

        $this->saveStats();
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
                
            case 'Circuit Image':
                return "https://cdn.pitskill.io/public/TrackPhoto-" . $value;
        
            case 'Enroll Link':
                return "https://pitskill.io/event/".$value;
            
            case 'Broadcasted':
                $out = [];
                foreach ($value as $broadcast) {
                    $out[] = "<a href='" . $broadcast['broadcast_url'] . "'>" . $broadcast['broadcast_name'] . "</a>";
                }
                return implode("<br>", $out);
            
            case 'Signup Date':
            case 'On Date':
            case 'Last Activity':
                return $this->transformDate($value, 'd/m/y H:i');
            case 'Last Race':
                return $this->transformDate($value, 'd/m/y');
            case 'Server SoF':
                return intval($value);
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
        $this->fetchData();
        $this->sortData();

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
        ];

        file_put_contents('data.json', json_encode($data));
    }
}

try {
    print "Updating data!".PHP_EOL;
    (new PitskillDataFetcher())->saveData();
    print "Updated data!".PHP_EOL;
} catch (\Throwable $th) {
    print_r($th->getMessage());
}
