<?php

function buildRedirectURI() {
  return 'http://' . $_SERVER['SERVER_NAME'] . '/auth/callback';
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
  if($me['path'] != '/')
    return false;

  // query and fragment not allowed
  if(array_key_exists('query', $me) || array_key_exists('fragment', $me))
    return false;

  return build_url($me);
}

$app->get('/', function($format='html') use($app) {
  $res = $app->response();


  ob_start();
  render('index', array(
    'title' => 'OwnYourGram',
    'meta' => ''
  ));
  $html = ob_get_clean();
  $res->body($html);
});

$app->get('/signin', function() use($app) {
  $html = render('signin', array('title' => 'Sign In'));
  $app->response()->body($html);
});

$app->get('/creating-a-token-endpoint', function() use($app) {
  $html = render('creating-a-token-endpoint', array('title' => 'Creating a Token Endpoint'));
  $app->response()->body($html);
});
$app->get('/creating-a-micropub-endpoint', function() use($app) {
  $html = render('creating-a-micropub-endpoint', array('title' => 'Creating a Micropub Endpoint'));
  $app->response()->body($html);
});

$app->get('/auth/start', function() use($app) {
  $req = $app->request();

  $params = $req->params();
  
  // the "me" parameter is user input, and may be in a couple of different forms:
  // aaronparecki.com http://aaronparecki.com http://aaronparecki.com/
  // Normlize the value now (move this into a function in IndieAuth\Client later)
  if(!array_key_exists('me', $params) || !($me = normalizeMeURL($params['me']))) {
    $html = render('auth_start_error', array(
      'title' => 'Sign In',
      'input' => $params['me']
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
    $authorizationURL = IndieAuth\Client::buildAuthorizationURL($authorizationEndpoint, $me, buildRedirectURI(), 'https://ownyourgram.com', $state, $scope);
  } else {
    $authorizationURL = false;
  }

  $html = render('auth_start', array(
    'title' => 'Sign In',
    'me' => $me,
    'meParts' => parse_url($me),
    'tokenEndpoint' => $tokenEndpoint,
    'micropubEndpoint' => $micropubEndpoint,
    'authorizationEndpoint' => $authorizationEndpoint,
    'authorizationURL' => $authorizationURL
  ));
  $app->response()->body($html);
});


