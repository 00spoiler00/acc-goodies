<?php

require 'vendor/autoload.php';

use Carbon\Carbon;
use Illuminate\Support\Collection;

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
        $this->ids = json_decode(file_get_contents('data/ids.json'));
        
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
        $notifications = json_decode(file_get_contents('data/notifications.json'), true);
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
                $localDrivers = collect($this->registrations)
                    ->filter(fn($r) => $r['Enroll Link'] == $registration['Enroll Link'])
                    ->map(fn($r) => $r['Driver'])
                    ->join(',')
                ;
                $message = "**{$registration['Driver']}** s'ha apuntat a [{$registration['Upcoming Event']}](<https://pitskill.io/event/{$registration['Enroll Link']}>) *@{$registration['On Date']}* ({$sof} SoF, {$registration['Registration']} pilots) [{$localDrivers}]";
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
          file_put_contents('data/notifications.json', json_encode($notifications->toArray()));
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
                if($lastRepChange > 5){
                    $promotions[] = $id;
                }else{
                    $pitRepChanges[$id] = $lastRepChange;
                }
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
        if (file_exists('data/stats.json')) {
            $this->stats = json_decode(file_get_contents('data/stats.json'), true);
        }
    }

    private function saveStats() : void
    {
        file_put_contents('data/stats.json', json_encode($this->stats));
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
        // Marc
        // $webhookUrl = 'https://discord.com/api/webhooks/1266792058228834315/eSDfbXpG-c2GHxcLFyEVlf-kDKr67dKghu5fLRyOB5C9JniAW-pKHPsA3tP59f2K075c';
        // ATotDrap 
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
            'lastUpdate' => $this->transformDate(Carbon::now(), 'd/m/y H:i:s'),
            'version' => 2,
        ];

        file_put_contents('data/data.json', json_encode($data));
    }
}

class HotlapProcessor
{
    private static $directory = '/home/marc/accServers/acc-server-00/results';
    private static $trackResults;
    private static $pilotDetails;

    public static function run()
    {
        self::$trackResults = collect();
        self::$pilotDetails = collect();

        self::loadExistingData();
        self::processFiles();
        self::saveData();
    }

    private static function loadExistingData()
    {
        if (file_exists('data/drivers.json')) {
            $existingDrivers = json_decode(file_get_contents('data/drivers.json'), true);
            if ($existingDrivers) {
                self::$pilotDetails = collect($existingDrivers);
            }
        }

        if (file_exists('data/hotlaps.json')) {
            $existingHotlaps = json_decode(file_get_contents('data/hotlaps.json'), true);
            if ($existingHotlaps) {
                self::$trackResults = collect($existingHotlaps);
            }
        }
    }

    private static function processFiles()
    {
        $files = glob(self::$directory . '/*.json');
        error_log("Processing files...".PHP_EOL, 3, 'logs/access.log');
        foreach ($files as $file) {
            error_log($file.PHP_EOL, 3, 'logs/access.log');
            self::processFile($file);
            unlink($file);
        }
    }

    private static function processFile($file)
    {
        $carbonDate = self::extractDateFromFilename($file);
        $data = self::loadJsonFile($file);

        if (isset($data['sessionResult']['leaderBoardLines'])) {
            foreach ($data['sessionResult']['leaderBoardLines'] as $leaderboard) {
                self::processLeaderboard($leaderboard, $data['trackName'], $carbonDate);
            }
        }
    }

    private static function extractDateFromFilename($file)
    {
        preg_match('/(\d{6})_/', basename($file), $matches);
        $fileDate = $matches[1] ?? 'unknown';
        return Carbon::createFromFormat('ymd', $fileDate)->format('Y-m-d');
    }

    private static function loadJsonFile($file)
    {
        $content = file_get_contents($file);
        $data = mb_convert_encoding($content, 'UTF-8', 'UTF-16LE');
        return json_decode($data, true);
    }

    private static function processLeaderboard($leaderboard, $circuit, $carbonDate)
    {
        $car = $leaderboard['car'];
        $driver = $leaderboard['currentDriver'];
        $timing = $leaderboard['timing'];

        $playerId = $driver['playerId'];
        $carId = $car['carModel'];
        $bestLap = $timing['bestLap'];

        if ($bestLap === 0 || $bestLap === 2147483647) {
            return;
        }

        self::savePilotDetails($driver, $playerId);
        self::updateTrackResults($circuit, $playerId, $carId, $driver, $bestLap, $carbonDate);
    }

    private static function savePilotDetails($driver, $playerId)
    {
        if (!self::$pilotDetails->has($playerId)) {
            self::$pilotDetails->put($playerId, [
                'firstName' => $driver['firstName'],
                'lastName' => $driver['lastName'],
                'shortName' => $driver['shortName'],
            ]);
        }
    }

    private static function updateTrackResults($circuit, $playerId, $carId, $driver, $bestLap, $carbonDate)
    {
        $circuitResults = self::$trackResults->get($circuit);
        $circuitResults = collect($circuitResults);
    
        $existingEntryIndex = $circuitResults->search(function ($entry) use ($playerId, $carId) {
            return $entry['DriverId'] === $playerId && $entry['CarId'] === $carId;
        });
    
        if ($existingEntryIndex !== false) {
            // Use the map method to update the entry within the Collection
            $circuitResults = $circuitResults->map(function ($entry, $index) use ($existingEntryIndex, $bestLap, $carbonDate) {
                if ($index === $existingEntryIndex) {
                    // Update the entry if a better lap time is found
                    $entry['Laptime'] = min($entry['Laptime'], $bestLap);
                    $entry['Date'] = $carbonDate;
                }
                return $entry;
            });
        } else {

            list($carModel, $category) = self::getCarInfoById($carId);

            $circuitResults->push([
                'CarId' => $carId,
                'CarModel' => $carModel,
                'Category' => $category,
                'DriverId' => $playerId,
                'Driver' => $driver['firstName'] . ' ' . $driver['lastName'],
                'Laptime' => $bestLap,
                'Date' => $carbonDate
            ]);
        }
    
        self::$trackResults->put($circuit, $circuitResults);
    }

