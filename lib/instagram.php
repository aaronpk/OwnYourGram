<?php
namespace IG;

function get_latest_photo(&$user) {
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, 'https://api.instagram.com/v1/users/self/media/recent?count=3&access_token='.$user->instagram_access_token);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  $response = curl_exec($ch);
  $data = json_decode($response);
  return $data->data;
}
