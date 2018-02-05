<?php

p3k\initdb();
ORM::configure('driver_options', [PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4']);

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

function polling_tier_description($tier) {
  switch($tier) {
    case 4: return '15 minutes';
    case 3: return 'hour';
    case 2: return '6 hours';
    case 1: return '24 hours';
  }
}

function tier_to_seconds($tier) {
  switch($tier) {
    case 4: return 15 * 60;
    case 3: return 60 * 60;
    case 2: return 6 * 60 * 60;
    case 1: return 24 * 60 * 60;
  }
}

function time_until_next_poll($date, $tier) {
  $seconds = tier_to_seconds($tier);
  $last_poll = strtotime($date);
  $next_poll = $last_poll + $seconds;

  $relative = new \RelativeTime\RelativeTime(['suffix'=>false, 'truncate'=>1]);
  return $relative->timeLeft(date('Y-m-d H:i:s', $next_poll));
}

function time_ago($date) {
  $relative = new \RelativeTime\RelativeTime(['suffix'=>true, 'truncate'=>1]);
  return $relative->timeago($date);
}

function download_file($url, $ext='jpg') {
  Logger::$log->info('Downloading temp file', ['url'=>$url]);

  $filename = tempnam(__DIR__.'/../tmp/', 'ig').'.'.$ext;
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

    // If the user has a media endpoint, first upload the files there and replace the URLs in the Micropub request
    if($user->media_endpoint) {
      $photos = [];
      $videos = [];

      if(isset($properties['photo']))
      foreach($properties['photo'] as $igurl) {
        $tmp = download_file($igurl);

        $http = new p3k\HTTP('OwnYourGram');
        $multipart = new p3k\Multipart();
        $multipart->addFile('file', $tmp, 'image/jpeg');

        $response = $http->post($user->media_endpoint, $multipart->data(), 
          ['Authorization: Bearer ' .$access_token, 'Content-type: '.$multipart->contentType()]);

        if(in_array($response['code'], [201,202]) && isset($response['headers']['Location'])) {
          Logger::$log->info('Uploaded photo to media endpoint', ['user'=>$user->url, 'url'=>$response['headers']['Location']]);
          $photos[] = $response['headers']['Location'];
        } else {
          Logger::$log->info('Error uploading photo to media endpoint', ['user'=>$user->url, 'response'=>$response]);
          $photos[] = $igurl;
        }
      }

      if(isset($properties['video']))
      foreach($properties['video'] as $igurl) {
        $tmp = download_file($igurl);

        $http = new p3k\HTTP('OwnYourGram');
        $multipart = new p3k\Multipart();
        $multipart->addFile('file', $tmp, 'video/mp4');

        $response = $http->post($user->media_endpoint, $multipart->data(), 
          ['Authorization: Bearer ' .$access_token, 'Content-type: '.$multipart->contentType()]);

        if(in_array($response['code'], [201,202]) && isset($response['headers']['Location'])) {
          Logger::$log->info('Uploaded video to media endpoint', ['user'=>$user->url, 'url'=>$response['headers']['Location']]);
          $videos[] = $response['headers']['Location'];
        } else {
          $videos[] = $igurl;
        }
      }

      $properties['photo'] = $photos;
      $properties['video'] = $videos;
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

