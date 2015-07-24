<?php

ORM::configure('mysql:host=' . Config::$dbHost . ';dbname=' . Config::$dbName);
ORM::configure('username', Config::$dbUsername);
ORM::configure('password', Config::$dbPassword);

function render($page, $data) {
  global $app;
  return $app->render('layout.php', array_merge($data, array('page' => $page)));
};

function partial($template, $data, $debug=false) {
  global $app;

  if($debug) {
    $tpl = new Savant3(\Slim\Extras\Views\Savant::$savantOptions);
    echo '<pre>' . $tpl->fetch($template . '.php') . '</pre>';
    return '';
  }

  ob_start();
  $tpl = new Savant3(\Slim\Extras\Views\Savant::$savantOptions);
  foreach($data as $k=>$v) {
    $tpl->{$k} = $v;
  }
  $tpl->display($template . '.php');
  return ob_get_clean();
}

function session($key) {
  if(array_key_exists($key, $_SESSION))
    return $_SESSION[$key];
  else
    return null;
}

function k($a, $k, $default=null) {
  if(is_array($k)) {
    $result = true;
    foreach($k as $key) {
      $result = $result && array_key_exists($key, $a);
    }
    return $result;
  } else {
    if(is_array($a) && array_key_exists($k, $a) && $a[$k])
      return $a[$k];
    elseif(is_object($a) && property_exists($a, $k) && $a->$k)
      return $a->$k;
    else
      return $default;
  }
}

function bs()
{
  static $pheanstalk;
  if(!isset($pheanstalk))
  {
    $pheanstalk = new Pheanstalk\Pheanstalk(Config::$beanstalkServer, Config::$beanstalkPort);
  }
  return $pheanstalk;
}

function get_timezone($lat, $lng) {
  try {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'http://timezone-api.geoloqi.com/timezone/'.$lat.'/'.$lng);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    $tz = @json_decode($response);
    if($tz)
      return new DateTimeZone($tz->timezone);
  } catch(Exception $e) {
    return null;
  }
  return null;
}

function download_file($url, $ext='jpg') {
  $filename = tempnam(dirname(__FILE__).'../tmp/', 'ig').'.'.$ext;
  $fp = fopen($filename, 'w+');
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $url);
  curl_setopt($ch, CURLOPT_FILE, $fp);
  curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
  curl_exec($ch);
  curl_close($ch);
  fclose($fp);
  return $filename;  
}

function micropub_post($endpoint, $access_token, $params, $photo_filename=false, $video_filename=false) {

  $postfields = array(
    'h' => 'entry',
    'published' => $params['published'],
    'location' => $params['location'],
    'place_name' => $params['place_name'],
    'category' => $params['category'],
    'content' => $params['content'],
    'syndication' => $params['syndication'],
    'access_token' => $access_token
  );

  if(k($params, 'category'))
    $postfields = $params['category'];

  $multipart = new p3k\Multipart();

  $multipart->addArray($postfields);

  if($photo_filename)
    $multipart->addFile('photo', $photo_filename, 'image/jpeg');

  if($video_filename)
    $multipart->addFile('video', $video_filename, 'video/mp4');

  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $endpoint);
  curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    'Authorization: Bearer ' . $access_token,
    'Content-Type: ' . $multipart->contentType()
  ));
  curl_setopt($ch, CURLOPT_POST, true);
  curl_setopt($ch, CURLOPT_POSTFIELDS, $multipart->data());
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_HEADER, true);
  $response = curl_exec($ch);
  $error = curl_error($ch);
  return array(
    'response' => $response,
    'error' => $error,
    'curlinfo' => curl_getinfo($ch)
  );
}

// Given an Instagram photo object, return an h-entry array with all the necessary keys
function h_entry_from_photo(&$user, &$photo) {
  $entry = array(
    'published' => null,
    'location' => null,
    'place_name' => null,
    'category' => array(),
    'content' => '',
    'syndication' => ''
  );

  $entry['published'] = date('c', $photo->created_time);

  // Look up the timezone of the photo if location data is present
  if(property_exists($photo, 'location') && $photo->location) {
    if($tz = get_timezone($photo->location->latitude, $photo->location->longitude)) {
      $d = DateTime::createFromFormat('U', $photo->created_time);
      $d->setTimeZone($tz);
      $entry['published'] = $d->format('c');
    }
  }

  if($photo->location)
    $entry['location'] = 'geo:' . $photo->location->latitude . ',' . $photo->location->longitude;

  if($photo->location && k($photo->location, 'name'))
    $entry['place_name'] = k($photo->location, 'name');

  // Add the regular tags to the category array
  if($photo->tags)
    $entry['category'] = array_merge($entry['category'], $photo->tags);

  // Add person-tags to the category array
  if($photo->users_in_photo) {
    foreach($photo->users_in_photo as $tag) {
      // Fetch the user's website
      if($profile = IG\get_profile($user, $tag->user->id)) {
        if($profile->website)
          $entry['category'][] = $profile->website;
        else
          $entry['category'][] = 'https://instagram.com/' . $profile->username;
        // $entry['category'][] = [
        //   'type' => ['h-card'],
        //   'properties' => [
        //     'name' => [$profile->full_name],
        //     'url' => [$profile->website],
        //     'photo' => [$profile->profile_picture]
        //   ],
        //   'value' => $profile->website
        // ];
      }
    }
  }

  if($photo->caption)
    $entry['content'] = $photo->caption->text;

  $entry['syndication'] = $photo->link;

  return $entry;
}

