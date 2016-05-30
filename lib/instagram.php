<?php
namespace IG;
use DOMDocument, DOMXPath;
use Config;

function http_headers() {
  return [
    'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
    'Accept-Language: en-US,en;q=0.8',
    'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_11_4) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/49.0.2623.110 Safari/537.36',
  ];
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

function get_photo($url, $ignoreCache=false) {
  $cacheKey = Config::$hostname.'::photo::'.$url;
  $cacheTime = 86400; # cache photos for 1 day

  if(Config::$cacheIGRequests && !$ignoreCache) {
    if($data = redis()->get($cacheKey)) {
      return json_decode($data, true);
    }
  }

  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $url);
  curl_setopt($ch, CURLOPT_HTTPHEADER, http_headers());
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  $response = curl_exec($ch);

  $data = extract_ig_data($response);

  if($data && is_array($data) && array_key_exists('entry_data', $data)) {
    if(is_array($data['entry_data']) && array_key_exists('PostPage', $data['entry_data'])) {
      $post = $data['entry_data']['PostPage'];
      if(is_array($post) && array_key_exists(0, $post) && array_key_exists('media', $post[0])) {
        $media = $post[0]['media'];

        if(Config::$cacheIGRequests)
          redis()->setex($cacheKey, $cacheTime, json_encode($media));

        return $media;
      }
    }
  }

  return $null;
}

function get_profile($username, $ignoreCache=false) {
  $cacheKey = Config::$hostname.'::profile::'.$username;
  $cacheTime = 86400 * 7; # cache profiles for 7 days

  if(Config::$cacheIGRequests && !$ignoreCache) {
    if($data = redis()->get($cacheKey)) {
      return json_decode($data, true);
    }
  }

  $ch = curl_init('https://www.instagram.com/'.$username.'/?__a=1');
  curl_setopt($ch, CURLOPT_HTTPHEADER, http_headers());
  curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  $response = curl_exec($ch);
  $profile = @json_decode($response, true);
  if($profile) {
    redis()->setex($cacheKey, $cacheTime, json_encode($profile));
    return $profile;
  } else {
    return null;
  }
}

function get_venue($id, $ignoreCache=false) {
  $cacheKey = Config::$hostname.'::venue::'.$id;
  $cacheTime = 86400 * 360; # cache venues for 360 days

  if(Config::$cacheIGRequests && !$ignoreCache) {
    if($data = redis()->get($cacheKey)) {
      return json_decode($data, true);
    }
  }

  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, 'https://www.instagram.com/explore/locations/'.$id.'/');
  $headers = http_headers();
  # need to set a referer, otherwise IG returns a blank page
  $headers[] = 'Referer: https://www.instagram.com/'; 
  curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  $response = curl_exec($ch);

  $data = extract_ig_data($response);

  if($data && is_array($data) && array_key_exists('entry_data', $data)) {
    if(is_array($data['entry_data']) && array_key_exists('LocationsPage', $data['entry_data'])) {
      $data = $data['entry_data']['LocationsPage'];
      if(is_array($data) && array_key_exists(0, $data) && array_key_exists('location', $data[0])) {
        $location = $data[0]['location'];

        # we don't need these and they're huge, so don't save them
        unset($location['media']);
        unset($location['top_posts']);

        if(Config::$cacheIGRequests) {
          redis()->setex($cacheKey, $cacheTime, json_encode($location));
        }
        
        return $location;
      }
    }
  }

  return null;
}

function extract_ig_data($html) {
  $doc = new DOMDocument();
  @$doc->loadHTML($html);

  if(!$doc) {
    return null;
  }

  $xpath = new DOMXPath($doc);

  $data = null;

  foreach($xpath->query('//script') as $script) {
    if(preg_match('/window\._sharedData = ({.+});/', $script->textContent, $match)) {
      $data = json_decode($match[1], true);
    }
  }

  return $data;  
}

