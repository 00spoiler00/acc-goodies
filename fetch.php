<?php

require 'vendor/autoload.php';

use Carbon\Carbon;

class PitskillDataFetcher
{
    private $ids = [11993, 1422, 9318, 17425, 15071, 17011, 14448, 14233, 15477, 16957, 15087, 15713, 17028, 15095, 17918, 18028, 17558, 18098, 15484];
    private $drivers = [];
    private $registrations = [];
    
    private $driverColumns = [
        'Image' => 'payload.sigma_user_data.discord_avatar',
        'Driver Name' => 'payload.tpc_driver_data.name',
        'Nickname' => 'payload.sigma_user_data.profile_data.nickname',
        'Licence' => 'payload.tpc_driver_data.licence_class',
        'PitRep' => 'payload.tpc_driver_data.currentPitRep',
        'PitSkill' => 'payload.tpc_driver_data.currentPitSkill',
        'Daily Races' => 'payload.tpc_driver_data.daily_race_count',
        'Last Race' => 'payload.tpc_stats.lastRaceDate',
        // 'VIP Level' => 'payload.sigma_user_data.vip_level',
        // 'Signup Date' => 'payload.sigma_user_data.signupDate',
    ];
    
    private $registrationColumns = [
        'Driver' => null,
        'On Date' => 'start_date',
        'Upcoming Event' => 'event_name',
        'Server' => 'event_registrations.0.vehicle_registration.server.server_name',
        'Server SoF' => 'event_registrations.0.vehicle_registration.server.server_strength_of_field',
        'Server Splits' => 'event_registrations.0.vehicle_registration.server.server_split_index',
        'Car' => 'event_registrations.0.car.name',
        'Track' => 'track.track_name_long',
        'Registration' => 'registration_count',
        'Broadcasted' => 'broadcasters'
    ];

    private function fetchData()
    {       
        foreach ($this->ids as $id) {
            // Driver
            $data = $this->getDataFromUrl("https://api.pitskill.io/api/pitskill/getdriverinfo?id=$id");
            foreach ($this->driverColumns as $column => $path) {
                $driver[$column] = $this->transformValue($column, $this->getValue($data, $path));
            }
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

    private function sortData()
    {
        usort($this->drivers, function($a, $b) {
            if ($a['PitSkill'] == $b['PitSkill']) {
                return $a['PitRep'] <=> $b['PitRep'];
            }
            return $b['PitSkill'] <=> $a['PitSkill'];
        });
        
        // Sort Registrations by 'OnDate'
        usort($this->registrations, function($a, $b) {
            $dateA = Carbon::createFromFormat('d/m/y H:i', $a['On Date']);
            $dateB = Carbon::createFromFormat('d/m/y H:i', $b['On Date']);
            return $dateA <=> $dateB;
        });

    }

    private function transformValue(string $column, string|array $value): string
    {
        switch ($column) {
            case 'Image':
                return "<img src='" . htmlspecialchars($value) . "' alt='Image' class='w-16 h-16 object-cover' />";
            case 'Broadcasted':
                $out = [];
                foreach ($value as $broadcast) {
                    $out[] = "<a href='" . $broadcast['broadcast_url'] . "'>" . $broadcast['broadcast_name'] . "</a>";
                }
                return implode("<br>", $out);
                case 'Licence':
                    $map = [
                        'S Class' => '<span class="bg-green-500 text-white rounded-full px-3 py-1">S Class</span>',
                        'A Class' => '<span class="bg-red-500 text-white rounded-full px-3 py-1">A Class</span>',
                        'B Class' => '<span class="bg-blue-500 text-white rounded-full px-3 py-1">B Class</span>',
                        'C Class' => '<span class="bg-purple-500 text-white rounded-full px-3 py-1">C Class</span>',
                        'Rookie' => '<span class="bg-orange-500 text-white rounded-full px-3 py-1">Rookie</span>',
                    ];
                    return array_key_exists($value, $map) ? $map[$value] : $value;
            case 'Signup Date':
            case 'On Date':
                return $this->transformDate($value, 'd/m/y H:i');
            case 'Last Race':
                return $this->transformDate($value, 'd/m/y');
            case 'Server SoF':
                return intval($value);
            default:
                return $value;
        }
    }

    private function transformDate($data, $format)
    {
        try {
            return Carbon::parse($data)
                         ->setTimezone('Europe/Madrid')
                         ->format($format);
        } catch (\Throwable $th) {
            return 'N/A';
        }
    }
    
    private function getDataFromUrl($url)
    {
        return json_decode(file_get_contents($url), true);
    }

    private function getValue($data, $path)
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

    public function saveData()
    {
        $this->fetchData();
        $this->sortData();

        $data = [
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

(new PitskillDataFetcher())->saveData();

