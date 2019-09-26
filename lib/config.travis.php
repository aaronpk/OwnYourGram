<?php
class Config {
  public static $hostname = 'ownyourgram.dev';
  public static $ssl = false;
  public static $gaid = false;

  public static $newUsersAllowed = true;

  public static $redis = false;
  public static $cacheIGRequests = false;

  public static $xray = false;
  public static $igCookie = false;

  public static $db = [
    'host' => '127.0.0.1',
    'database' => 'ownyourgram',
    'username' => 'ownyourgram',
    'password' => '',
  ];
}

