<?php
namespace IG;
use DOMDocument, DOMXPath;
use Config;
use Logger;

function get_user_photos($username, $ignoreCache=false) {
  $cacheKey = Config::$hostname.'::userfeed::'.$username;
  $cacheTime = 60*15; # cache feeds for 15 minutes

  if(Config::$redis && Config::$cacheIGRequests && !$ignoreCache) {
    if($data = \p3k\redis()->get($cacheKey)) {
      return json_decode($data, true);
    }
  }

  Logger::$log->info('Fetching user timeline', ['username'=>$username]);

  if(Config::$xray) {
    $http = new \p3k\HTTP(user_agent());
    $response = $http->get('https://'.Config::$xray.'/parse?'.http_build_query([
      'url' => 'https://www.instagram.com/'.$username,
      'expect' => 'feed',
      'instagram_session' => Config::$igCookie,
    ]));
    $data = json_decode($response['body'], true);
  } else {
    $xray = new \p3k\XRay();
    $xray->http = new \p3k\HTTP(user_agent());
    $data = $xray->parse('https://www.instagram.com/'.$username, [
      'expect' => 'feed',
      'instagram_session' => Config::$igCookie,
    ]);
  }

  if(isset($data['data']['items'])) {
    $items = $data['data']['items'];
  } else {
    $items = [];
  }

  Logger::$log->info('Photos found:', ['username'=>$username, 'photos'=>count($items)]);

  $latest = 0;

  if(count($items)) {
    foreach($items as $photo) {
      if(strtotime($photo['published']) > $latest)
        $latest = strtotime($photo['published']);
    }
  }

  $response = [
    'username' => $username,
    'items' => $items,
    'latest' => $latest,
  ];
  if(Config::$redis && count($items))
    \p3k\redis()->setex($cacheKey, $cacheTime, json_encode($response));

  // Don't store the returned URL in the cache
  $response['url'] = $data['url'];

  return $response;
}

function get_profile($username, $ignoreCache=false) {
  $cacheKey = Config::$hostname.'::profile::'.$username;
  $cacheTime = 86400 * 7; # cache profiles for 7 days

  if(Config::$redis && Config::$cacheIGRequests && !$ignoreCache) {
    if($data = \p3k\redis()->get($cacheKey)) {
      return json_decode($data, true);
    }
  }

  Logger::$log->info('Fetching Instagram profile', ['username'=>$username]);

  if(Config::$xray) {
    $http = new \p3k\HTTP(user_agent());
    $response = $http->get('https://'.Config::$xray.'/parse?'.http_build_query([
      'url' => 'https://www.instagram.com/'.$username,
      'instagram_session' => Config::$igCookie,
    ]));
    $data = json_decode($response['body'], true);
  } else {
    $xray = new \p3k\XRay();
    $xray->http = new \p3k\HTTP(user_agent());
    $data = $xray->parse('https://www.instagram.com/'.$username, [
      'instagram_session' => Config::$igCookie,
    ]);
  }

  if(isset($data['data']['type']) && $data['data']['type'] == 'card') {
    if(Config::$redis)
      \p3k\redis()->setex($cacheKey, $cacheTime, json_encode($data['data']));

    return $data['data'];
  }

  return null;
}

// Check that a user's profile contains a link to the given website
function profile_matches_website($username, $url, $profile_data=false) {
  if($profile_data)
    $profile = $profile_data;
  else
    $profile = get_profile($username, true); // always ignore cache

  $success = false;

  if($profile) {
    if(isset($profile['url']) && $profile['url'] == $url)
      $success = true;
    elseif(isset($profile['note']) && strpos($profile['note'], $url) !== false)
      $success = true;
  }

  return $success;
}
