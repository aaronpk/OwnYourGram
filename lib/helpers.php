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
    $pheanstalk = new Pheanstalk_Pheanstalk(Config::$beanstalkServer, Config::$beanstalkPort);
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

function download_file($url) {
  $filename = tempnam(dirname(__FILE__).'../tmp/', 'ig');
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

function micropub_post($endpoint, $params, $filename, $access_token) {
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $endpoint);
  curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    'Authorization: Bearer ' . $access_token
  ));
  curl_setopt($ch, CURLOPT_POST, true);
  curl_setopt($ch, CURLOPT_POSTFIELDS, array(
    'h' => 'entry',
    'published' => $params['published'],
    'location' => $params['location'],
    'place_name' => $params['place_name'],
    'category' => $params['category'],
    'content' => $params['content'],
    'photo' => '@'.$filename
  ));
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_HEADER, true);
  return curl_exec($ch);
}

// Given an Instagram photo object, return an h-entry array with all the necessary keys
function h_entry_from_photo(&$photo) {
  $entry = array(
    'published' => null,
    'location' => null,
    'place_name' => null,
    'category' => array(),
    'content' => ''
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

  if($photo->tags)
    $entry['category'] = implode(',', $photo->tags);

  if($photo->caption)
    $entry['content'] = $photo->caption->text;

  return $entry;
}

