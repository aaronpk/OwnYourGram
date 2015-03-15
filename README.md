OwnYourGram
===========

https://ownyourgram.com


### Configuring the Instagram Webhook

http://instagram.com/developer/realtime/

After deploying the site, create the subscription to receive a callback post when
any user who has authorized the application posts a new photo.

```
curl -F 'client_id=x' \
     -F 'client_secret=x' \
     -F 'object=user' \
     -F 'aspect=media' \
     -F 'verify_token=1234567890' \
     -F 'callback_url=http://ownyourgram.com/instagram/callback' \
     https://api.instagram.com/v1/subscriptions/
```



### Contributing

By submitting code to this project, you agree to irrevocably release it under the same license as this project.


### License

Copyright 2013 by Aaron Parecki

Licensed under the Apache License, Version 2.0 (the "License");
you may not use this file except in compliance with the License.
You may obtain a copy of the License at

http://www.apache.org/licenses/LICENSE-2.0

Unless required by applicable law or agreed to in writing, software
distributed under the License is distributed on an "AS IS" BASIS,
WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
See the License for the specific language governing permissions and
limitations under the License.

