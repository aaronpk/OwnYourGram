<?php
class Config {
  public static $hostname = 'ownyourgram.dev';
  public static $ssl = false;
  public static $gaid = '';

  public static $instagramClientID = '';
  public static $instagramClientSecret = '';

  public static $beanstalkServer = '127.0.0.1';
  public static $beanstalkPort = 11300;

  public static $dbHost = '127.0.0.1';
  public static $dbName = 'ownyourgram';
  public static $dbUsername = 'ownyourgram';
  public static $dbPassword = '';

  public static function instagramRedirectURI() {
    return 'http'.(self::$ssl ? 's' : '').'://'.Config::$hostname.'/auth/instagram-callback';
  }
}

