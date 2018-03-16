<?php
namespace IG;
use DOMDocument, DOMXPath;
use Config;
use Logger;

function http_headers() {
  return [
    'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
    'Accept-Language: en-US,en;q=0.8',
    'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_11_4) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/49.0.2623.110 Safari/537.36',
  ];
}

function get_user_photos($username, $ignoreCache=false) {
  $cacheKey = Config::$hostname.'::userfeed::'.$username;
  $cacheTime = 60*15; # cache feeds for 15 minutes

  if(Config::$cacheIGRequests && !$ignoreCache) {
    if($data = \p3k\redis()->get($cacheKey)) {
      return json_decode($data, true);
    }
  }

  Logger::$log->info('Fetching user timeline', ['username'=>$username]);

  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, 'https://www.instagram.com/'.$username.'/?__a=1');
  curl_setopt($ch, CURLOPT_HTTPHEADER, http_headers());
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  $response = curl_exec($ch);

  $data = json_decode($response, true);

  $latest = 0;

  $items = [];
  if($data) {
    foreach($data['graphql']['user']['edge_owner_to_timeline_media']['edges'] as $item) {
      $item = $item['node'];
      $items[] = [
        'url' => 'https://www.instagram.com/p/'.$item['shortcode'].'/',
        'published' => date('Y-m-d H:i:s', $item['taken_at_timestamp'])
      ];
      if($item['taken_at_timestamp'] > $latest)
        $latest = $item['taken_at_timestamp'];
    }
  }

  $response = [
    'username' => $username,
    'items' => $items,
    'latest' => $latest,
  ];
  if(count($items))
    \p3k\redis()->setex($cacheKey, $cacheTime, json_encode($response));
  return $response;
}

function get_profile($username, $ignoreCache=false) {
  $cacheKey = Config::$hostname.'::profile::'.$username;
  $cacheTime = 86400 * 7; # cache profiles for 7 days

  if(Config::$cacheIGRequests && !$ignoreCache) {
    if($data = \p3k\redis()->get($cacheKey)) {
      return json_decode($data, true);
    }
  }

  Logger::$log->info('Fetching Instagram profile', ['username'=>$username]);

  $ch = curl_init('https://www.instagram.com/'.$username.'/?__a=1');
  curl_setopt($ch, CURLOPT_HTTPHEADER, http_headers());
  curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  $response = curl_exec($ch);
  $profile = @json_decode($response, true);
  if($profile && isset($profile['graphql']['user'])) {
    $user = $profile['graphql']['user'];
    \p3k\redis()->setex($cacheKey, $cacheTime, json_encode($user));
    return $user;
  } else {
    return null;
  }
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
