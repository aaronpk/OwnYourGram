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
  $date->modify('-7 days');

  $users = ORM::for_table('users')
    ->raw_query('SELECT users.*, COUNT(1) AS num
      FROM users
      JOIN photos ON users.id = photos.user_id
      WHERE photos.published > :date
        AND photos.canonical_url != ""
      GROUP BY users.id
      ORDER BY num DESC', ['date' => $date->format('Y-m-d H:i:s')])
    ->find_many();

  render('index', array(
    'title' => 'OwnYourGram',
    'meta' => '',
    'users' => $users,
    'total_photos' => $total_photos,
    'total_users' => $total_users,
    'signed_in' => isset($_SESSION['user_id'])
  ));
});

$app->get('/dashboard', function() use($app) {
  $app->redirect('/photos', 302);
});

$app->get('/settings', function() use($app) {
  if($user=require_login($app)) {

    if(!$user->instagram_username) {
      $app->redirect('/instagram', 302);
      return;
    }

    $rules = ORM::for_table('syndication_rules')
      ->where('user_id', $user->id)
      ->order_by_asc('syndicate_to_name')
      ->order_by_asc('match')
      ->find_many();

    render('settings', array(
      'title' => 'Settings - OwnYourGram',
      'user' => $user,
      'rules' => $rules,
    ));
  }
});

$app->get('/photos', function() use($app) {
  if($user=require_login($app)) {

    $photoQuery = ORM::for_table('photos')
      ->where('user_id', $user->id)
      ->order_by_desc('published')
      ->limit(20)
      ->find_many();

    $photos = [];
    foreach($photoQuery as $photo) {
      $photos[] = [
        'instagram_url' => $photo->instagram_url,
        'instagram_img' => $photo->instagram_img,
        'instagram_img_list' => json_decode($photo->instagram_img_list),
        'video' => (isset($entry['video']) ? $entry['video'] : false),
        'canonical_url' => $photo->canonical_url,
        'id' => $photo->id,
        'data' => json_decode($photo->instagram_data, true),
      ];
    }

    render('photos', array(
      'title' => 'Import Photos - OwnYourGram',
      'user' => $user,
      'photos' => $photos,
    ));
  }
});

$app->get('/settings/syndication-targets.json', function() use($app) {
  if($user=require_login($app)) {

    $targets = json_decode($user->micropub_syndication_targets);

    $app->response()->header('Content-Type', 'application/json');
    $app->response()->body(json_encode([
      'targets' => $targets
    ]));
  }
});

$app->post('/settings/syndication-targets.json', function() use($app) {
  if($user=require_login($app)) {

    $response = micropub_get($user->micropub_endpoint, $user->micropub_access_token, ['q'=>'syndicate-to']);

    $targets = [];
    $error = false;

    if($response['data']) {
      if(array_key_exists('syndicate-to', $response['data'])) {
        $raw = $response['data']['syndicate-to'];

        foreach($raw as $t) {
          if(array_key_exists('name', $t) && array_key_exists('uid', $t)) {
            $targets[] = $t;
          }
        }

        $user->micropub_syndication_targets = json_encode($targets);
        $user->save();
      } else {
        $error = 'Your endpoint did not return a "syndicate-to" property in the response';
      }
    } else {
      $error = $response['error'];
    }

    $app->response()->header('Content-Type', 'application/json');
    $app->response()->body(json_encode([
      'targets' => $targets,
      'error' => $error
    ]));
  }
});

$app->post('/settings/syndication-rules.json', function() use($app){
  if($user=require_login($app)) {
    $params = $app->request()->params();

    if($params['action'] == 'create') {
      $rule = ORM::for_table('syndication_rules')->create();
      $rule->user_id = $user->id;
      $rule->match = $params['keyword'];
      $rule->syndicate_to = $params['target'];
      $rule->syndicate_to_name = $params['target_name'];
      $rule->save();
    } elseif($params['action'] == 'delete') {
      $rule = ORM::for_table('syndication_rules')
        ->where('user_id', $user->id)
        ->where('id', $params['id'])
        ->delete_many();
    }

    $app->response()->header('Content-Type', 'application/json');
    $app->response()->body(json_encode([
      'result' => 'ok'
    ]));
  }
});

$app->post('/settings/instagram.json', function() use($app) {
  if($user=require_login($app)) {
    $params = $app->request()->params();

    if(!isset($params['action'])) {
      return;
    }

    if($params['action'] == 'disconnect') {
      $user->instagram_user_id = '';
      $user->instagram_username = '';
      $user->instagram_access_token = '';
      $user->save();
    }

    $app->response()->header('Content-Type', 'application/json');
    $app->response()->body(json_encode([
      'result' => 'ok'
    ]));
  }
});

