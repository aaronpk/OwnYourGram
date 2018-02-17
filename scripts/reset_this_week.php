<?php
chdir(dirname(__FILE__).'/..');
require 'vendor/autoload.php';

$db = new PDO(
    'mysql:host=' . Config::$dbHost . ';dbname=' . Config::$dbName,
    Config::$dbUsername,
    Config::$dbPassword
);

$db->exec('UPDATE users SET photo_count_this_week = 0');
