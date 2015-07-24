<?php

$app->post('/instagram/callback', function() use($app) {
  // Will be something like this
  /*
  [
    {
        "subscription_id": "1",
        "object": "user",
        "object_id": "1234",
        "changed_aspect": "media",
        "time": 1297286541
    },
    {
        "subscription_id": "2",
        "object": "tag",
        "object_id": "nofilter",
        "changed_aspect": "media",
        "time": 1297286541
    },
    ...
  ]
  */

  // Queue a job to process this request
  bs()->putInTube(Config::$hostname.'-worker', $app->request()->getBody());
});

// Respond to the callback challenge from Instagram
// http://instagram.com/developer/realtime/
$app->get('/instagram/callback', function() use($app) {
  $params = $app->request()->params();
  if(array_key_exists('hub_challenge', $params))
    $app->response()->body($params['hub_challenge']);
  else
    $app->response()->body('error');
});

$app->post('/mailgun', function() use($app) {
  $params = $app->request()->params();

  // Find the user for this email
  if(!preg_match('/(.+)@ownyourgram\.com/', $params['to'], $match)) {
    $app->response()->body('invalid recipient');
    return;
  }

  $user = ORM::for_table('users')->where('email_username', $match[1])->find_one();
  if(!$user) {
    $app->response()->body('user not found');
    return;
  }  

  if(!$user->micropub_access_token) {
    $app->response()->body('user has no access token');
    return;
  }

  $data = array();

  if(k($params, 'subject'))
    $data['name'] = k($params, 'subject');

  $data['content'] = k($params, 'body-plain');

  // Set tags for any hashtags used in the body
  if(preg_match_all('/#([^ ]+)/', $text, $matches)) {
    $tags = array();
    foreach($matches[1] as $m)
      $tags[] = $m;
    if($tags) {
      if($user->send_category_as_array != 1) {
        $data['category'] = $tags;
      } else {
        $data['category'] = implode(',', $tags);
      }
    }
  }

  // Find if there's a photo in the email
  $filename = false;
  foreach($_FILES as $file) {
    if(preg_match('/image/', $file['type'])) {
      $filename = $file['tmp_name'];
    }
  }

  $r = micropub_post($user->micropub_endpoint, $user->micropub_access_token, $data, $filename);
  $response = $r['response'];

  $user->last_micropub_response = json_encode($r);

  if($response && preg_match('/Location: (.+)/', $response, $match)) {
    $location = $match[1];
    $user->micropub_success = 1;
  } else {
    $location = false;
  }

  $user->save();

  $app->response()->body('created post');
});

