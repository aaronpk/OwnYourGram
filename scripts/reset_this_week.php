<?php
chdir(dirname(__FILE__).'/..');
require 'vendor/autoload.php';
require 'lib/Savant.php';
require 'lib/config.php';

ORM::for_table('users')->raw_query('UPDATE users SET photo_count_this_week = 0');

