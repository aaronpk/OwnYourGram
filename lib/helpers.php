<?php

ORM::configure('mysql:host=' . Config::$dbHost . ';dbname=' . Config::$dbName);
ORM::configure('username', Config::$dbUsername);
ORM::configure('password', Config::$dbPassword);

class Logger {
  public static $log;

  public static function init() {
    self::$log = new Monolog\Logger('name');
    self::$log->pushHandler(new Monolog\Handler\StreamHandler(dirname(__FILE__).'/../logs/ownyourgram.log', Monolog\Logger::INFO));
  }
}

Logger::init();


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

function redis() {
  static $client = false;
  if(!$client)
    $client = new Predis\Client(Config::$redis);
  return $client;
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

function friendly_url($url) {
  return preg_replace(['/https?:\/\//','/\/$/'],'',$url);
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
  $timezone = p3k\Timezone::timezone_for_location($lat, $lng);
  if($timezone) {
    return new DateTimeZone($timezone);
  } else {
    return null;
  }
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

function micropub_post($user, $params, $photo_filename=false, $video_filename=false) {

  $endpoint = $user->micropub_endpoint;
  $access_token = $user->micropub_access_token;

  if(k($params, 'name'))
    $properties['name'] = $params['name'];
  if(k($params, 'content'))
    $properties['content'] = $params['content'];
  if(k($params, 'category'))
    $properties['category'] = $params['category'];
  if(k($params, 'published'))
    $properties['published'] = $params['published'];
  if(k($params, 'location'))
    $properties['location'] = $params['location'];
  if(k($params, 'syndication'))
    $properties['syndication'] = $params['syndication'];
  if(k($params, 'mp-syndicate-to'))
    $properties['mp-syndicate-to'] = $params['mp-syndicate-to'];

  if($user->send_media_as == 'upload') {
    $postfields = array(
      'h' => 'entry',
      'access_token' => $access_token
    );

    foreach($properties as $k=>$v)
      $postfields[$k] = $v;

    if(k($params, 'place_name'))
      $postfields['place_name'] = $params['place_name'];

    $multipart = new p3k\Multipart();

    $multipart->addArray($postfields);

    if($photo_filename) {
      if(is_array($photo_filename)) {
        foreach($photo_filename as $f) {
          $multipart->addFile('photo[]', $f, 'image/jpeg');
        }
      } else {
        $multipart->addFile('photo', $photo_filename, 'image/jpeg');
      }
    }

    if($video_filename) {
      if(is_array($video_filename)) {
        foreach($video_filename as $f) {
          $multipart->addFile('video[]', $f, 'video/mp4');
        }
      } else {
        $multipart->addFile('video', $video_filename, 'video/mp4');
      }
    }

    $body = $multipart->data();
    $content_type = $multipart->contentType();
  } else {
    $content_type = 'application/json';

    if($photo_filename)
      $properties['photo'] = $photo_filename;

    if($video_filename)
      $properties['video'] = $video_filename;

    // Convert everything to an array
    foreach($properties as $k=>$v) {
      if(!is_array($v) || !array_key_exists(0, $v))
        $properties[$k] = [$v];
    }

    $body = json_encode([
      'type' => ['h-entry'],
      'properties' => $properties
    ]);
  }

  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $endpoint);
  curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    'Authorization: Bearer ' . $access_token,
    'Content-Type: ' . $content_type
  ));
  curl_setopt($ch, CURLOPT_POST, true);
  curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_HEADER, true);
  $response = curl_exec($ch);

  $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
  $header_str = trim(substr($response, 0, $header_size));

  $error = curl_error($ch);
  return array(
    'response' => $response,
    'code' => curl_getinfo($ch, CURLINFO_HTTP_CODE),
    'headers' => parse_headers($header_str),
    'error' => $error,
    'curlinfo' => curl_getinfo($ch)
  );
}

function micropub_get($endpoint, $access_token, $params) {
  $url = parse_url($endpoint);
  if(!k($url, 'query')) {
    $url['query'] = http_build_query($params);
  } else {
    $url['query'] .= '&' . http_build_query($params);
  }
  $endpoint = p3k\url\build_url($url);

  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $endpoint);
  curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    'Authorization: Bearer ' . $access_token,
    'Accept: application/json'
  ));
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  $response = curl_exec($ch);
  $data = array();
  if($response) {
    $data = json_decode($response, true);
  }
  $error = curl_error($ch);
  return array(
    'data' => $data,
    'error' => $error,
    'curlinfo' => curl_getinfo($ch)
  );
}

function parse_headers($headers) {
  $retVal = array();
  $fields = explode("\r\n", preg_replace('/\x0D\x0A[\x09\x20]+/', ' ', $headers));
  foreach($fields as $field) {
    if(preg_match('/([^:]+): (.+)/m', $field, $match)) {
      $match[1] = preg_replace_callback('/(?<=^|[\x09\x20\x2D])./', function($m) {
        return strtoupper($m[0]);
      }, strtolower(trim($match[1])));
      // If there's already a value set for the header name being returned, turn it into an array and add the new value
      $match[1] = preg_replace_callback('/(?<=^|[\x09\x20\x2D])./', function($m) {
        return strtoupper($m[0]);
      }, strtolower(trim($match[1])));
      if(isset($retVal[$match[1]])) {
        $retVal[$match[1]][] = trim($match[2]);
      } else {
        $retVal[$match[1]] = [trim($match[2])];
      }
    }
  }
  return $retVal;
}

// Given an Instagram photo object, return an h-entry array with all the necessary keys.
// This method potentially makes additional HTTP requests to fetch venue and other user information.
function h_entry_from_photo($url, $oldLocationFormat=true, $multiPhoto=false) {
  $entry = array(
    'published' => null,
    'location' => null,
    'category' => array(),
    'content' => '',
    'syndication' => ''
  );

  if($oldLocationFormat)
    $entry['place_name'] = null;

  $xray = new p3k\XRay();
  // Fetch the photo permalink, as well as associated profiles and locations
  $data = $xray->parse($url);

  if(!$data || $data['code'] != 200) {
    return null;
  }

  $photo = $data['data'];

  $entry['published'] = $photo['published'];

  // Add venue information if present
  if(!empty($photo['location'])) {
    $location = $photo['refs'][$photo['location'][0]];

    if($oldLocationFormat) {
      $entry['place_name'] = $location['name'];
      if(!empty($location['latitude'])) {
        $entry['location'] = 'geo:' . $location['latitude'] . ',' . $location['longitude'];
      }
    } else {
      $entry['location'] = [
        'type' => ['h-card'],
        'properties' => [
          'name' => [$location['name']]
        ]
      ];
      if(!empty($location['latitude'])) {
        $entry['location']['properties']['latitude'] = $location['latitude'];
        $entry['location']['properties']['longitude'] = $location['longitude'];
      }
    }
  }

  if(isset($photo['content']))
    $entry['content'] = $photo['content']['text'];

  $entry['syndication'] = $url;

  if(!empty($photo['category']))
    $entry['category'] = $photo['category'];

  // Include the photo/video media URLs
  if(!empty($photo['video'])) {
    $entry['photo'] = $photo['photo'][0];
    $entry['video'] = $photo['video'][0];
  } else {
    if($multiPhoto)
      $entry['photo'] = count($photo['photo']) > 1 ? $photo['photo'] : $photo['photo'][0];
    else
      $entry['photo'] = $photo['photo'][0];
  }

  return $entry;
}

