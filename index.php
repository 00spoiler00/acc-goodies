<?php

opcache_reset();

require 'vendor/autoload.php';

use Twig\Environment;
use Twig\Loader\FilesystemLoader;

class PitskillDataRenderer
{


    public function render()
    {
        $data = json_decode(file_get_contents('data.json'), true);
        $loader = new FilesystemLoader(__DIR__ . '/views');
        $twig = new Environment($loader);

        echo $twig->render('pitskill.twig', $data);
    }
}

(new PitskillDataRenderer())->render();