$app->get('/instagram/photos.json', function() use($app) {
  if($user=require_login($app)) {
    $params = $app->request()->params();

    if(isset($params['force_refresh']) && $params['force_refresh'])
      $refresh = true;
    else
      $refresh = false;

    if(isset($params['num']))
      $num = $params['num'];
    else
      $num = 4;

    $feed = IG\get_user_photos($user->instagram_username, $refresh);

    $photos = [];

    if($feed['items']) {
      foreach(array_slice($feed['items'],0,$num) as $item) {
        $photo = ORM::for_table('photos')
          ->where('user_id', $user->id)
          ->where('instagram_url', $item['url'])
          ->find_one();
        if(!$photo) {
          $photo = ORM::for_table('photos')->create();
          $photo->user_id = $user->id;
          $photo->instagram_url = $item['url'];

          $entry = h_entry_from_photo($item['url'], $user->send_media_as == 'upload', $user->multi_photo);

          $photo->instagram_data = json_encode($entry);

          if($user->multi_photo && is_array($entry['photo']) && (count($entry['photo']) > 1)) {
            $photo->instagram_img_list = json_encode($entry['photo']);
          } else {
            if(is_array($entry['photo'])) $entry['photo'] = $entry['photo'][0];
            $photo->instagram_img = $entry['photo'];
          }

          $photo->published = date('Y-m-d H:i:s', strtotime($entry['published']));
          $photo->save();
        } else {
          $entry = json_decode($photo->instagram_data, true);
        }

        $photos[] = [
          'instagram_url' => $photo->instagram_url,
          'instagram_img' => $photo->instagram_img,
          'instagram_img_list' => json_decode($photo->instagram_img_list),
          'video' => (isset($entry['video']) ? $entry['video'] : false),
          'canonical_url' => $photo->canonical_url,
          'id' => $photo->id,
          'data' => $entry,
        ];

      }
    }

    $targets = json_decode($user->micropub_syndication_targets);

    $app->response()->header('Content-Type', 'application/json');
    $app->response()->body(json_encode([
      'items' => $photos,
      'targets' => $targets
    ]));
  }
});

$app->get('/creating-a-token-endpoint', function() use($app) {
  $app->redirect('https://indieweb.org/token-endpoint', 302);
});
$app->get('/creating-a-micropub-endpoint', function() use($app) {
  $app->redirect('https://indieweb.org/micropub-endpoint', 302);
});

$app->get('/docs', function() use($app) {
  render('docs', array(
    'title' => 'OwnYourGram Documentation',
  ));
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

    render('instagram', array(
      'title' => 'Instagram',
      'user' => $user,
      'instagram_username' => $instagram_username
    ));
  }
});

$app->get('/instagram/verify', function() use($app) {
  if($user=require_login($app)) {
    if(!array_key_exists('instagram_username', $_SESSION)) {
      $app->redirect('/instagram', 302);
      return;
    }

    // Check the instagram account looking for the link back to the user's home page
    $success = IG\profile_matches_website($_SESSION['instagram_username'], $user->url);

    if($success) {
      // Remove this username from a previous account
      ORM::for_table('users')->raw_execute('UPDATE users SET instagram_username="" WHERE instagram_username=:u', ['u'=>$_SESSION['instagram_username']]);

      $user->instagram_username = $_SESSION['instagram_username'];
    } else {
      $user->instagram_username = null;
    }
    $user->save();

    render('instagram-verify', array(
      'title' => 'Instagram',
      'user' => $user,
      'instagram_username' => $_SESSION['instagram_username'],
      'success' => $success
    ));
  }
});

$app->post('/prefs/save', function() use($app) {
  if($user=require_login($app)) {
    $params = $app->request()->params();

    if(array_key_exists('blacklist', $params))
      $user->blacklist = $params['blacklist'];
    if(array_key_exists('whitelist', $params))
      $user->whitelist = $params['whitelist'];
    if(array_key_exists('add_tags', $params))
      $user->add_tags = $params['add_tags'];
    if(array_key_exists('send_media_as', $params))
      $user->send_media_as = $params['send_media_as'];
    if(array_key_exists('multi_photo', $params))
      $user->multi_photo = $params['multi_photo'];

    $user->save();

    // Delete any photos in the database that were not yet processed, since
    // the cached data may need to be regenerated if they changed the micropub format
    // or multi-photo setting.

    ORM::for_table('photos')->raw_execute('DELETE FROM photos WHERE user_id = :u AND processed = 0', ['u'=>$user->id]);

    $app->response()->header('Content-Type', 'application/json');
    $app->response()->body(json_encode(array(
      'result' => 'ok'
    )));
  }
});

$app->post('/instagram/test.json', function() use($app) {
  if($user=require_login($app)) {
    $params = $app->request()->params();

    $photo = ORM::for_table('photos')
      ->where('user_id', $user->id)
      ->where('id', $params['id'])
      ->find_one();

    if(!$photo)
      $app->redirect('/');

    $entry = json_decode($photo->instagram_data, true);

    // Build syndication links for post-again
    if(isset($_POST['syndicate']) && $_POST['syndicate'] == 'true') {
      $rules = ORM::for_table('syndication_rules')->where('user_id', $user->id)->find_many();
      $syndications = '';
      foreach($rules as $rule) {
        if($rule->match == '*' || stripos($entry['content'], $rule->match) !== false) {
          if(!isset($entry['mp-syndicate-to']))
            $entry['mp-syndicate-to'] = [];
          $entry['mp-syndicate-to'][] = $rule->syndicate_to;
          $syndications .= ' +'.$rule->syndicate_to_name;
        }
      }
    }

    // Now send to the micropub endpoint
    $response = micropub_post($user, $entry);

    $user->last_micropub_response = json_encode($response);

    // Check the response and look for a "Location" header containing the URL
    if($response && isset($response['headers']['Location']) && ($response['code'] == 201 || $response['code'] == 202)) {
      $user->micropub_success = 1;
      $user->last_micropub_url = $location = $response['headers']['Location'][0];
      $user->photo_count = $user->photo_count + 1;

      // If their account was disabled, enable it again
      if($user->tier == 0) {
        $user->tier = 2;
      }

      $photo->canonical_url = $location;
    } else {
      $location = false;

      Logger::$log->info('Error posting to Micropub endpoint: '."\n".$response['response']);
    }

    $photo->processed = 1;
    $photo->save();
    $user->save();

    $app->response()->header('Content-Type', 'application/json');
    $app->response()->body(json_encode(array(
      'response' => $response['response'],
      'location' => $location,
      'error' => $response['error'],
    )));
  }
});
