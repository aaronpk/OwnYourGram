<?php ob_start() ?>
## OwnYourGram Documentation

OwnYourGram will convert your Instagram photos and videos into a Micropub request, 
and send them to your Micropub endpoint.

The request to create a photo will be sent as multipart form-encoded with a file upload,
so that the actual photo (and video) data is sent along with the request. Most web frameworks
will automatically handle parsing the HTTP request and providing the POST parameters 
and the file upload automatically.

The request will contain the following POST parameters:

* `h=entry` - Indicates the type of object being created, in this case an <a href="http://indiewebcamp.com/h-entry">h-entry</a>.
* `content` - The caption of the Instagram photo.
* `published` - An ISO8601 formatted date string for the date the photo was taken.
* `category[]` - Each hashtag used in the photo caption will be sent as a `category[]` parameter. Additionally, if there are any people tagged in the photo, their Instagram profile URL or website URL will be included as categories. ([What is a person tag?](https://indieweb.org/person-tag))
* `location` - A <a href="https://indieweb.org/geo_URI">Geo URI</a> including the latitude and longitude of the photo if included. (e.g. `geo:37.786971,-122.399677`)
* `place_name` - If the location on Instagram has a name, the name will be included here. (Note: this property is deprecated and will be replaced by sending a full h-card in the future.)
* `syndication` - The Instagram URL of your photo. You can use this to link to the Instagram copy of your photo, and to enable backfeed of comments and likes via <a href="https://brid.gy">Bridgy</a>.
* `photo` - The photo will be sent in a parameter named "photo". There will only be one photo per request. For Instagram videos, this will be the thumbnail of the video.
* `video` - For Instagram videos, the video file will be uploaded as well.

The request will also contain an access token in the HTTP `Authorization` header:

<pre>
Authorization: Bearer XXXXXXXX
</pre>

## Micropub Documentation

To learn more about setting up a Micropub endpoint, refer to the documentation and tutorials below.

* [Micropub specification](https://www.w3.org/TR/micropub/)
* [Creating a Micropub endpoint](https://indieweb.org/micropub-endpoint)
* [Creating a Token endpoint](https://indieweb.org/token-endpoint)

<?= Markdown(ob_get_clean()) ?>
