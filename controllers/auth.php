<?php

function buildRedirectURI() {
  return (Config::$ssl ? 'https' : 'http') . '://' . $_SERVER['SERVER_NAME'] . '/auth/callback';
}

function clientID() {
  return 'https://ownyourgram.com';
}

$app->get('/signin', function() use($app) {
  render('signin', array('title' => 'Sign In'));
});

$app->get('/auth/start', function() use($app) {
  $req = $app->request();

  $params = $req->params();

  // the "me" parameter is user input, and may be in a couple of different forms:
  // aaronparecki.com http://aaronparecki.com http://aaronparecki.com/
  // Normlize the value now (move this into a function in IndieAuth\Client later)
  if(!array_key_exists('me', $params) || !($me = IndieAuth\Client::normalizeMeURL($params['me']))) {
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
    $_SESSION['auth_me'] = $me;

    $scope = 'create';
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

    $app->redirect($authorizationURL, 302);

  } else {

    // If all three endpoints are found, redirect immediately
    if($micropubEndpoint && $authorizationEndpoint && $tokenEndpoint) {
      $app->redirect($authorizationURL, 302);
    } else {
      render('auth_start', array(
        'title' => 'Sign In',
        'me' => $me,
        'authorizing' => $me,
        'meParts' => parse_url($me),
        'tokenEndpoint' => $tokenEndpoint,
        'micropubEndpoint' => $micropubEndpoint,
        'authorizationEndpoint' => $authorizationEndpoint,
        'authorizationURL' => $authorizationURL
      ));
    }
  }
});

$app->get('/auth/callback', function() use($app) {
  $req = $app->request();
  $params = $req->params();

  // If there is no state in the session, start the login again
  if(!array_key_exists('auth_state', $_SESSION)) {
    $app->redirect('/auth/start?error=missing_session_state');
    return;
  }

  if(!array_key_exists('code', $params) || trim($params['code']) == '') {
    render('auth_error', array(
      'title' => 'Auth Callback',
      'error' => 'Missing authorization code',
      'errorDescription' => 'No authorization code was provided in the request.'
    ));
    return;
  }

  // Verify the state came back and matches what we set in the session
  // Should only fail for malicious attempts, ok to show a not as nice error message
  if(!array_key_exists('state', $params)) {
    render('auth_error', array(
      'title' => 'Auth Callback',
      'error' => 'Missing state parameter',
      'errorDescription' => 'No state parameter was provided in the request. This shouldn\'t happen. It is possible this is a malicious authorization attempt.'
    ));
    return;
  }

  if($params['state'] != $_SESSION['auth_state']) {
    render('auth_error', array(
      'title' => 'Auth Callback',
      'error' => 'Invalid state',
      'errorDescription' => 'The state parameter provided did not match the state provided at the start of authorization. This is most likely caused by a malicious authorization attempt.'
    ));
    return;
  }

  // Now the basic sanity checks have passed. Time to start providing more helpful messages when there is an error.
  // An authorization code is in the query string, and we want to exchange that for an access token at the token endpoint.

  $me = $_SESSION['auth_me'];

  // Discover the endpoints
  $micropubEndpoint = IndieAuth\Client::discoverMicropubEndpoint($me);
  $tokenEndpoint = IndieAuth\Client::discoverTokenEndpoint($me);

  if($tokenEndpoint) {
    $token = IndieAuth\Client::getAccessToken($tokenEndpoint, $params['code'], $me, buildRedirectURI(), clientID(), $params['state'], true);

  } else {
    $token = array('auth'=>false, 'response'=>false);
  }

  $hasAlreadyLoggedInBefore = false;

  // If a valid access token was returned, store the token info in the session and they are signed in
  if($token['auth'] && k($token['auth'], array('me','access_token','scope'))) {
    $_SESSION['auth'] = $token['auth'];
    $_SESSION['me'] = $token['auth']['me'];

    $user = ORM::for_table('users')->where('url', $me)->find_one();
    if($user) {
      // Already logged in, update the last login date
      $user->last_login = date('Y-m-d H:i:s');
      // If they have logged in before and we already have an access token, then redirect to the dashboard now
      if($user->micropub_access_token)
        $hasAlreadyLoggedInBefore = true;

      // Discover the media endpoint every time they log in, and remove it if not found
      $q = micropub_get($micropubEndpoint, $token['auth']['access_token'], ['q'=>'config']);
      if($q && $q['data'] && $q['data']['media-endpoint']) {
        $user->media_endpoint = $q['data']['media-endpoint'];
      } else {
        $user->media_endpoint = '';
      }

    } else {
      // New user! Store the user in the database
      $user = ORM::for_table('users')->create();
      $user->url = $me;
      $user->date_created = date('Y-m-d H:i:s');

      $q = micropub_get($micropubEndpoint, $token['auth']['access_token'], ['q'=>'config']);
      if($q && $q['data'] && $q['data']['media-endpoint']) {
        $user->media_endpoint = $q['data']['media-endpoint'];
        // $user->send_media_as = 'url';
      }

    }
    $user->micropub_endpoint = $micropubEndpoint;
    $user->micropub_access_token = $token['auth']['access_token'];
    $user->micropub_response = $token['response'];

    // If polling was disabled, enable it again at the lowest tier
    if($user->tier == 0) {
      $user->tier = 1;
    }

    $user->save();
    $_SESSION['user_id'] = $user->id();
  }

  unset($_SESSION['auth_state']);
  unset($_SESSION['auth_me']);

  if($hasAlreadyLoggedInBefore) {
    // If they have an active instagram username, or have already imported a photo, redirect to the dashboard.
    // For people who disconnect an Instagram account, they will then be sent to the dashboard after logging in.
    if($user->instagram_username || $user->micropub_success)
      $app->redirect('/dashboard', 302);
    else
      $app->redirect('/instagram', 302);
  } else {
    render('auth_callback', array(
      'title' => 'Sign In',
      'me' => $me,
      'authorizing' => $me,
      'meParts' => parse_url($me),
      'tokenEndpoint' => $tokenEndpoint,
      'auth' => $token['auth'],
      'response' => $token['response'],
      'curl_error' => (array_key_exists('error', $token) ? $token['error'] : false)
    ));
  }
});

$app->get('/signout', function() use($app) {
  unset($_SESSION['auth']);
  unset($_SESSION['me']);
  unset($_SESSION['auth_state']);
  unset($_SESSION['user_id']);
  $app->redirect('/', 302);
});
