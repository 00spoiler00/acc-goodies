<?php

require 'vendor/autoload.php';

use Illuminate\Support\Collection;
use Carbon\Carbon;

// Directorio que contiene los archivos de resultados
$directory = './results';

// Obtener todos los archivos JSON en el directorio
$files = glob($directory . '/*.json');

// Colecciones para almacenar resultados y detalles del piloto
$trackResults = collect();
$pilotDetails = collect();

// Cargar los detalles de pilotos existentes si el archivo existe
if (file_exists('drivers.json')) {
    $existingDrivers = json_decode(file_get_contents('drivers.json'), true);
    if ($existingDrivers) {
        $pilotDetails = collect($existingDrivers);
    }
}

// Iterar sobre cada archivo JSON
foreach ($files as $file) {

    // Extraer la fecha del nombre del archivo y convertirla a un objeto Carbon
    preg_match('/(\d{6})_/', basename($file), $matches);
    $fileDate = $matches[1] ?? 'unknown';
    $carbonDate = Carbon::createFromFormat('ymd', $fileDate)->format('Y-m-d');

    // Leer y convertir los datos del archivo JSON
    $content = file_get_contents($file);
    $data = mb_convert_encoding($content, 'UTF-8', 'UTF-16LE');
    $data = json_decode($data, true);

    if (isset($data['sessionResult']['leaderBoardLines'])) {
        foreach ($data['sessionResult']['leaderBoardLines'] as $leaderboard) {
            $car = $leaderboard['car'];
            $driver = $leaderboard['currentDriver'];
            
            
            $timing = $leaderboard['timing'];

            $circuit = $data['trackName'];
            $playerId = $driver['playerId'];
            $carId = $car['carId'];
            $bestLap = $timing['bestLap'];

            // Avoid wrong results
            if($bestLap === 0 || $bestLap === 2147483647) {
                continue;
            }

            // Guardar detalles del piloto si no existen
            if (!$pilotDetails->has($playerId)) {
                $pilotDetails->put($playerId, [
                    'firstName' => $driver['firstName'],
                    'lastName' => $driver['lastName'],
                    'shortName' => $driver['shortName'],
                ]);
            }

            // Obtener los resultados actuales para el circuito
            $circuitResults = $trackResults->get($circuit, collect());

            // Buscar si ya existe un registro para el mismo piloto y coche
            $existingEntry = $circuitResults->first(function ($entry) use ($playerId, $carId) {
                return $entry['Driver'] === $playerId && $entry['Car'] === $carId;
            });

            if ($existingEntry) {
                // Actualizar si se encuentra un mejor tiempo
                if ($bestLap < $existingEntry['Laptime']) {
                    $existingEntry['Laptime'] = $bestLap;
                    $existingEntry['Date'] = $carbonDate;
                }
            } else {
                // Añadir nuevo registro si no existe
                $circuitResults->push([
                    'Car' => $carId,
                    'DriverId' => $playerId,
                    'Driver' => $driver['firstName'] . ' ' . $driver['lastName'],
                    'Laptime' => $bestLap,
                    'Date' => $carbonDate
                ]);
            }

            // Guardar resultados actualizados para el circuito
            $trackResults->put($circuit, $circuitResults);
        }
    }
}

// Convertir colecciones a arreglos y guardar en JSON
file_put_contents('hotlaps.json', $trackResults->toJson(JSON_PRETTY_PRINT));
file_put_contents('drivers.json', $pilotDetails->toJson(JSON_PRETTY_PRINT));
