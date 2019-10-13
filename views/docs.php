<?php
use Michelf\MarkdownExtra;
$parser = new MarkdownExtra();
ob_start()
?>
## OwnYourGram

### Importing from Instagram {#instagram}

Instagram has recently started severely rate limiting OwnYourGram and any other apps that don't use the official API. As such, OwnYourGram has not been able to continue automatically importing photos at the rate it used to.

The majority of the rate limiting is happening when OwnYourGram attempts to fetch your profile to find your recent photos. Fetching individual photos, however, is not susceptible to the same rate limits. This means you can always log in to OwnYourGram and provide it with the URL to one of your photos and it will be able to import it immediately.

OwnYourGram will still continue to poll your profile, however it does so only once every 24 hours.

If OwnYourGram encounters too many Micropub errors when posting the photo to your site, your automatic import may be disabled entirely.


## Micropub {#micropub}

OwnYourGram will convert your Instagram photos and videos into a Micropub request,
and send them to your Micropub endpoint.

### Form-Encoded Request {#form}

The request to create a photo will be sent as multipart form-encoded with a file upload,
so that the actual photo (and video) data is sent along with the request. Most web frameworks
will automatically handle parsing the HTTP request and providing the POST parameters
and the file upload automatically.

The request will contain the following POST parameters:

* `h=entry` - Indicates the type of object being created, in this case an <a href="https://indieweb.org/h-entry">h-entry</a>.
* `content` - The caption of the Instagram photo.
* `published` - An ISO8601 formatted date string for the date the photo was taken.
* `category[]` - Each hashtag used in the photo caption will be sent as a `category[]` parameter. Additionally, if there are any people tagged in the photo, their Instagram profile URL or website URL will be included as categories. ([What is a person tag?](https://indieweb.org/person-tag))
* `location` - A <a href="https://indieweb.org/geo_URI">Geo URI</a> including the latitude and longitude of the photo if available (e.g. `geo:37.786971,-122.399677`)
* `place_name` - (deprecated) If the location on Instagram has a name, the name will be included here. (Note: this property is deprecated and will only be sent for non-JSON requests, since it's included in the h-card location above.)
* `syndication` - The Instagram URL of your photo. You can use this to link to the Instagram copy of your photo, and to enable backfeed of comments and likes via <a href="https://brid.gy">Bridgy</a>.
* `photo` or `photo[]` - (multipart) - The photo will be sent in a parameter named "photo". There will one or more photos per request. If there are multiple photos, then multiple `photo[]` properties will be sent in the request. For Instagram videos, this will be the thumbnail of the video.
* `video` - (multipart) - For Instagram videos, the video file will be uploaded as well.

### JSON Request {#json}

By default, OwnYourGram will send a multipart request to your Micropub endpoint with the photo (and video) as an upload. A better option is to handle the JSON format, since you'll get better information about venues, as well as person-tags.

If you have a Media Endpoint, then OwnYourGram will first upload the photo to your Media Endpoint and include the URL in the JSON request. If you don't have a Media Endpoint, then the Instagram URL will be included instead.

The JSON request will look like the below. The properties are the same as the form-encoded request with the exception of how location information is handled.

<pre>
{
  "type": ["h-entry"],
  "properties": {
    "content": ["Photo caption from Instagram including #hashtags"],
    "published": ["2018-01-22T16:58:01-05:00"],
    "category": ["hashtags"],
    "location": [{
      "type": ["h-card"],
      "properties": {
        "name": ["Baltimore, Maryland"],
        "latitude": [39.2903],
        "longitude": [-76.6125]
      }
    }],
    "syndication": ["https://www.instagram.com/p/BeRIVrpAdcm/"],
    "photo": ["https://instagram.fsea1-1.fna.fbcdn.net/vp/afbe9ef713ea6a784b8b2d16cac075c7/5B0BB2B8/t51.2885-15/e35/26276701_146389926072712_1687561928420884480_n.jpg"]
  }
}
</pre>

Note that every value is an array, even if there is only one value, which is the standard Microformats2 JSON format. See the [Micropub specification](https://www.w3.org/TR/micropub/#json-syntax) for more details on the JSON syntax.


### Authentication {#authentication}

The request will also contain an access token in the HTTP `Authorization` header:

<pre>
Authorization: Bearer XXXXXXXX
</pre>

### Micropub Documentation

To learn more about setting up a Micropub endpoint, refer to the documentation and tutorials below.

* [Micropub specification](https://www.w3.org/TR/micropub/)
* [Creating a Micropub endpoint](https://indieweb.org/micropub-endpoint)
* [Creating a Token endpoint](https://indieweb.org/token-endpoint)

<?= $parser->defaultTransform(ob_get_clean()) ?>
