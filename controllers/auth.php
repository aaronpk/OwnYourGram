<?php

function buildRedirectURI() {
  return (Config::$ssl ? 'https' : 'http') . '://' . $_SERVER['SERVER_NAME'] . '/auth/callback';
}

function clientID() {
  return 'https://ownyourgram.com';
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

// Input: Any URL or string like "aaronparecki.com"
// Output: Normlized URL (default to http if no scheme, force "/" path)
//         or return false if not a valid URL (has query string params, etc)
function normalizeMeURL($url) {
  $me = parse_url($url);

  // parse_url returns just "path" for naked domains
  if(count($me) == 1 && array_key_exists('path', $me)) {
    $me['host'] = $me['path'];
    unset($me['path']);
  }

  if(!array_key_exists('scheme', $me))
    $me['scheme'] = 'http';

  if(!array_key_exists('path', $me))
    $me['path'] = '/';

  // Invalid scheme
  if(!in_array($me['scheme'], array('http','https')))
    return false;

  // Invalid path
  // if($me['path'] != '/')
  //   return false;

  // query and fragment not allowed
  if(array_key_exists('query', $me) || array_key_exists('fragment', $me))
    return false;

  return build_url($me);
}

$app->get('/signin', function() use($app) {
  $html = render('signin', array('title' => 'Sign In'));
  $app->response()->body($html);
});


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

    $app->redirect('/instagram');
  }
});

$app->get('/auth/start', function() use($app) {
  $req = $app->request();

  $params = $req->params();

  // the "me" parameter is user input, and may be in a couple of different forms:
  // aaronparecki.com http://aaronparecki.com http://aaronparecki.com/
  // Normlize the value now (move this into a function in IndieAuth\Client later)
  if(!array_key_exists('me', $params) || !($me = normalizeMeURL($params['me']))) {
    $html = render('auth_error', array(
      'title' => 'Sign In',
      'error' => 'Invalid "me" Parameter',
      'errorDescription' => 'The ID you entered, <strong>' . $params['me'] . '</strong> is not valid.'
    ));
    $app->response()->body($html);
    return;
  }

  $authorizationEndpoint = IndieAuth\Client::discoverAuthorizationEndpoint($me);
  $tokenEndpoint = IndieAuth\Client::discoverTokenEndpoint($me);
  $micropubEndpoint = IndieAuth\Client::discoverMicropubEndpoint($me);

  if($tokenEndpoint && $micropubEndpoint && $authorizationEndpoint) {
    // Generate a "state" parameter for the request
    $state = IndieAuth\Client::generateStateParameter();
    $_SESSION['auth_state'] = $state;

    $scope = 'post';
    $authorizationURL = IndieAuth\Client::buildAuthorizationURL($authorizationEndpoint, $me, buildRedirectURI(), clientID(), $state, $scope);
  } else {
    $authorizationURL = false;
  }

  // If the user has already signed in before and has a micropub access token, skip
  // the debugging screens and redirect immediately to the auth endpoint.
  // This will still generate a new access token when they finish logging in.
  $user = ORM::for_table('users')->where('url', $me)->find_one();
  if($user && $user->micropub_access_token && !array_key_exists('restart', $params)) {

    $user->micropub_endpoint = $micropubEndpoint;
    $user->authorization_endpoint = $authorizationEndpoint;
    $user->token_endpoint = $tokenEndpoint;
    $user->save();

    $app->redirect($authorizationURL, 301);

  } else {

    if(!$user)
      $user = ORM::for_table('users')->create();
    $user->url = $me;
    $user->date_created = date('Y-m-d H:i:s');
    $user->micropub_endpoint = $micropubEndpoint;
    $user->authorization_endpoint = $authorizationEndpoint;
    $user->token_endpoint = $tokenEndpoint;
    $user->save();

    $html = render('auth_start', array(
      'title' => 'Sign In',
      'me' => $me,
      'authorizing' => $me,
      'meParts' => parse_url($me),
      'tokenEndpoint' => $tokenEndpoint,
      'micropubEndpoint' => $micropubEndpoint,
      'authorizationEndpoint' => $authorizationEndpoint,
      'authorizationURL' => $authorizationURL
    ));
    $app->response()->body($html);
  }
});

