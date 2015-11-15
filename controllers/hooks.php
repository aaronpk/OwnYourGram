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
