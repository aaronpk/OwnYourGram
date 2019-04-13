<?php

use PHPUnit\Framework\TestCase;

final class InstagramTest extends TestCase
{
  public function testTest() {
    $this->assertTrue(true);
  }

  public function testCheckProfileMatchesWebsite() {
    $profile = IG\get_profile('pk_spam', true);

    $this->assertTrue(IG\profile_matches_website('pk_spam', 'https://aaronparecki.com/', $profile));
    $this->assertTrue(IG\profile_matches_website('pk_spam', 'https://aaronparecki.com.dev/', $profile));
    $this->assertTrue(IG\profile_matches_website('pk_spam', 'http://aaronpk.micro.blog/', $profile));
  }

  public function testGetUserPhotos() {
    $feed = IG\get_user_photos('pk_spam', true);

    $this->assertSame('pk_spam', $feed['username']);
    $this->assertCount(12, $feed['items']);

    $this->assertEquals('https://www.instagram.com/p/BsdlOmLh_IX/', $feed['items'][0]['url']);
    $this->assertEquals('2019-01-10T17:20:52+00:00', $feed['items'][0]['published']);

    $this->assertEquals('https://www.instagram.com/p/BGFdtAViMJy/', $feed['items'][11]['url']);
    $this->assertEquals('2016-05-31T20:40:22+00:00', $feed['items'][11]['published']);
  }

  public function testGetPhoto() {
    $entry = h_entry_from_photo('https://www.instagram.com/p/BGDpqNoiMJ0/', false, true);

    $this->assertSame('2016-05-30T20:46:22-07:00', $entry['published']);
    $this->assertSame(['h-card'], $entry['location']['type']);
    $this->assertSame(['Burnside 26'], $entry['location']['properties']['name']);
    $this->assertGreaterThan(45.52, $entry['location']['properties']['latitude'][0]);
    $this->assertLessThan(45.53, $entry['location']['properties']['latitude'][0]);
    $this->assertGreaterThan(-122.639, $entry['location']['properties']['longitude'][0]);
    $this->assertLessThan(-122.638, $entry['location']['properties']['longitude'][0]);
    $this->assertSame(['muffins','https://indiewebcat.com/'], $entry['category']);
    $this->assertSame('Meow #muffins', $entry['content']);
    $this->assertSame('https://www.instagram.com/p/BGDpqNoiMJ0/', $entry['syndication']);
    $this->assertContains('13266755_877794672348882_1908663476_n.jpg', $entry['photo'][0]);
  }
}
