<?php

include './vendor/autoload.php';

// Función para obtener datos de una URL
function getDataFromUrl($url) {
    return json_decode(file_get_contents($url), true);
}

// IDs de ejemplo
$ids = [17425];

// Obtener y combinar los datos para cada ID
foreach ($ids as $id) {
    $datas[] = [
        'driverInfo' => getDataFromUrl("https://api.pitskill.io/api/pitskill/getdriverinfo?id=$id"),
        'upcomingRegistrations' => getDataFromUrl("https://api.pitskill.io/api/events/upcomingRegistrations?id=$id"),
    ];
}

// Definir la estructura de los datos
$columns = [
    // 'Image' => 'driverInfo.payload',
    'Image' => 'driverInfo.payload.sigma_user_data.discord_avatar',
    'Driver Name' => 'driverInfo.payload.tpc_driver_data.name',
    'Nickname' => 'driverInfo.payload.sigma_user_data.profile_data.nickname',
    'Current Pit Rep' => 'driverInfo.payload.tpc_driver_data.currentPitRep',
    'Current Pit Skill' => 'driverInfo.payload.tpc_driver_data.currentPitSkill',
    'Daily Race Count' => 'driverInfo.payload.tpc_driver_data.daily_race_count',
    'Licence Class' => 'driverInfo.payload.tpc_driver_data.licence_class',
    'Last Race Date' => 'driverInfo.payload.tpc_stats.lastRaceDate',
    'Signup Date' => 'driverInfo.payload.sigma_user_data.signupDate',
    'VIP Level' => 'driverInfo.payload.sigma_user_data.vip_level',
    'Upcoming Event Name' => 'upcomingRegistrations.payload.0.event_name',
    'Event Start Date' => 'upcomingRegistrations.payload.0.start_date',
    'Server' => 'upcomingRegistrations.payload.0.event_registrations.0.vehicle_registration.server.server_name',
    'Server SoF' => 'upcomingRegistrations.payload.0.event_registrations.0.vehicle_registration.server.server_strength_of_field',
    'Server Splits' => 'upcomingRegistrations.payload.0.event_registrations.0.vehicle_registration.server.server_split_index',
    'Server Strength of Field' => 'upcomingRegistrations.payload.0.event_registrations.0.car.name',
    'Car' => 'upcomingRegistrations.payload.0.event_registrations.0.car.name',
    'Track' => 'upcomingRegistrations.payload.0.track.track_name_long',
    'Registration Count' => 'upcomingRegistrations.payload.0.registration_count',
    'Broadcasters' => 'upcomingRegistrations.payload.0.broadcasters'
];

function getValue($data, $path) {
    // dd($data);
    $keys = explode('.', $path);
    foreach ($keys as $key) {
        if (!isset($data[$key])) {
            return 'N/A';
        }
        $data = $data[$key];
    }
    return $data;
}


?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pitskill Current Standings</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h1 class="text-center mb-4">Pitskill Current Standings</h1>
        <table class="table table-bordered">
            <thead class="thead-dark">
                <tr>
                    <?php foreach (array_keys($columns) as $column): ?>
                        <th><?php echo htmlspecialchars($column); ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($datas as $data): ?>
                    <tr>
                        <?php foreach ($columns as $column => $path): ?>
                            <?php 
                                $value = getValue($data, $path);
                                if ($column == 'Image') {
                                    $value = "<img src='" . htmlspecialchars($value) . "' alt='Image' />";
                                } elseif ($column == 'Broadcasters') {
                                    $value = is_array($value) && count($value) > 0 ? 'Yes' : 'No';
                                }
                            ?>
                            <td><?php echo $value; ?></td>

                        <?php endforeach; ?>

                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>


</body>
</html>
