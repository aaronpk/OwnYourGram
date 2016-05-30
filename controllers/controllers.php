<?php

function require_login(&$app) {
  if(!array_key_exists('user_id', $_SESSION)) {
    $app->redirect('/');
    return false;
  } else {
    return ORM::for_table('users')->find_one($_SESSION['user_id']);
  }
}

$app->get('/', function($format='html') use($app) {
  $res = $app->response();

  // Total number of photos
  $total_photos = ORM::for_table('users')
    ->sum('photo_count');

  $total_users = ORM::for_table('users')
    ->where_gt('photo_count', 0)
    ->where_not_null('last_micropub_response')
    ->count();

  // Find the top ranked users this week
  $date = new DateTime();
  while($date->format('D') != 'Sun') {
    $date->modify('-1 day');
  }

  $users = ORM::for_table('users')
    ->where('micropub_success', 1)
    ->where_not_null('last_instagram_img_url')
    ->where_gte('last_photo_date', $date->format('Y-m-d'))
    ->where_gt('photo_count_this_week', 0)
    ->order_by_desc('photo_count_this_week')
    ->find_many();

  ob_start();
  render('index', array(
    'title' => 'OwnYourGram',
    'meta' => '',
    'users' => $users,
    'total_photos' => $total_photos,
    'total_users' => $total_users
  ));
  $html = ob_get_clean();
  $res->body($html);
});


$app->get('/creating-a-token-endpoint', function() use($app) {
  $app->redirect('https://indiewebcamp.com/token-endpoint', 302);
});
$app->get('/creating-a-micropub-endpoint', function() use($app) {
  $html = render('creating-a-micropub-endpoint', array('title' => 'Creating a Micropub Endpoint'));
  $app->response()->body($html);
});

$app->get('/instagram', function() use($app) {
  if($user=require_login($app)) {

    // Check the user's home page to see if there is a rel=me link to an instagram profile
    $instagram_username = false;
    $homepage = Mf2\fetch($user->url);
    if($homepage && array_key_exists('me', $homepage['rels'])) {
      foreach($homepage['rels']['me'] as $rel) {
        if(preg_match('/https?:\/\/(?:www\.)?instagram\.com\/([^\/]+)/', $rel, $match)) {
          $instagram_username = $match[1];
        }
      }
    }

    $_SESSION['instagram_username'] = $instagram_username;

    $html = render('instagram', array(
      'title' => 'Instagram',
      'user' => $user,
      'instagram_username' => $instagram_username
    ));
    $app->response()->body($html);
  }
});

$app->get('/instagram/verify', function() use($app) {
  if($user=require_login($app)) {
    if(!array_key_exists('instagram_username', $_SESSION)) {
      $app->redirect('/instagram', 302);
      return;
    }

    // Check the instagram account looking for the link back to the user's home page
    $profile = IG\get_profile($_SESSION['instagram_username']);

    $success = false;

    if($profile && array_key_exists('user', $profile)) {
      if($profile['user']['external_url'] == $user->url
        || strpos($profile['user']['biography'], $user->url) !== false) {
        $success = true;
      }
    }

    if($success) {
      $user->instagram_username = $_SESSION['instagram_username'];
    } else {
      $user->instagram_username = null;
    }
    $user->save();

    $html = render('instagram-verify', array(
      'title' => 'Instagram',
      'user' => $user,
      'instagram_username' => $_SESSION['instagram_username'],
      'success' => $success
    ));
    $app->response()->body($html);

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

	if($params['video_url'])
	  $video_filename = download_file($params['video_url']);
	else
	  $video_filename = false;

    // Now send to the micropub endpoint
    $r = micropub_post($user->micropub_endpoint, $user->micropub_access_token, $params, $filename, $video_filename);
    $response = $r['response'];

    #unlink($filename);

    $user->last_micropub_response = json_encode($r);

    // Check the response and look for a "Location" header containing the URL
    if($response && preg_match('/Location: (.+)/', $response, $match)) {
      $location = $match[1];
      $user->micropub_success = 1;
      $user->last_micropub_url = $location;
      $user->photo_count = $user->photo_count + 1;
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
