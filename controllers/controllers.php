<?php

function require_login(&$app) {
  if(!array_key_exists('user_id', $_SESSION)) {
    $app->redirect('/');
    return false;
  } else {
    return ORM::for_table('users')->find_one($_SESSION['user_id']);
  }
}

$app->get('/auth/instagram', function() use($app) {
  if(require_login($app)) {
    $html = render('instagram-auth', array(
      'title' => 'Connect Instagram'
    ));
    $app->response()->body($html);
  }
});

$app->get('/auth/instagram-start', function() use($app) {
  if(require_login($app)) {
    $app->redirect('https://api.instagram.com/oauth/authorize/?client_id='.Config::$instagramClientID.'&redirect_uri='.Config::instagramRedirectURI().'&response_type=code&scope=comments');
  }
});

$app->get('/auth/instagram-callback', function() use($app) {
  if(require_login($app)) {

    $params = $app->request()->params();

    if(!array_key_exists('code', $params)) {
      // Error authorizing
      $app->redirect('/auth/instagram');
    } else {
      $ch = curl_init();
      curl_setopt($ch, CURLOPT_URL, 'https://api.instagram.com/oauth/access_token');
      curl_setopt($ch, CURLOPT_POST, true);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(array(
        'client_id' => Config::$instagramClientID,
        'client_secret' => Config::$instagramClientSecret,
        'redirect_uri' => Config::instagramRedirectURI(),
        'grant_type' => 'authorization_code',
        'code' => $params['code']
      )));
      $response = curl_exec($ch);
      $token = json_decode($response);

      if(property_exists($token, 'access_token')) {
        $_SESSION['instagram'] = $token;

        // Update the user record with the instagram access token
        $user = ORM::for_table('users')->find_one($_SESSION['user_id']);
        $user->instagram_access_token = $token->access_token;
        $user->instagram_user_id = $token->user->id;
        $user->instagram_response = $response;
        $user->save();
      } else {
        $app->redirect('/auth/instagram');
      }
    }

    $app->redirect('/dashboard');
  }
});


$app->get('/dashboard', function() use($app) {
  if($user=require_login($app)) {

    // If the user hasn't connected their Instagram account yet, redirect to the page to auth instagram
    if($user->instagram_access_token == '') {
      $app->redirect('/auth/instagram-start');
    } else {

      // Go fetch the latest Instagram photo and show it to them for testing the micropub endpoint
      $photo = IG\get_latest_photo($user);
      if($photo) {
        $photo = $photo[2];

        $date = date('c', $photo->created_time);

        // Look up the timezone of the photo if location data is present
        if(property_exists($photo, 'location') && $photo->location) {
          try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, 'http://timezone-api.geoloqi.com/timezone/'.$photo->location->latitude.'/'.$photo->location->longitude);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $response = curl_exec($ch);
            $tz = @json_decode($response);
            if($tz) {
              $date = date('Y-m-d\TH:i:s').$tz->offset;
            }
          } catch(Exception $e) {
          }
        }
      }

      $html = render('dashboard', array(
        'title' => 'Dashboard',
        'photo' => $photo,
        'date' => $date
      ));
      $app->response()->body($html);
    }
  }
});

$app->post('/micropub/test', function() use($app) {
  if($user=require_login($app)) {
    $params = $app->request()->params();

    // Download the file to a temp folder
    $filename = tempnam(sys_get_temp_dir(), 'ig');
    $fp = fopen($filename, 'w+');
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $params['url']);
    curl_setopt($ch, CURLOPT_FILE, $fp);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_exec($ch);
    curl_close($ch);
    fclose($fp);

    // Now send to the micropub endpoint
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $user->micropub_endpoint);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
      'Authorization: Bearer '.$user->micropub_access_token
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
    $response = curl_exec($ch);

    unlink($filename);

    $app->response()->body(json_encode(array(
      'response' => $response
    )));
  }
});

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

  // Look up the access token for the user ID


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


