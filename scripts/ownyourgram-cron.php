<?php
chdir(dirname(__FILE__).'/..');
require 'vendor/autoload.php';

if(Config::$redis) {
  if($limit=\p3k\redis()->get('ownyourgram-ig-ratelimited')) {
    $limit = json_decode($limit, true);
    // Rate limited, check if we've passed the cutoff, and increment if not
    if(!is_array($limit)) {
      // migrate from old rate limiting
      $limit = [
        'wait' => 60,
        'from' => time(),
      ];
      \p3k\redis()->set('ownyourgram-ig-ratelimited', json_encode($limit));
      die();
    } else {
      if($limit && is_array($limit) && time() < $limit['from']+$limit['wait']) {
        // If now is before the rate limiting says to run, abort now and wait for the next loop
        die();
      } else {
        // If we've passed the rate limiting window, increment the interval and let this loop run
        $limit['wait'] *= 1.5;
        // Cap the rate limiting at 24 hours
        if($limit['wait'] > 86400) $limit['wait'] = 86400;
        $limit['from'] = time();
        \p3k\redis()->set('ownyourgram-ig-ratelimited', json_encode($limit));
      }
    }
  }
}

$users = ORM::for_table('users')
  ->where('micropub_success', 1)
  ->where_gt('tier', 0)
  ->where_not_equal('instagram_username', '');

if(isset($argv[1])) {
  $users = $users->where('instagram_username',$argv[1]);
} else {
  $users = $users->where_lt('next_poll_date', date('Y-m-d H:i:s'))
    ->order_by_asc('next_poll_date');
}

$user = $users->find_one();

if(!$user) {
  die();
}

echo "========================================\n";
echo date('Y-m-d H:i:s') . "\n";
echo "Processing User " . $user->instagram_username . "\n";
$seconds = time() - strtotime($user->next_poll_date);
echo "Target poll date: ".$user->next_poll_date." (".round($seconds/60)." minute lag)\n";

