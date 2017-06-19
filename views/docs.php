<?php ob_start() ?>
## OwnYourGram

### Importing from Instagram

OwnYourGram does not use the Instagram API, but instead will poll your account to find your photos. Because it has to poll, OwnYourGram has several polling tiers based on how often you post on Instagram.

* every 15 minutes
* every hour
* every 6 hours
* every 24 hours

If you have posted 7 or more photos in the last 14 days, you will be in the highest polling tier, so your account will be checked every 15 minutes. If you have posted 4-6 photos in the last 14 days, your account will be checked every hour. If you've posted 2-3 photos, your account will be checked every 6 hours, and if you've posted 0-1 photos then your account will be checked only every 24 hours.

Every time your account is checked, your polling tier is recalculated. This means if you suddenly post a bunch of photos, you'll start in the highest tier again.

If OwnYourGram encounters any Micropub errors when posting the photo to your site, your account is demoted by one tier.


## Micropub

OwnYourGram will convert your Instagram photos and videos into a Micropub request, 
and send them to your Micropub endpoint.

The request to create a photo will be sent as multipart form-encoded with a file upload,
so that the actual photo (and video) data is sent along with the request. Most web frameworks
will automatically handle parsing the HTTP request and providing the POST parameters 
and the file upload automatically.

The request will contain the following POST parameters:

* `h=entry` - Indicates the type of object being created, in this case an <a href="https://indieweb.org/h-entry">h-entry</a>.
* `content` - The caption of the Instagram photo.
* `published` - An ISO8601 formatted date string for the date the photo was taken.
* `category[]` - Each hashtag used in the photo caption will be sent as a `category[]` parameter. Additionally, if there are any people tagged in the photo, their Instagram profile URL or website URL will be included as categories. ([What is a person tag?](https://indieweb.org/person-tag))
* `location` - Either a <a href="https://indieweb.org/geo_URI">Geo URI</a> including the latitude and longitude of the photo if included (e.g. `geo:37.786971,-122.399677`), or an <a href="http://microformats.org/wiki/h-card">h-card</a> if your account is set to JSON posts.
* `place_name` - If the location on Instagram has a name, the name will be included here. (Note: this property is deprecated and will only be sent for non-JSON requests, since it's included in the h-card location above.)
* `syndication` - The Instagram URL of your photo. You can use this to link to the Instagram copy of your photo, and to enable backfeed of comments and likes via <a href="https://brid.gy">Bridgy</a>.
* `photo` - The photo will be sent in a parameter named "photo". There will only be one photo per request. For Instagram videos, this will be the thumbnail of the video.
* `video` - For Instagram videos, the video file will be uploaded as well.

By default, OwnYourGram will send a multipart request to your Micropub endpoint with the photo as an upload. You can opt in to receiving a JSON request that references the Instagram URL instead. You can then download the photo (and video) from Instagram's URL yourself rather than have OwnYourGram upload them.

The request will also contain an access token in the HTTP `Authorization` header:

<pre>
Authorization: Bearer XXXXXXXX
</pre>

### Micropub Documentation

To learn more about setting up a Micropub endpoint, refer to the documentation and tutorials below.

* [Micropub specification](https://www.w3.org/TR/micropub/)
* [Creating a Micropub endpoint](https://indieweb.org/micropub-endpoint)
* [Creating a Token endpoint](https://indieweb.org/token-endpoint)

<?= Markdown(ob_get_clean()) ?>
