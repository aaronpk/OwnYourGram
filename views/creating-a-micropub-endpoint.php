<?php ob_start() ?>
## The Micropub Endpoint

After a client has obtained an access token and discovered the user's Micropub endpoint
it is ready to make requests to create posts.

### The Request

This is not intended to be a comprehensive guide to Micropub, so we will use the example 
of the "OwnYourGram" app creating a photo post at the Micropub endpoint.

The request to create a photo will be sent with as multipart form-encoded 
so that the actual photo data is sent along with the request. Most web frameworks
will automatically handle parsing the HTTP request and providing the POST parameters 
and the file upload automatically. The example code here is written in PHP but the idea
is applicable in any language.

The request will contain the following POST parameters:

* `h=entry` - Indicates the type of object being created, in this case an <a href="http://indiewebcamp.com/h-entry">h-entry</a>.
* `content` - The text content the user entered, in this case the caption on the Instagram photo.
* `published` - An ISO8601 formatted date string representing the date the photo was published.
* `category` - A comma-separated list of hashtags the user included in the caption.
* `location` - A "geo" URI including the latitude and longitude of the photo if included. (Will look like `geo:37.786971,-122.399677`)
* `place_name` - If the location on Instagram has a name, the name will be included here.
* `photo` - The photo will be sent in a parameter named "photo". There will only be one photo per request.

The request will also contain an access token in the HTTP `Authorization` header:

<pre>
Authorization: Bearer XXXXXXXX
</pre>


### Verifying Access Tokens

Before you can begin processing the photo, you must first verify the access token is valid
and contains at least the "post" scope.

How exactly you do this is dependent on your architecture. In the example of 
<a href="/creating-a-token-endpoint">creating a token endpoint</a>, we looked at two 
ways of storing access tokens. To verify tokens in a database, you need to retrieve the
token info from the database. To verify self-encoded tokens, you'll need to decode the
token and check the info there.

In any case, once you have looked up the token info, you need to make a determination
about whether that access token is still valid. You'll have the following information
at hand that can be used to check:

* `me` - The user who this access token corresponds to.
* `client_id` - The app that generated the token.
* `scope` - The list of scopes that were authorized by the user.
* `date_issued` - The date the token was issued.

Keep in mind that it may be possible for another user besides yourself to have created
an access token at your token endpoint, so the first thing you'll do when verifying 
is making sure the "me" parameter matches your own domain. This way you are the only
one that can create posts on your website.


### Validating the Request Parameters

A valid request to create a photo post will contain the parameters listed above. For now,
you can verify the presence of everything in the list, or you can try to genericize your 
micropub endpoint so that it can also create text posts.

At a bare minimum, a Micropub request will contain the following:

* `h=entry`
* `photo` or `content` (or both)

If a photo is part of the request, then the content is optional.

If there's no `published` date, then the endpoint should set the published date to now.

The access token must also contain at least the "post" scope.



### The Response

Once you've validated the access token and checked for the presence of all required parameters,
you can create a post in your website with the information provided.

If a post was successfully created, you should return an `HTTP 201` response with a
`Location` header that points to the URL of the post. No body is required for the response.

<pre>
HTTP/1.1 201 Created
Location: http://example.com/post/100
</pre>

If there was an error, the response should include an HTTP error code as appropriate, 
and optionally an HTML or other body with more information. Below is a list of possible errors.

* `HTTP 401 Unauthorized` - No access token was provided in the request.
* `HTTP 403 Forbidden` - An access token was provided, but the authenticated user does not have permission to complete the request.
* `HTTP 400 Bad Request` - Something was wrong with the request, such as a missing "h" parameter, or other missing data. The response body may contain more human-readable information about the error.



<?= Markdown(ob_get_clean()) ?>
