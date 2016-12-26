<?php
chdir(dirname(__FILE__).'/..');
require 'vendor/autoload.php';

$users = ORM::for_table('users')
  ->where_not_equal('instagram_access_token', '')
  ->find_many();
foreach($users as $user) {
  echo $user->url . "\n";

  $info = file_get_contents('https://api.instagram.com/v1/users/self/?access_token='.$user->instagram_access_token);
  $data = json_decode($info);
  print_r($info);

  if($data) {
    $user->instagram_username = $data->data->username;
    $user->save();
  }

  die();
}

