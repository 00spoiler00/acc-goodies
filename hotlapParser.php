<?php

require 'vendor/autoload.php';

use Illuminate\Support\Collection;
use Carbon\Carbon;

class HotlapProcessor
{
    private static $directory = './results';
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
        if (file_exists('drivers.json')) {
            $existingDrivers = json_decode(file_get_contents('drivers.json'), true);
            if ($existingDrivers) {
                self::$pilotDetails = collect($existingDrivers);
            }
        }

        if (file_exists('hotlaps.json')) {
            $existingHotlaps = json_decode(file_get_contents('hotlaps.json'), true);
            if ($existingHotlaps) {
                self::$trackResults = collect($existingHotlaps);
            }
        }
    }

    private static function processFiles()
    {
        $files = glob(self::$directory . '/*.json');
        foreach ($files as $file) {
            self::processFile($file);
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
        file_put_contents('hotlaps.json', self::$trackResults->toJson(JSON_PRETTY_PRINT));
        file_put_contents('drivers.json', self::$pilotDetails->toJson(JSON_PRETTY_PRINT));
    }
}

// Run the Hotlap Processor
HotlapProcessor::run();
