<?php

require 'vendor/autoload.php';

use Twig\Environment;
use Twig\Loader\FilesystemLoader;

class PitskillData
{
    private $ids = [11993,1422,9318,17425,15071,17011,14448,14233,15477,16957,15087,15713,17028,15095,17918,18028,17558];
    // private $ids = [17558];
    private $rawData = [];
    
    private $driverColumns = [
        'Image' => 'driverInfo.payload.sigma_user_data.discord_avatar',
        'Driver Name' => 'driverInfo.payload.tpc_driver_data.name',
        'Nickname' => 'driverInfo.payload.sigma_user_data.profile_data.nickname',
        'PitRep' => 'driverInfo.payload.tpc_driver_data.currentPitRep',
        'PitSkill' => 'driverInfo.payload.tpc_driver_data.currentPitSkill',
        'Daily Races' => 'driverInfo.payload.tpc_driver_data.daily_race_count',
        'Licence' => 'driverInfo.payload.tpc_driver_data.licence_class',
        'Last Race' => 'driverInfo.payload.tpc_stats.lastRaceDate',
        'Signup Date' => 'driverInfo.payload.sigma_user_data.signupDate',
        'VIP Level' => 'driverInfo.payload.sigma_user_data.vip_level',
    ];

    private $registrationColumns = [
        'Upcoming Event' => 'upcomingRegistrations.payload.0.event_name',
        'On Date' => 'upcomingRegistrations.payload.0.start_date',
        'Server' => 'upcomingRegistrations.payload.0.event_registrations.0.vehicle_registration.server.server_name',
        'Server SoF' => 'upcomingRegistrations.payload.0.event_registrations.0.vehicle_registration.server.server_strength_of_field',
        'Server Splits' => 'upcomingRegistrations.payload.0.event_registrations.0.vehicle_registration.server.server_split_index',
        'Car' => 'upcomingRegistrations.payload.0.event_registrations.0.car.name',
        'Track' => 'upcomingRegistrations.payload.0.track.track_name_long',
        'Registration' => 'upcomingRegistrations.payload.0.registration_count',
        'Broadcasted' => 'upcomingRegistrations.payload.0.broadcasters'
    ];

    private function fetchData()
    {
        foreach ($this->ids as $id) {
            $this->rawData[] = [
                'driver' => $this->getDataFromUrl("https://api.pitskill.io/api/pitskill/getdriverinfo?id=$id"),
                'registrations' => $this->getDataFromUrl("https://api.pitskill.io/api/events/upcomingRegistrations?id=$id"),
            ];
        }
    }

    private function transformData()
    {
        foreach ($this->rawData as $rawData) {
            $driverTransformedData = [];
            foreach ($this->columns as $column => $path) {
                $value = $this->getValue($rawData, $path);
                switch ($column) {
                    case 'Image':
                        $value = "<img src='" . htmlspecialchars($value) . "' alt='Image' />";

                    case 'Broadcasted':
                        $value = is_array($value) && count($value) > 0 ? 'Yes' : 'No';

                    case 'Licence':
                        $map = [
                            'A Class' => '<span class="bg-blue text-white">A Class</span>',
                            'B Class' => '<span class="bg-yellow text-white">A Class</span>',
                        ];
                        $value = array_key_exists($value, $map) ? $map[$value] : $value;

                    case 'Signup Date':
                    case 'On Date':
                        $value = $this->transformDate($value, 'd/m/y H:i');
                    
                    case 'Last Race':
                        $value = $this->transformDate($value, 'd/m/y');
                    
                    // case 'Server SoF':
                    //     $value = round($value, 1);
                        
                    default:
                }
                $driverTransformedData[$column] = $value;
            }
            $this->driversTransformedData[] = $driverTransformedData;
        }
    }

    private function transformDate($data, $format)
    {
        try {
            return Carbon\Carbon::parse($value)->format($format);
        } catch (\Throwable $th) {
            return 'N/A';
        }
    }


    private function getDataFromUrl($url)
    {
        return json_decode(file_get_contents($url), true);
    }

    private function getColumns()
    {
        return array_keys($this->columns);
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

    public function render()
    {
        $this->fetchData();
        $this->transformData();

        $loader = new FilesystemLoader(__DIR__ . '/views');
        $twig = new Environment($loader);

        echo $twig->render('pitskill.twig', [
            'columns' => $this->getColumns(),
            'driversData' => $this->driversTransformedData,
        ]);
    }
}

(new PitskillData())->render();

