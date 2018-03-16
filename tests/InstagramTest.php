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
}
