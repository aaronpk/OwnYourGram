<?php
chdir('..');
require 'vendor/autoload.php';

// Configure the Savant plugin
\Slim\Extras\Views\Savant::$savantDirectory = 'vendor/saltybeagle/savant3';
\Slim\Extras\Views\Savant::$savantOptions = array('template_path' => 'views');

// Create a new app object with the Savant view renderer
$app = new \Slim\Slim(array(
  'view' => new \Slim\Extras\Views\Savant()
));

class Logger {
  public static $log;

  public static function init() {
    self::$log = new Monolog\Logger('name');
    self::$log->pushHandler(new Monolog\Handler\StreamHandler('logs/ownyourgram.log', Monolog\Logger::INFO));
  }
}

Logger::init();

require 'controllers/auth.php';
require 'controllers/controllers.php';

session_start();

$app->run();