$app->get('/auth/callback', function() use($app) {
  $req = $app->request();
  $params = $req->params();

  // Double check there is a "me" parameter
  // Should only fail for really hacked up requests
  if(!array_key_exists('me', $params) || !($me = normalizeMeURL($params['me']))) {
    $html = render('auth_error', array(
      'title' => 'Auth Callback',
      'error' => 'Invalid "me" Parameter',
      'errorDescription' => 'The ID you entered, <strong>' . $params['me'] . '</strong> is not valid.'
    ));
    $app->response()->body($html);
    return;
  }

  // If there is no state in the session, start the login again
  if(!array_key_exists('auth_state', $_SESSION)) {
    $app->redirect('/auth/start?me='.urlencode($params['me']));
    return;
  }

  if(!array_key_exists('code', $params) || trim($params['code']) == '') {
    $html = render('auth_error', array(
      'title' => 'Auth Callback',
      'error' => 'Missing authorization code',
      'errorDescription' => 'No authorization code was provided in the request.'
    ));
    $app->response()->body($html);
    return;
  }

  // Verify the state came back and matches what we set in the session
  // Should only fail for malicious attempts, ok to show a not as nice error message
  if(!array_key_exists('state', $params)) {
    $html = render('auth_error', array(
      'title' => 'Auth Callback',
      'error' => 'Missing state parameter',
      'errorDescription' => 'No state parameter was provided in the request. This shouldn\'t happen. It is possible this is a malicious authorization attempt.'
    ));
    $app->response()->body($html);
    return;
  }

  if($params['state'] != $_SESSION['auth_state']) {
    $html = render('auth_error', array(
      'title' => 'Auth Callback',
      'error' => 'Invalid state',
      'errorDescription' => 'The state parameter provided did not match the state provided at the start of authorization. This is most likely caused by a malicious authorization attempt.'
    ));
    $app->response()->body($html);
    return;
  }

  // Now the basic sanity checks have passed. Time to start providing more helpful messages when there is an error.
  // An authorization code is in the query string, and we want to exchange that for an access token at the token endpoint.

  // Discover the endpoints
  $micropubEndpoint = IndieAuth\Client::discoverMicropubEndpoint($me);
  $tokenEndpoint = IndieAuth\Client::discoverTokenEndpoint($me);

  if($tokenEndpoint) {
    $token = IndieAuth\Client::getAccessToken($tokenEndpoint, $params['code'], $params['me'], buildRedirectURI(), clientID(), $params['state'], true);

  } else {
    $token = array('auth'=>false, 'response'=>false);
  }

  $redirectToDashboardImmediately = false;

  // If a valid access token was returned, store the token info in the session and they are signed in
  if($token['auth'] && k($token['auth'], array('me','access_token','scope'))) {
    $_SESSION['auth'] = $token['auth'];
    $_SESSION['me'] = $params['me'];

    $user = ORM::for_table('users')->where('url', $me)->find_one();
    if($user) {
      // Already logged in, update the last login date
      $user->last_login = date('Y-m-d H:i:s');
      // If they have logged in before and we already have an access token, then redirect to the dashboard now
      if($user->micropub_access_token)
        $redirectToDashboardImmediately = true;
    } else {
      // New user! Store the user in the database
      $user = ORM::for_table('users')->create();
      $user->url = $me;
      $user->date_created = date('Y-m-d H:i:s');
    }
    $user->micropub_endpoint = $micropubEndpoint;
    $user->micropub_access_token = $token['auth']['access_token'];
    $user->micropub_response = $token['response'];
    $user->save();
    $_SESSION['user_id'] = $user->id();
  }

  unset($_SESSION['auth_state']);

  if($redirectToDashboardImmediately) {
    $app->redirect('/instagram', 301);
  } else {
    $html = render('auth_callback', array(
      'title' => 'Sign In',
      'me' => $me,
      'authorizing' => $me,
      'meParts' => parse_url($me),
      'tokenEndpoint' => $tokenEndpoint,
      'auth' => $token['auth'],
      'response' => $token['response'],
      'curl_error' => (array_key_exists('error', $token) ? $token['error'] : false)
    ));
    $app->response()->body($html);
  }
});

$app->get('/signout', function() use($app) {
  unset($_SESSION['auth']);
  unset($_SESSION['me']);
  unset($_SESSION['auth_state']);
  unset($_SESSION['user_id']);
  $app->redirect('/', 301);
});
