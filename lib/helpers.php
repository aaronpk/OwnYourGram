<?php

p3k\initdb();
ORM::configure('driver_options', [PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4']);

class Logger {
  public static $log;

  public static function init() {
    self::$log = new Monolog\Logger('ownyourgram');
    self::$log->pushHandler(new Monolog\Handler\StreamHandler(dirname(__FILE__).'/../scripts/logs/ownyourgram.log', Monolog\Logger::INFO));
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

  $filename = tempnam(__DIR__.'/../tmp/', 'ig').'.'.$ext;
  $fp = fopen($filename, 'w+');

  Logger::$log->info('Downloading temp file', ['url'=>$url, 'tmpfile'=>$filename]);

  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $url);
  curl_setopt($ch, CURLOPT_FILE, $fp);
  curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
  curl_exec($ch);
  curl_close($ch);
  fclose($fp);
  return $filename;
}

function copy_to_media_endpoint($user, $file, $type) {
  if(preg_match('/https?:\/\//', $file)) {
    $tmp = download_file($file);
  } else {
    // This fallback case should be fixed. This means this script failed to download the file,
    // but the URL will just be passed on to the multipart library which will try to download it
    // itself, probably failing again.
    $tmp = $file;
  }

  $http = new p3k\HTTP('OwnYourGram');
  $multipart = new p3k\Multipart();
  $multipart->addFile('file', $tmp, $type);

  $response = $http->post($user->media_endpoint, $multipart->data(),
    ['Authorization: Bearer ' .$user->micropub_access_token, 'Content-type: '.$multipart->contentType()]);

  if(in_array($response['code'], [201,202]) && isset($response['headers']['Location'])) {
    Logger::$log->info('Uploaded file to media endpoint', ['user'=>$user->url, 'url'=>$response['headers']['Location']]);
    $media_url = $response['headers']['Location'];
  } else {
    Logger::$log->info('Error uploading file to media endpoint', ['user'=>$user->url, 'response'=>$response]);
    $media_url = false;
  }

  return $media_url;
}

function micropub_post($user, $params) {

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

    if($user->media_endpoint) {

      $media_endpoint_error = false;

      if(isset($params['photo'])) {
        $photos = [];
        if(!is_array($params['photo'])) $params['photo'] = [$params['photo']];
        foreach($params['photo'] as $u) {
          $loc = copy_to_media_endpoint($user, $u, 'image/jpeg');
          if($loc) {
            $photos[] = $loc;
          } else {
            $media_endpoint_error = true;
          }
        }
        if(count($photos) == 1) $photos = $photos[0];
        $postfields['photo'] = $photos;
      }

      if(isset($params['video'])) {
        $videos = [];
        if(!is_array($params['video'])) $params['video'] = [$params['video']];
        foreach($params['video'] as $u) {
          $loc = copy_to_media_endpoint($user, $u, 'video/mp4');
          if($loc) {
            $videos[] = $loc;
          } else {
            $media_endpoint_error = true;
          }
        }
        if(count($videos) == 1) $videos = $videos[0];
        $postfields['video'] = $videos;
      }

      $content_type = 'application/x-www-form-urlencoded';
      $body = http_build_query($postfields);
      $body = preg_replace('/%5B[0-9]+%5D=/simU', '%5B%5D=', $body);
    }

    if(!$user->media_endpoint || $media_endpoint_error) {
      // Send as multipart upload if there is no media endpoint or if there was a media endpoint error

      $multipart = new p3k\Multipart();

      $multipart->addArray($postfields);

      if(isset($params['photo'])) {
        if(!is_array($params['photo'])) $params['photo'] = [$params['photo']];
        foreach($params['photo'] as $u) {
          $fn = download_file($u);
          $key = count($params['photo']) == 1 ? 'photo' : 'photo[]';
          $multipart->addFile($key, $fn, 'image/jpeg');
        }
      }

      if(isset($params['video'])) {
        if(!is_array($params['video'])) $params['video'] = [$params['video']];
        foreach($params['video'] as $u) {
          $fn = download_file($u);
          $key = count($params['video']) == 1 ? 'video' : 'video[]';
          $multipart->addFile($key, $fn, 'video/mp4');
        }
      }

      $body = $multipart->data();
      $content_type = $multipart->contentType();
    }

  } else {
    $content_type = 'application/json';

    if(isset($params['photo']))
      $properties['photo'] = $params['photo'];

    if(isset($params['video']))
      $properties['video'] = $params['video'];

    // Convert everything to an array
    foreach($properties as $k=>$v) {
      if(!is_array($v) || !array_key_exists(0, $v))
        $properties[$k] = [$v];
    }

    // If the user has a media endpoint, first upload the files there and replace the URLs in the Micropub request
    if($user->media_endpoint) {
      $photos = [];
      $videos = [];

      if(isset($properties['photo'])) {
        foreach($properties['photo'] as $igurl) {
          $loc = copy_to_media_endpoint($user, $igurl, 'image/jpeg');
          if($loc) {
            $photos[] = $loc;
          } else {
            $photos[] = $igurl;
          }
        }
        $properties['photo'] = $photos;
      }

      if(isset($properties['video'])) {
        foreach($properties['video'] as $igurl) {
          $loc = copy_to_media_endpoint($user, $igurl, 'video/mp4');
          if($loc) {
            $videos[] = $loc;
          } else {
            $videos[] = $igurl;
          }
        }
        $properties['video'] = $videos;
      }
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
        $entry['location']['properties']['latitude'] = [(string)$location['latitude']];
        $entry['location']['properties']['longitude'] = [(string)$location['longitude']];
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
    $entry['photo'] = $photo['photo'];
    $entry['video'] = $photo['video'];
  } else {
    $entry['photo'] = $photo['photo'];
  }

  return $entry;
}

