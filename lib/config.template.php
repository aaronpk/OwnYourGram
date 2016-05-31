<?php
class Config {
  public static $hostname = 'ownyourgram.dev';
  public static $ssl = false;
  public static $gaid = '';

  public static $beanstalkServer = '127.0.0.1';
  public static $beanstalkPort = 11300;

  public static $redis = 'tcp://127.0.0.1:6379';
  public static $cacheIGRequests = true;

  public static $dbHost = '127.0.0.1';
  public static $dbName = 'ownyourgram';
  public static $dbUsername = 'ownyourgram';
  public static $dbPassword = '';
}

