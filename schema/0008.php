<?php
chdir(dirname(__FILE__).'/..');
require 'vendor/autoload.php';

$photos = ORM::for_table('photos')->find_many();
foreach($photos as $photo) {
  $data = json_decode($photo->instagram_data, true);
  if($data) {
    $photo->published = date('Y-m-d H:i:s', strtotime($data['published']));
    $photo->save();
  }
}
