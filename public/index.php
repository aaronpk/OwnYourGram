<?php
chdir('..');
require 'vendor/autoload.php';
require 'lib/Savant.php';
require 'lib/config.php';
require 'lib/helpers.php';
require 'lib/markdown.php';
require 'lib/instagram.php';

// Configure the Savant plugin
\Slim\Extras\Views\Savant::$savantDirectory = 'vendor/saltybeagle/savant3';
\Slim\Extras\Views\Savant::$savantOptions = array('template_path' => 'views');

// Create a new app object with the Savant view renderer
$app = new \Slim\Slim(array(
  'view' => new \Slim\Extras\Views\Savant()
));

require 'controllers/auth.php';
require 'controllers/controllers.php';
require 'controllers/hooks.php';

session_start();

$app->run();
