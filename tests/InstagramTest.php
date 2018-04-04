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

    $this->assertContains([
      'url' => 'https://www.instagram.com/p/Be0lBpGDncI/',
      'published' => '2018-02-05 16:22:07'
    ], $feed['items']);

    $this->assertContains([
      'url' => 'https://www.instagram.com/p/BOuszCXhunE/',
      'published' => '2017-01-01 17:12:16'
    ], $feed['items']);
  }

  public function testGetPhoto() {
    $entry = h_entry_from_photo('https://www.instagram.com/p/BGDpqNoiMJ0/', false, true);

    $this->assertSame('2016-05-30T20:46:22-07:00', $entry['published']);
    $this->assertSame([
      'type' => ['h-card'],
      'properties' => [
        'name' => ['Burnside 26'],
        'latitude' => ['45.5228640678'],
        'longitude' => ['-122.6389405085']
      ]
    ], $entry['location']);
    $this->assertSame(['muffins','https://indiewebcat.com/'], $entry['category']);
    $this->assertSame('Meow #muffins', $entry['content']);
    $this->assertSame('https://www.instagram.com/p/BGDpqNoiMJ0/', $entry['syndication']);
    $this->assertContains('9433ea494a8b055bebabf70fd81cfa32/5B51F092/t51.2885-15/e35/13266755_877794672348882_1908663476_n.jpg', $entry['photo'][0]);
  }
}
