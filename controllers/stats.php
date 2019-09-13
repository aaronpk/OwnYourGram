<?php

$app->get('/stats/users', function() use($app) {

  $params = $app->request()->params();

  if(isset($params['config'])) {

    $response = "graph_title OwnYourGram Users
graph_info Number of OwnYourGram user accounts
graph_vlabel Users
graph_category ownyourgram
graph_args --lower-limit 0
graph_scale yes

total_users.label Total Users
total_users.type GAUGE
total_users.min 0
active_users.label Active Users
active_users.type GAUGE
active_users.min 0
";
  } else {
    $total_users = ORM::for_table('users')->count();
    $active_users = ORM::for_table('users')
      ->where('micropub_success', 1)
      ->where_gt('tier', 0)
      ->where_not_equal('instagram_username', '')
      ->count();
    $response = 'total_users.value '.$total_users.'
active_users.value '.$active_users;
  }

  respond_text($app, $response);
});

$app->get('/stats/photos', function() use($app) {

  $params = $app->request()->params();

  if(isset($params['config'])) {

    $response = "graph_title OwnYourGram Photos
graph_info Total number of OwnYourGram photos imported
graph_vlabel Photos
graph_category ownyourgram
graph_args --lower-limit 0
graph_scale yes

photos.label Total Photos
photos.type GAUGE
photos.min 0
";
  } else {
    $photos = ORM::for_table('users')->sum('photo_count');
    $response = 'photos.value '.$photos;
  }

  respond_text($app, $response);
});

$app->get('/stats/tiers', function() use($app) {

  $params = $app->request()->params();

  if(isset($params['config'])) {

    $response = "graph_title OwnYourGram Polling Tiers
graph_info Number of users in each polling tier
graph_vlabel Users
graph_category ownyourgram
graph_args --lower-limit 0
graph_scale yes

tier0.label Tier 0
tier0.type GAUGE
tier0.min 0
tier1.label Tier 1
tier1.type GAUGE
tier1.min 0
tier2.label Tier 2
tier2.type GAUGE
tier2.min 0
tier3.label Tier 3
tier3.type GAUGE
tier3.min 0
tier4.label Tier 4
tier4.type GAUGE
tier4.min 0
";
  } else {

    $tier0 = ORM::for_table('users')
      ->where('micropub_success', 1)
      ->where_not_equal('instagram_username', '')
      ->where('tier', 0)
      ->count();
    $tier1 = ORM::for_table('users')
      ->where('micropub_success', 1)
      ->where_not_equal('instagram_username', '')
      ->where('tier', 1)
      ->count();
    $tier2 = ORM::for_table('users')
      ->where('micropub_success', 1)
      ->where_not_equal('instagram_username', '')
      ->where('tier', 2)
      ->count();
    $tier3 = ORM::for_table('users')
      ->where('micropub_success', 1)
      ->where_not_equal('instagram_username', '')
      ->where('tier', 3)
      ->count();
    $tier4 = ORM::for_table('users')
      ->where('micropub_success', 1)
      ->where_not_equal('instagram_username', '')
      ->where('tier', 4)
      ->count();

    $response = 'tier0.value '.$tier0.'
tier1.value '.$tier1.'
tier2.value '.$tier2.'
tier3.value '.$tier3.'
tier4.value '.$tier4;
  }

  respond_text($app, $response);
});



$app->get('/stats/lag', function() use($app) {

  $params = $app->request()->params();

  if(isset($params['config'])) {

    $response = "graph_title OwnYourGram Polling Lag
graph_info Average polling lag
graph_vlabel Seconds
graph_category ownyourgram
graph_args --lower-limit 0
graph_scale yes

lag.label Lag
lag.type GAUGE
lag.min 0
";
  } else {

    $timing = [];

    while($s = \p3k\redis()->lpop('ownyourgram-poll-lag')) {
      $timing[] = (int)$s;
    }

    if(count($timing))
      $lag = round(array_sum($timing) / count($timing));
    else
      $lag = 0;

    $response = 'lag.value '.$lag;
  }

  respond_text($app, $response);
});


function respond_text(&$app, $response) {
  $app->response()->header('Content-Type', 'text/plain');
  $app->response()->body($response);
}
