<?php

require 'vendor/autoload.php';

use Twig\Environment;
use Twig\Loader\FilesystemLoader;

class PitskillData
{
    private $ids = [11993,1422,9318,17425,15071,17011,14448,14233,15477,16957,15087,15713,17028,15095,17918,18028,17558];
    // private $ids = [17558];
    
    private $driversData = [];
    private $driverColumns = [
        'Image' => 'payload.sigma_user_data.discord_avatar',
        'Driver Name' => 'payload.tpc_driver_data.name',
        'Nickname' => 'payload.sigma_user_data.profile_data.nickname',
        'PitRep' => 'payload.tpc_driver_data.currentPitRep',
        'PitSkill' => 'payload.tpc_driver_data.currentPitSkill',
        'Daily Races' => 'payload.tpc_driver_data.daily_race_count',
        'Licence' => 'payload.tpc_driver_data.licence_class',
        'Last Race' => 'payload.tpc_stats.lastRaceDate',
        'Signup Date' => 'payload.sigma_user_data.signupDate',
        'VIP Level' => 'payload.sigma_user_data.vip_level',
    ];
    
    private $registrationsData = [];
    private $registrationColumns = [
        'Driver' => null,
        'Upcoming Event' => 'event_name',
        'On Date' => 'start_date',
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
            foreach($this->driverColumns as $column => $path){
                $driver[$column] = $this->transformValue($column, $this->getValue($data, $path));
            }
            $this->drivers[] = $driver;


            // Registrations
            $data = $this->getDataFromUrl("https://api.pitskill.io/api/events/upcomingRegistrations?id=$id");
            if(array_key_exists('payload', $data) && $data['payload'] !== null){
                
                foreach($data['payload'] as $eventIndex => $event){
                    $registration['Driver'] = $driver['Driver Name'];
                    foreach($this->registrationColumns as $column => $path){
                        if(!$path) continue;
                        $path = 'payload.'.$eventIndex.'.'.$path;
                        try {
                            $registration[$column] = $this->transformValue($column, $this->getValue($data, $path));
                        } catch (\Throwable $th) {
                            dd($th, $this->getValue($data, $path));
                        }
                    }
                    $this->registrations[] = $registration;
                }
            }
        }
    }

    private function transformValue(string $column, string|array $value): string
    {
        switch ($column) {
            case 'Image':
                return "<img src='" . htmlspecialchars($value) . "' alt='Image' />";

            case 'Broadcasted':
                $out = [];
                foreach ($value as $broadcast) {
                    $out[] = "<a href='".$value['broadcast_url']."'>".$value['broadcast_name']."</a>";
                }
                return implode("<br>", $out);

            case 'Broadcasted':
                return is_array($value) && count($value) > 0 ? 'Yes' : 'No';

            case 'Licence':
                $map = [
                    'A Class' => '<span class="bg-blue text-white">A Class</span>',
                    'B Class' => '<span class="bg-yellow text-white">A Class</span>',
                ];
                return array_key_exists($value, $map) ? $map[$value] : $value;

            case 'Signup Date':
            case 'On Date':
                return $this->transformDate($value, 'd/m/y H:i');
            
            case 'Last Race':
                return $this->transformDate($value, 'd/m/y');
            
            // case 'Server SoF':
            //     $value = round($value, 1);
                
            default:
            return $value;
        }
    }

    private function transformDate($data, $format)
    {
        try {
            return Carbon\Carbon::parse($data)->format($format);
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

    public function render()
    {
        $this->fetchData();

        $loader = new FilesystemLoader(__DIR__ . '/views');
        $twig = new Environment($loader);

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

        // dd($data);

        echo $twig->render('pitskill.twig', $data);
    }
}

(new PitskillData())->render();

