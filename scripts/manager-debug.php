<?php
declare(ticks=1);

if(count($argv) < 2) {
  echo "Usage example: php manager.php worker\n";
  exit(0);
}

$script = $argv[1];
$script = str_replace('.php', '', $script);
$scriptFileName = $script.'.php';

echo 'Loading '.$scriptFileName."\n";

if(function_exists('pcntl_signal')) {
  pcntl_signal(SIGINT, function($sig){
    global $pcntl_continue;
    $pcntl_continue = FALSE;
  });
}

$pcntl_continue = TRUE;
define('PDO_SUPPORT_DELAYED', TRUE);

// Include the script which will load up the environment and begin getting jobs from the queue
include($scriptFileName);
    
