<?php
namespace IG;

class AccessTokenException extends \Exception {
}

function get_latest_photos(&$user, $since=false, $limit=1) {
  $params = array(
    'access_token' => $user->instagram_access_token
  );

  if($limit !== false)
    $params['count'] = $limit;

  if($since !== false)
    $params['min_timestamp'] = $since;

  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, 'https://api.instagram.com/v1/users/self/media/recent?'.http_build_query($params));
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  $response = curl_exec($ch);
  $data = @json_decode($response);
  if($data && is_object($data)) {
    if(property_exists($data, 'data')) {
      return $data->data;
    } elseif(property_exists($data, 'meta') && property_exists($data->meta, 'error_message')) {
      throw new AccessTokenException($data->meta->error_message);
    } else {
      return null;
    }
  } else {
    return null;
  }
}

function get_photo(&$user, $media_id) {
  $params = array(
    'access_token' => $user->instagram_access_token
  );

  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, 'https://api.instagram.com/v1/media/'.$media_id.'?'.http_build_query($params));
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  $response = curl_exec($ch);
  $data = @json_decode($response);
  if($data)
    return $data->data;
  else
    return null;
}

function delete_comment(&$user, $media_id, $comment_id) {
  $params = array(
    'access_token' => $user->instagram_access_token
  );

  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, 'https://api.instagram.com/v1/media/'.$media_id.'/comments/'.$comment_id.'?'.http_build_query($params));
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
  $response = curl_exec($ch);
  $data = @json_decode($response);
  if($data)
    return $data->data;
  else
    return null;
}

function add_comment(&$user, $media_id, $text) {
  $params = array(
    'access_token' => $user->instagram_access_token,
    'text' => $text
  );

  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, 'https://api.instagram.com/v1/media/'.$media_id.'/comments?'.http_build_query($params));
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
  $response = curl_exec($ch);
  $data = @json_decode($response);
  if($data)
    return $data->data;
  else
    return array('error'=>$response);
}

