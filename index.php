<?php
error_reporting(-1);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

require 'vendor/autoload.php';

$configuration = [
    'settings' => [
        'displayErrorDetails' => true,
    ],
];

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$c = new \Slim\Container($configuration);
$app = new \Slim\App($c);

require './src/Routes.php';

$app->run();