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
  try {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'http://atlas.p3k.io/api/timezone?latitude='.$lat.'&longitude='.$lng);
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

function micropub_post($user, $params, $photo_filename=false, $video_filename=false) {

  $endpoint = $user->micropub_endpoint;
  $access_token = $user->micropub_access_token;

  if(k($params, 'name'))
    $properties['name'] = $params['name'];
  if(k($params, 'content'))
    $properties['content'] = $params['content'];
  if(k($params, 'category'))
    $properties['category'] = $params['category'];
  if(k($params, 'place_name'))
    $properties['place_name'] = $params['place_name'];
  if(k($params, 'location'))
    $properties['location'] = $params['location'];
  if(k($params, 'published'))
    $properties['published'] = $params['published'];
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

    $multipart = new p3k\Multipart();

    $multipart->addArray($postfields);

    if($photo_filename)
      $multipart->addFile('photo', $photo_filename, 'image/jpeg');

    if($video_filename)
      $multipart->addFile('video', $video_filename, 'video/mp4');

    $body = $multipart->data();
    $content_type = $multipart->contentType();
  } else {
    $content_type = 'application/json';

    if($photo_filename)
      $properties['photo'] = $photo_filename;

    if($video_filename)
      $properties['video'] = $video_filename;

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
  $endpoint = build_url($url);

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
function h_entry_from_photo($url, $oldLocationFormat=true) {
  $entry = array(
    'published' => null,
    'location' => null,
    'category' => array(),
    'content' => '',
    'syndication' => ''
  );

  if($oldLocationFormat)
    $entry['place_name'] = null;

  // First fetch the photo permalink
  $photo = IG\get_photo($url);

  $entry['published'] = date('c', $photo['date']);

  // Add venue information if present
  if(array_key_exists('location', $photo) && $photo['location']) {

    if($oldLocationFormat) {
      $entry['place_name'] = $photo['location']['name'];
    } else {
      $entry['location'] = [
        'type' => ['h-card'],
        'properties' => [
          'name' => [$photo['location']['name']]
        ]
      ];
    }

    $venue = IG\get_venue($photo['location']['id']);
    if($venue && array_key_exists('lat', $venue)) {
      if($tz = get_timezone($venue['lat'], $venue['lng'])) {
        $d = DateTime::createFromFormat('U', $photo['date']);
        $d->setTimeZone($tz);
        $entry['published'] = $d->format('c');

        if(!$oldLocationFormat) {
          $entry['location']['properties']['latitude'] = [$venue['lat']];
          $entry['location']['properties']['longitude'] = [$venue['lng']];
        } else {
          $entry['location'] = 'geo:' . $venue['lat'] . ',' . $venue['lng'];
        }
      }
    }
  }

  if(array_key_exists('caption', $photo) && $photo['caption']) {
    if(preg_match_all('/#([a-z0-9_-]+)/i', $photo['caption'], $matches)) {
      foreach($matches[1] as $match) {
        $entry['category'][] = $match;
      }
    }

    $entry['content'] = $photo['caption'];
  }

  $entry['syndication'] = $url;

  // Add person-tags to the category array
  if(array_key_exists('usertags', $photo) && $photo['usertags']['nodes']) {
    foreach($photo['usertags']['nodes'] as $tag) {
      // Fetch the user profile
      try {
        if($profile = IG\get_profile($tag['user']['username'])) {
          if($profile['external_url'])
            $entry['category'][] = $profile['external_url'];
          else
            $entry['category'][] = 'https://instagram.com/' . $tag['user']['username'];
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
      } catch(Exception $e) {
        $entry['category'][] = 'https://instagram.com/' . $tag['user']['username'];
      }
    }
  }

  // Include the photo/video media URLs
  $entry['photo'] = $photo['display_src'];

  if(array_key_exists('is_video', $photo) && $photo['is_video']) {
    $entry['video'] = $photo['video_url'];
  }

  return $entry;
}

function build_url($parsed_url) {
  $scheme   = isset($parsed_url['scheme']) ? $parsed_url['scheme'] . '://' : '';
  $host     = isset($parsed_url['host']) ? $parsed_url['host'] : '';
  $port     = isset($parsed_url['port']) ? ':' . $parsed_url['port'] : '';
  $user     = isset($parsed_url['user']) ? $parsed_url['user'] : '';
  $pass     = isset($parsed_url['pass']) ? ':' . $parsed_url['pass']  : '';
  $pass     = ($user || $pass) ? "$pass@" : '';
  $path     = isset($parsed_url['path']) ? $parsed_url['path'] : '';
  $query    = isset($parsed_url['query']) ? '?' . $parsed_url['query'] : '';
  $fragment = isset($parsed_url['fragment']) ? '#' . $parsed_url['fragment'] : '';
  return "$scheme$user$pass$host$port$path$query$fragment";
}

