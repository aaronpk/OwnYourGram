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
  if(!preg_match('/([^ <>]+)@ownyourgram\.com/', $params['To'], $match)) {
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

  /*
  ob_start();
  print_r($params);
  print_r($_POST);
  print_r($_FILES);
  foreach($_FILES as $f) {
    echo "=-=-=-=-=\n";
    echo file_get_contents($f['tmp_name'])."\n";
  }
  $debug = ob_get_clean();
  file_put_contents('last.txt', $debug);
  */

  $data = array(
    'published' => (k($params, 'Date') ? date('c', strtotime(k($params, 'Date'))) : date('c'))
  );

  if(k($params, 'Subject'))
    $data['name'] = k($params, 'Subject');

  if(k($params, 'body-plain'))
    $data['content'] = k($params, 'body-plain');

  // Handle attachments
  $filename = false;

  foreach($_FILES as $file) {
    // If a photo was included, set the filename to the downloaded file
    if(preg_match('/image/', $file['type'])) {
      $filename = $file['tmp_name'];
    }

    // Sometimes MMSs are sent with a txt file attached instead of in the body
    if($file['type'] == 'text/plain') {
      $content = trim(file_get_contents($file['tmp_name']));
      if($content) {
        $data['content'] = $content;
      }
    }
  }

  // Set tags for any hashtags used in the body
  if(k($data,'content') && preg_match_all('/#([^ ]+)/', $data['content'], $matches)) {
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

