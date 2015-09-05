<?php
chdir(dirname(__FILE__).'/..');
require 'vendor/autoload.php';
require 'lib/Savant.php';
require 'lib/config.php';
require 'lib/helpers.php';

$db = new PDO(
    'mysql:host=' . Config::$dbHost . ';dbname=' . Config::$dbName,
    Config::$dbUsername,
    Config::$dbPassword
);

$db->exec('UPDATE users SET photo_count_this_week = 0');

