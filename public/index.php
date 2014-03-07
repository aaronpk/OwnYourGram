<?php
chdir('..');
require 'vendor/autoload.php';
require 'lib/Savant.php';
require 'lib/helpers.php';
require 'lib/config.php';
require 'lib/markdown.php';

// Configure the Savant plugin
\Slim\Extras\Views\Savant::$savantDirectory = 'vendor/albertofem/savant3/src';
\Slim\Extras\Views\Savant::$savantOptions = array('template_path' => 'views');

// Create a new app object with the Savant view renderer
$app = new \Slim\Slim(array(
  'view' => new \Slim\Extras\Views\Savant()
));

require 'controllers/controllers.php';

session_start();

$app->run();