// Push this timing delay into redis to be graphed later
if(Config::$redis) {
  \p3k\redis()->lpush('ownyourgram-poll-lag', $seconds);
}

  try {

    $user->last_poll_date = date('Y-m-d H:i:s');
    $user->save();

    $feed = IG\get_user_photos($user->instagram_username);

    if(isset($feed['url']) && $feed['url'] == 'https://www.instagram.com/accounts/login/') {
      if(Config::$redis) {
        // Instagram returns the login URL when rate limited
        log_msg("Rejected by Instagram, enabling global rate limit block ", $user);
        // If there is already a rate limit in place, leave it
        if(!\p3k\redis()->get('ownyourgram-ig-ratelimited')) {
          \p3k\redis()->set('ownyourgram-ig-ratelimited', json_encode([
            'wait' => 60,
            'from' => time()
          ]));
        }
      }
      // Schedule this user again for later at the same tier
      set_next_poll_date($user);
      $user->save();
      die();
    } else {
      if(Config::$redis) {
        // If this worked, reset the rate limiting
        \p3k\redis()->del('ownyourgram-ig-ratelimited');
      }
    }

    if(!$feed || count($feed['items']) == 0) {
      $user->tier = max($user->tier - 1, 0);
      log_msg("Error retrieving user's Instagram feed. Demoting to ".$user->tier, $user);
      set_next_poll_date($user);
      $user->save();
      die();
    }

    $micropub_errors = 0;
    $successful_photos = 0;

    foreach($feed['items'] as $item) {
      $url = $item['url'];

      // Skip any photos from before the cron task was launched
      if(strtotime($item['published']) < strtotime('2016-05-31T14:00:00-0700')) {
        continue;
      }

      // Skip any photos from before their OYG account was created
      // I've been getting reports from people that they were surprised when OYG imported older photos,
      // and they only wanted it to import photos starting when they signed up for it.
      if(strtotime($item['published']) < strtotime($user->date_created)) {
        continue;
      }

      $photo = ORM::for_table('photos')
        ->where('user_id', $user->id)
        ->where('instagram_url', $url)
        ->find_one();

      // Check if this photo has already been imported.
      // The photo may already be in the DB, but not have been processed yet.
      if(!$photo || !$photo->processed) {
        if(!$photo) {
          $photo = ORM::for_table('photos')->create();
          $photo->user_id = $user->id;
          $photo->instagram_url = $url;
        }

        $entry = h_entry_from_photo($url, $user->send_media_as == 'upload', $user->multi_photo);

        if(!$entry) {
          echo "ERROR: Could not parse photo: $url\n";
          continue;
        }

        $photo->instagram_data = json_encode($entry);

        if($user->multi_photo && is_array($entry['photo']) && (count($entry['photo']) > 1)) {
          $photo->instagram_img_list = json_encode($entry['photo']);
        } else {
          if(is_array($entry['photo'])) $entry['photo'] = $entry['photo'][0];
          $photo->instagram_img = $entry['photo'];
        }

        $photo->published = date('Y-m-d H:i:s', strtotime($entry['published']));
        $photo->save();

        // Check the whitelist/blacklist
        if($user->whitelist) {
          // Default to not post unless something in the whitelist matches
          $should_import = false;

          $whitelist_matched = false;
          $blacklist_matched = false;

          // If there is a whitelist, check that the caption contains one of the words
          $whitelist_words = preg_split('/[ ]+/', $user->whitelist);
          foreach($whitelist_words as $word) {
            if(stripos($entry['content'], $word) !== false) {
              $should_import = true;
              $whitelist_matched = $word;
            }
          }

          // If there is also a blacklist, check that now
          $blacklist_words = preg_split('/[ ]+/', $user->blacklist);
          foreach($blacklist_words as $word) {
            if(stripos($entry['content'], $word) !== false) {
              $should_import = false;
              $blacklist_matched = $word;
            }
          }

          if($whitelist_matched)
            log_msg("Whitelist matched ".$whitelist_matched, $user);
          if($blacklist_matched)
            log_msg("Blacklist matched ".$blacklist_matched, $user);

          #log_msg("Whitelist: ".($should_import ? 'Import' : 'Do not import'), $user);

        } elseif($user->blacklist) {
          // Default to post unless something in the blacklist matches
          $should_import = true;

          $blacklist_matched = false;

          $blacklist_words = preg_split('/[ ]+/', $user->blacklist);
          foreach($blacklist_words as $word) {
            if(stripos($entry['content'], $word) !== false) {
              $should_import = false;
              $blacklist_matched = $word;
            }
          }

          if($blacklist_matched)
            log_msg("Blacklist matched ".$blacklist_matched, $user);

          #log_msg("Blacklist: ".($should_import ? 'Import' : 'Do not import'), $user);

        } else {
          $should_import = true;
        }

        if($should_import) {
          // Post to the Micropub endpoint

          $rules = ORM::for_table('syndication_rules')->where('user_id', $user->id)->find_many();
          $syndications = '';
          foreach($rules as $rule) {
            if($rule->match == '*' || stripos($entry['content'], $rule->match) !== false) {
              if(!isset($entry['mp-syndicate-to']))
                $entry['mp-syndicate-to'] = [];
              $entry['mp-syndicate-to'][] = $rule->syndicate_to;
              $syndications .= ' +'.$rule->syndicate_to_name;
            }
          }

          if($user->add_tags) {
            $tags = preg_split('/[ ]+/', $user->add_tags);
            if(!isset($entry['category']))
              $entry['category'] = [];
            foreach($tags as $t)
              $entry['category'][] = $t;
            $entry['category'] = array_unique($entry['category']);
          }

          log_msg("Sending ".(isset($entry['video']) ? 'video' : 'photo')." ".$url." to micropub endpoint: ".$user->micropub_endpoint.$syndications, $user);

          $response = micropub_post($user, $entry);

          $user->last_micropub_response = substr(json_encode($response),0,65535);
          $user->last_instagram_photo = $photo->id;
          $user->last_photo_date = date('Y-m-d H:i:s');

          if($response && isset($response['headers']['Location'])
            && in_array($response['code'], [201,202,301,302])) {
            $photo_url = $response['headers']['Location'][0];
            $user->last_micropub_url = $photo_url;
            $user->last_instagram_img_url = is_array($entry['photo']) ? $entry['photo'][0] : $entry['photo'];
            $user->photo_count = $user->photo_count + 1;
            $user->photo_count_this_week = $user->photo_count_this_week + 1;

            $photo->canonical_url = $photo_url;
            $successful_photos++;
            log_msg("Posted to ".$photo_url, $user);
          } else {
            // Their micropub endpoint didn't return a location, notify them there's a problem somehow
            log_msg("There was an error posting this photo. Response code was: ".$response['code'], $user);
            $micropub_errors++;
            if($response['code'] == 403) {
              break;
            }
          }
        }

        $photo->processed = 1;
        $photo->save();

        $user->save();
      }

    }

    // After importing this batch, look at the user's posting frequency and determine their polling tier.
    if($micropub_errors > 0) {
      // Micropub errors demote the user to a lower tier.
      // If they're already at the lowest tier, this will disable polling their account until they log back in.
      $user->tier = max($user->tier - 1, 0);
      log_msg("Encountered a Micropub error. Demoting to tier ".$user->tier, $user);
      set_next_poll_date($user);
      $user->save();
    } else {
      // Check how many photos they've taken in the last 14 days
      $previous_tier = $user->tier;

      $count = ORM::for_table('photos')
        ->where('user_id', $user->id)
        ->where_gt('published', date('Y-m-d H:i:s', strtotime('-14 days')))
        ->count();
      if($count >= 7) {
        $new_tier = 4;
      } elseif($count >= 4) {
        $new_tier = 3;
      } elseif($count >= 2) {
        $new_tier = 2;
      } else {
        $new_tier = 1;
      }

      if($new_tier > $previous_tier && $successful_photos > 0) {
        log_msg('Upgrading user to tier ' . $new_tier, $user);
        $user->tier = $new_tier;
        set_next_poll_date($user);
        $user->save();
      } elseif($new_tier < $previous_tier) {
        log_msg('Demoting user to tier ' . $new_tier, $user);
        $user->tier = $new_tier;
        set_next_poll_date($user);
        $user->save();
      } else {
        log_msg('Keeping user at the same tier: '.$new_tier, $user);
        set_next_poll_date($user);
        $user->save();
      }
    }

  } catch(Exception $e) {
    // Bump down a tier on errors
    $user->tier = max($user->tier - 1, 0);
    log_msg("There was an error processing this user. Demoting to tier ".$user->tier." '".$e->getMessage()."'", $user);
    set_next_poll_date($user);
    $user->save();
  }



function log_msg($msg, $user) {
  echo date('Y-m-d H:i:s ');
  if($user)
    echo '[' . $user->url . '] ';
  echo $msg . "\n";
}

function set_next_poll_date(&$user) {
  // Use the user's current polling tier and update their next poll date
  $seconds = tier_to_seconds($user->tier);
  $last = strtotime($user->last_poll_date);
  $next = $last + $seconds;
  $user->next_poll_date = date('Y-m-d H:i:s', $next);
}
