<?php
class Config {
  public static $hostname = 'ownyourgram.dev';
  public static $ssl = false;
  public static $gaid = false;

  public static $newUsersAllowed = true;

  public static $redis = false;
  #public static $redis = 'tcp://127.0.0.1:6379';
  public static $cacheIGRequests = true;

  #public static $xray = 'xray.example.com'; // Set this to the hostname of an XRay server to fetch remotely
  public static $xray = false; // set to false to fetch from the local server
  public static $igCookie = false;

  public static $db = [
    'host' => '127.0.0.1',
    'database' => 'ownyourgram',
    'username' => 'ownyourgram',
    'password' => 'ownyourgram',
  ];
}

