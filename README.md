OwnYourGram
===========

[OwnYourGram](https://ownyourgram.com) will check for photos posted to your Instagram account and send them to your website via [Micropub](https://micropub.net).

Note that this works by scraping the Instagram website so may periodically stop working as Instagram adjusts their rate limits and policies.

## Installation

The best way to avoid the global rate limits that apply to the hosted instance at [ownyourgram.com](https://ownyourgram.com) is to run your own copy of OwnYourGram. You can run it on your own server, or even run it on a laptop.

### Requirements

Please ensure you have the following installed before configuring OwnYourGram.

* PHP 7
* [Composer](https://getcomposer.org)
* MySQL
* Optional: Redis

Redis is optional, but highly recommended. Using Redis enables OwnYourGram to cache Instagram responses and also enables adapting to Instagram's rate limits. Without it, OwnYourGram will make more requests to Instagram and will be more likely to get rate limited.

### Setup

Clone this project into a folder:

```
git clone https://github.com/aaronpk/OwnYourGram.git
```

Install the dependencies:

```
cd OwnYourGram
composer install
```

Copy the `config.template.php` file to `config.php` and fill out the values.

```
cp lib/config.template.php lib/config.php
```

* `$hostname` - set to the hostname you've configured to serve this app. Use `localhost` if using the built-in PHP server.
* `$ssl` - `true` or `false` depending on whether you access OwnYourGram over `https`
* `$gaid` - If you want to use Google Analytics, set your Google Analytics tracking ID here
* `$newUsersAllowed` - `true` or `false` - the first time you set this up, ensure it's set to `true` so that you can log in. If you want to lock down your installation to only existing users, set this to `false` and no new users will be able to log in.
* `$redis` - `false` or `tcp://127.0.0.1:6379` - if you have Redis enabled, the performance of OwnYourGram will be improved
* `$cacheIGRequests` - `true` or `false` - enable caching for better performance
* `$xray` - `false` or the hostname of an XRay service - Setting this to `false` will cause OwnYourGram to fetch Instagram pages directly. This is best if you are running this on a laptop. If you're running it on a cloud server, you may want to run an external XRay service in case Instagram is blocking the IP address of your server.
* `$igCookie` - `false` or your Instagram session cookie - setting this to a cookie will cause all requests to Instagram to be made with this cookie. This can be helpful to get around some rate limits, but may put the account at risk of getting flagged as a bot.
* `$db` - the configuration for your MySQL database

Create the database and a database user:

```
mysql -u root
CREATE DATABASE ownyourgram;
GRANT ALL PRIVILEGES ON ownyourgram.* TO 'ownyourgram'@'127.0.0.1' IDENTIFIED BY 'ownyourgram';
exit
```

Create the tables:

```
mysql -u ownyourgram -pownyourgram < schema/schema.sql
```

Configure a web server to serve the `public` folder of this project, or use the built-in PHP server.

#### nginx

Ensure you have something like the following in a server block for OwnYourGram, and that nginx is configured to handle PHP files.

```
...
  root /web/OwnYourGram/public;

  try_files $uri /index.php?$args;
...
```

#### built-in server

```
php -S 127.0.0.1:8080 -t public
```

Now you can visit the app and log in! The first time you log in it will prompt you to connect an Instagram account.


### Polling Tiers

Configure a cron task to run every minute. Rate limiting is checked in the cron job. You'll need to enable this cron job to have OwnYourGram keep your photos up to date automatically. If you only want to manually import from the web interface then you don't need this.

```
* * * * * /usr/bin/php /web/sites/ownyourgram.com/scripts/ownyourgram-cron.php
```

* 0 - account disabled. will only be re-enabled after the user signs in.
* 1 - daily
* 2 - every 6 hours
* 3 - every hour
* 4 - every 15 minutes

## Credits

Camera icon by Gracelle Mesina from thenounproject.com
https://thenounproject.com/term/camera/62851

## License

Copyright 2013-2019 by Aaron Parecki

Licensed under the Apache License, Version 2.0 (the "License");
you may not use this file except in compliance with the License.
You may obtain a copy of the License at

http://www.apache.org/licenses/LICENSE-2.0

Unless required by applicable law or agreed to in writing, software
distributed under the License is distributed on an "AS IS" BASIS,
WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
See the License for the specific language governing permissions and
limitations under the License.

