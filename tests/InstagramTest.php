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

}
