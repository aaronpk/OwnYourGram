OwnYourGram
===========

https://ownyourgram.com


### Polling Tiers

* 0 - account disabled. will only be re-enabled after the user signs in.
* 1 - daily
* 2 - every 6 hours
* 3 - every hour
* 4 - every 15 minutes

Configure cron tasks at the specified intervals passing the tier number as a command line argument.

```
# every 15 minutes
0,15,30,45 * * * * /usr/bin/php /web/sites/ownyourgram.com/scripts/ownyourgram-cron.php 4

# every hour at 5 after the hour
5 * * * * /usr/bin/php /web/sites/ownyourgram.com/scripts/ownyourgram-cron.php 3

# every 6 hours at 10 after the hour
10 0,6,12,18 * * * /usr/bin/php /web/sites/ownyourgram.com/scripts/ownyourgram-cron.php 2

# every day at 1:10 UTC
10 1 * * * /usr/bin/php /web/sites/ownyourgram.com/scripts/ownyourgram-cron.php 1
```

### Credits

Camera icon by Gracelle Mesina from thenounproject.com 
https://thenounproject.com/term/camera/62851

### License

Copyright 2013-2017 by Aaron Parecki

Licensed under the Apache License, Version 2.0 (the "License");
you may not use this file except in compliance with the License.
You may obtain a copy of the License at

http://www.apache.org/licenses/LICENSE-2.0

Unless required by applicable law or agreed to in writing, software
distributed under the License is distributed on an "AS IS" BASIS,
WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
See the License for the specific language governing permissions and
limitations under the License.

