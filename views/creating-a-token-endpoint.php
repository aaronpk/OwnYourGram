<?php ob_start() ?>
## The Token Endpoint

Requests will be made to the token endpoint after the client finishes communicating 
with the authorization server and obtains an auth code.

### Access Token Request

Requests to the token endpoint will contain the following parameters:

* `code` - The authorization code previously obtained.
* `me` - The user's domain name (e.g. http://example.com)
* `redirect_uri` - the redirect URI used in the request to obtain the authorization code.
* `client_id` - The client ID used in the initial request.
* `state` - The state parameter used in the initial request.

The token endpoint needs to verify these values and then issue an access token in response.

The authorization endpoint can be used to verify these values. However you will first need 
to determine which authorization server this user delegates to. This is done by looking 
for a <code>rel="authorization_endpoint"</code> link on the user's home page, which is
the "me" parameter.

Once you know the authorization endpoint, you can make a POST request with the parameters
of this request:

* `code`
* `me`
* `redirect_uri`
* `client_id`
* `state`

If the request is valid (the state, client_id, redirect_uri and code all correspond to the 
authorization made by the user), he authorization endpoint will return a successful reply:

<pre>me=http%3A%2F%2Faaronparecki.com&amp;scope=post</pre>

This is how the token endpoint knows which scopes the user authorized previously.

### Generating an Access Token

At this point, your token endpoint is ready to issue an access token to the app.

How exactly you do this is entirely up to you, and depends on which language/framework
you are using. There are multiple ways to generate and later verify an access token.

Since your token endpoint will be issuing the token, and your Micropub endpoint will be
the only thing that needs to validate tokens, how that works is entirely up to you, 
and can even be changed later without any ill effects.

#### Token Database

A trivial way of creating access tokens is to use a database such as MySQL, Postgres,
or Redis. Using this method, you would simply generate a long random string, and use
that as a unique key, adding in the rest of the information about the token.

At a minimum, you would store the following data along with the token:

* `me`
* `client_id`
* `scope`

While this is a simple way of handling access tokens, you will quickly realize the limitations.
Unless you have a way of expiring and re-issuing tokens, your token database will
quickly grow in size and may eventually become unwieldy. Of course this also assumes
that your website has a database to begin with, which is 
<a href="http://indiewebcamp.com/database">not necessarily a safe assumption</a>.


#### Self-Encoded Tokens

Self-encoded tokens are a way to create access tokens that doesn't require storing a 
string in a database in order to look it up later. By encoding all of the token information
into the token itself, the server can verify the token just by inspecting it later. There 
are many ways to self-encode tokens, again this depends on your preferences.

One way to create self-encoded tokens is to create a hash of all the data you want to 
include in the token, JSON-encode it, and encrypt the resulting string with a key known
only to your server.

The example below is written in PHP, but the idea applies to any language.

<pre>
  $token_data = array(
    'me' => $_POST['me'],
    'client_id' => $_POST['client_id'],
    'scope' => $auth['scope'],  // Returned by the auth code verification step
    'date_issued' => date('Y-m-d H:i:s'),
    'nonce' => mt_rand(1000000,pow(2,31))
  );
</pre>

In the example above, we've included a few pieces of information that will be useful when
decrypting and verifying the token later.

* `me` - Naturally we need to know which user this token corresponds to.
* `client_id` - Indicates the app that generated the token.
* `scope` - The Micropub endpoint must be able to know what scope the token includes, so it can allow or deny specific requests.
* `date_issued` - Included so that we can selectively invalidate tokens created before a certain date if needed. Also servers to add more entropy to the encrypted string.
* `nonce` - Adds some extra entropy to the encrypted string.

All of this data is then JSON-encoded and encrypted using the "JWT" package, which 
results in a string that is the access token.

<pre>
$token = JWT::encode($token_data, $encryption_key);
</pre>



<h3>Access Token Response</h3>

However you generate an access token, you now have a string ready to reply to the client.

The token response must include three parameters:

* `access_token` - The actual access token string.
* `me` - The URL of the user.
* `scope` - The list of scopes that the token represents.

These should be returned as a www-form-encoded string in the response body, as follows:

<pre>
HTTP/1.1 200 OK
Content-Type: application/x-www-form-urlencoded

access_token=XXXXXX&amp;scope=post&amp;me=http%3A%2F%2Faaronparecki.com%2F
</pre>

The reason for returning the "me" value is that the app does not yet know which user
the authorization is for, and will use this value to discover the Micropub endpoint
to make a request with the access token.

<?= Markdown(ob_get_clean()) ?>
