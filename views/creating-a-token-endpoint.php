<pre>
### Issuing an access token

Assuming you get a successful response from the auth server containing the "me" and "scope" parameters, you can now generate an access token and send the reply.

The way you build an access token is entirely up to you. You may want to store tokens in a database, or you may want to use self-encoded tokens to avoid the need for a database. In either case, you must store at least the "me," "scope" and "client_id" values.

The example below generates a self-encoded token by encrypting all the needed information into a string and returning the encrypted string. This example uses a [JWT](https://github.com/firebase/php-jwt) library to encrypt the token, but you could use any method of encryption you wish.

```php
  // $auth is set from the IndieAuth\Client::verifyIndieAuthCode call from before

  $token_data = array(
    'date_issued' => date('Y-m-d H:i:s'),
    'me' => $auth['me'],
    'client_id' => $_POST['client_id'],
    'scope' => array_key_exists('scope', $auth) ? $auth['scope'] : '',
    'nonce' => mt_rand(1000000,pow(2,31))   // add some noise to the encrypted version
  );

  // Encrypt the token with your server-side encryption key
  $token = JWT::encode($token_data, $encryptionKey);

  header('Content-type: application/x-www-form-urlencoded');  
  echo http_build_query(array(
    'me' => $auth['me'],
    'scope' => $token_data['scope'],
    'access_token' => $token
  ));
```

Note that you must return the parameters "me", "scope" and "access_token" in your response. This is because API clients will not know the user or scope of the token otherwise, and clients will need to discover the API endpoint using the "me" parameter before they can make API requests.
</pre>