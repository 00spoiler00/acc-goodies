<?php

opcache_reset();


require 'vendor/autoload.php';

use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Carbon\Carbon;

class PitskillDataRenderer
{
    public function render()
    {
        $data = json_decode(file_get_contents('data.json'), true);
        $loader = new FilesystemLoader(__DIR__ . '/views');
        $twig = new Environment($loader);

        $lastUpdate = filemtime('data.json');
        $lastUpdate = Carbon::createFromTimestamp($lastUpdate, 'UTC')
                                  ->setTimezone('Europe/Madrid')
                                  ->toDateTimeString();

        // Add the modification date to the data array
        $data['last_update'] = $lastUpdate;


        echo $twig->render('pitskill.twig', $data);
    }
}

(new PitskillDataRenderer())->render();