    private static function getCarInfoById($id) {
        // Array mapping car IDs to car models and categories
        $carInfo = [
            0 => ['Porsche 991 GT3 R', 'GT3'],
            1 => ['Mercedes-AMG GT3', 'GT3'],
            2 => ['Ferrari 488 GT3', 'GT3'],
            3 => ['Audi R8 LMS', 'GT3'],
            4 => ['Lamborghini Huracán GT3', 'GT3'],
            5 => ['McLaren 650S GT3', 'GT3'],
            6 => ['Nissan GT-R Nismo GT3', 'GT3'],
            7 => ['BMW M6 GT3', 'GT3'],
            8 => ['Bentley Continental GT3', 'GT3'],
            9 => ['Porsche 911 II GT3 Cup', 'GTC'],
            10 => ['Nissan GT-R Nismo GT3', 'GT3'],
            11 => ['Bentley Continental GT3', 'GT3'],
            12 => ['AMR V12 Vantage GT3', 'GT3'],
            13 => ['Reiter Engineering R-EX GT3', 'GT3'],
            14 => ['Emil Frey Jaguar G3', 'GT3'],
            15 => ['Lexus RC F GT3', 'GT3'],
            16 => ['Lamborghini Huracán GT3 Evo', 'GT3'],
            17 => ['Honda NSX GT3', 'GT3'],
            18 => ['Lamborghini Huracan SuperTrofeo', 'GTC'],
            19 => ['Audi R8 LMS Evo', 'GT3'],
            20 => ['AMR V8 Vantage', 'GT3'],
            21 => ['Honda NSX GT3 Evo', 'GT3'],
            22 => ['McLaren 720S GT3', 'GT3'],
            23 => ['Porsche 911 II GT3 R', 'GT3'],
            24 => ['Ferrari 488 GT3 Evo', 'GT3'],
            25 => ['Mercedes-AMG GT3', 'GT3'],
            26 => ['Ferrari 488 Challenge Evo', 'GTC'],
            27 => ['BMW M2 Club Sport Racing', 'TCX'],
            28 => ['Porsche 992 GT3 Cup', 'GTC'],
            29 => ['Lamborghini Huracán SuperTrofeo EVO2', 'GTC'],
            30 => ['BMW M4 GT3', 'GT3'],
            31 => ['Audi R8 LMS GT3 Evo 2', 'GT3'],
            32 => ['Ferrari 296 GT3', 'GT3'],
            33 => ['Lamborghini Huracan GT3 Evo 2', 'GT3'],
            34 => ['Porsche 992 GT3 R', 'GT3'],
            35 => ['McLaren 720S GT3 Evo', 'GT3'],
            36 => ['Ford Mustang GT3', 'GT3'],
            37 => ['Alpine A110 GT4', 'GT4'],
            38 => ['Aston Martin Vantage GT4', 'GT4'],
            39 => ['Audi R8 LMS GT4', 'GT4'],
            40 => ['BMW M4 GT4', 'GT4'],
            41 => ['Chevrolet Camaro GT4', 'GT4'],
            42 => ['Ginetta G55 GT4', 'GT4'],
            43 => ['KTM X-Bow GT4', 'GT4'],
            44 => ['Maserati MC GT4', 'GT4'],
            45 => ['McLaren 570S GT4', 'GT4'],
            46 => ['Mercedes AMG GT4', 'GT4'],
            47 => ['Porsche 718 Cayman GT4 Clubsport', 'GT4'],
            48 => ['Audi R8 LMS GT2', 'GT2'],
            49 => ['KTM XBOW GT2', 'GT2'],
            50 => ['Maserati MC20 GT2', 'GT2'],
            51 => ['Mercedes AMG GT2', 'GT2'],
            52 => ['Porsche 911 GT2 RS CS Evo', 'GT2'],
            53 => ['Porsche 935', 'GT2']
        ];
    
        // Check if the ID exists in the array and return the corresponding model and category
        if (isset($carInfo[$id])) {
            return $carInfo[$id];
        } else {
            return ["Car model not found", "N/A"];
        }
    }
    
    private static function saveData()
    {
        file_put_contents('data/hotlaps.json', self::$trackResults->toJson(JSON_PRETTY_PRINT));
        file_put_contents('data/drivers.json', self::$pilotDetails->toJson(JSON_PRETTY_PRINT));
    }
}

// Run the Hotlap Processor with error logging
try {

    error_log("Updating data...", 3, 'logs/access.log');
    (new PitskillDataFetcher())->run();
    error_log("Done!" . PHP_EOL, 3, 'logs/access.log');

    error_log("Processing hotlaps data...", 3, 'logs/access.log');
    HotlapProcessor::run();
    error_log("Done!" . PHP_EOL, 3, 'logs/access.log');

} catch (Exception $e) {
    error_log("Error in HotlapProcessor: " . $e->getMessage() . PHP_EOL, 3, 'logs/error.log');
    error_log("Stack trace: " . $e->getTraceAsString() . PHP_EOL, 3, 'logs/error.log');
}
