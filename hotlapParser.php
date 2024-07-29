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
        $carId = $car['carId'];
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
        $circuitResults = self::$trackResults->get($circuit, collect());

        $existingEntryIndex = $circuitResults->search(function ($entry) use ($playerId, $carId) {
            return $entry['DriverId'] === $playerId && $entry['Car'] === $carId;
        });

        if ($existingEntryIndex !== false) {
            if ($bestLap < $circuitResults[$existingEntryIndex]['Laptime']) {
                $circuitResults[$existingEntryIndex]['Laptime'] = $bestLap;
                $circuitResults[$existingEntryIndex]['Date'] = $carbonDate;
            }
        } else {
            $circuitResults->push([
                'Car' => $carId,
                'DriverId' => $playerId,
                'Driver' => $driver['firstName'] . ' ' . $driver['lastName'],
                'Laptime' => $bestLap,
                'Date' => $carbonDate
            ]);
        }

        self::$trackResults->put($circuit, $circuitResults);
    }

    private static function saveData()
    {
        file_put_contents('hotlaps.json', self::$trackResults->toJson(JSON_PRETTY_PRINT));
        file_put_contents('drivers.json', self::$pilotDetails->toJson(JSON_PRETTY_PRINT));
    }
}

// Run the Hotlap Processor
HotlapProcessor::run();
