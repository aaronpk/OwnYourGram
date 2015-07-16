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
        // Remove the Instagram account info from a past user account if it already exists
        ORM::for_table('users')->where('instagram_user_id', $token->user->id)->find_result_set()
          ->set('instagram_user_id','')
          ->set('instagram_access_token','')
          ->save();

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
      try {
        if($photos = IG\get_latest_photos($user)) {
          $entry = h_entry_from_photo($user, $photos[0]);
          $photo_url = $photos[0]->images->standard_resolution->url;
        } else {
          $entry = false;
          $photo_url = false;
        }
      } catch(IG\AccessTokenException $e) {
        $user->instagram_access_token = '';
        $user->instagram_response = '';
        $user->save();
        $app->redirect('/auth/instagram-start');
      } catch(Exception $e) {
        $html = render('auth_error', array(
          'title' => 'Error',
          'error' => 'Error',
          'errorDescription' => $e->getMessage()
        ));
        $app->response()->body($html);
        return;
      }

      $test_response = '';
      if($user->last_micropub_response) {
        try {
          if(@json_decode($user->last_micropub_response)) {
            $d = json_decode($user->last_micropub_response);
            $test_response = $d->response;
          }
        } catch(Exception $e) {
        }
      }

      $html = render('dashboard', array(
        'title' => 'Dashboard',
        'entry' => $entry,
        'photo_url' => $photo_url,
        'micropub_endpoint' => $user->micropub_endpoint,
        'test_response' => $test_response,
        'user' => $user
      ));
      $app->response()->body($html);
    }
  }
});

$app->post('/prefs/array', function() use($app) {
  if($user=require_login($app)) {
    $user->send_category_as_array = 1;
    $user->save();

    $app->response()->body(json_encode(array(
      'result' => 'ok'
    )));
  }
});

$app->post('/micropub/test', function() use($app) {
  if($user=require_login($app)) {
    $params = $app->request()->params();

    if($user->send_category_as_array != 1) {
      if(is_array($params['category']))
        $params['category'] = implode(',', $params['category']);
    }

    // Download the file to a temp folder
    $filename = download_file($params['url']);

    // Now send to the micropub endpoint
    $r = micropub_post($user->micropub_endpoint, $user->micropub_access_token, $params, $filename);
    $response = $r['response'];

    #unlink($filename);

    $user->last_micropub_response = json_encode($r);

    // Check the response and look for a "Location" header containing the URL
    if($response && preg_match('/Location: (.+)/', $response, $match)) {
      $location = $match[1];
      $user->micropub_success = 1;
    } else {
      $location = false;
    }

    $user->save();

    $app->response()->body(json_encode(array(
      'response' => htmlspecialchars($response),
      'location' => $location,
      'error' => $r['error'],
      'curlinfo' => $r['curlinfo'],
      'filename' => $filename
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


