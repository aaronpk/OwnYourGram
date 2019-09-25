<?php
class Config {
  public static $hostname = 'ownyourgram.dev';
  public static $ssl = false;
  public static $gaid = false;

  public static $newUsersAllowed = true;

  public static $redis = false;
  #public static $redis = 'tcp://127.0.0.1:6379';
  public static $cacheIGRequests = true;

  public static $xray = 'xray.p3k.app';
  #public static $xray = false; // set to false to fetch locally
  public static $igCookie = false;

  public static $db = [
    'host' => '127.0.0.1',
    'database' => 'ownyourgram',
    'username' => 'ownyourgram',
    'password' => '',
  ];
}

