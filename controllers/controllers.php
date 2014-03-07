<?php

$app->get('/dashboard', function() use($app) {



  $html = render('dashboard', array(
    'title' => 'Dashboard'
  ));
  $app->response()->body($html);
});

