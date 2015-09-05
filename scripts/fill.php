<?php
chdir(dirname(__FILE__).'/..');
require 'vendor/autoload.php';
require 'lib/Savant.php';
require 'lib/config.php';
require 'lib/helpers.php';
require 'lib/markdown.php';
require 'lib/instagram.php';

$users = ORM::for_table('users')
  ->where_not_null('last_instagram_photo')
  ->where_not_null('last_micropub_url')
  ->where_null('last_instagram_img_url')
  ->find_many();
foreach($users as $user) {
  echo $user->url . "\n";
  try {
    $photo = IG\get_photo($user, $user->last_instagram_photo);
    $public = IG\user_is_public($user);

    $user->ig_public = $public ? 1 : 0;
    if($photo) {
      $user->last_instagram_img_url = $photo->images->standard_resolution->url;
      echo $photo->images->standard_resolution->url . "\n";
    } else {
      echo "no photo found\n";
    }
    $user->save();
  } catch(Exception $e) {
    echo "Invalid instagram token\n";
  }
}

