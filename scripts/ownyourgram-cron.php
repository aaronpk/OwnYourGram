<?php
chdir(dirname(__FILE__).'/..');
require 'vendor/autoload.php';
require 'lib/Savant.php';
require 'lib/config.php';
require 'lib/helpers.php';
require 'lib/markdown.php';
require 'lib/instagram.php';

echo "========================================\n";
echo date('Y-m-d H:i:s') . "\n";

$users = ORM::for_table('users')
  ->where('micropub_success', 1)
  ->where_not_null('instagram_username');

if(isset($argv[1]))
  $users = $users->where('instagram_username',$argv[1]);

$users = $users->find_many();
foreach($users as $user) {

  try {

    $feed = IG\get_user_photos($user->instagram_username);

    if($feed) {
      foreach($feed['items'] as $item) {
        $url = $item['url'];

        // Skip any photos from before the cron task was launched
        if(strtotime($item['published']) < strtotime('2016-05-31T14:00:00-0700')) {
          continue;
        }

        // Check if this photo has already been imported
        $photo = ORM::for_table('photos')
          ->where('user_id', $user->id)
          ->where('instagram_url', $url)
          ->find_one();

        if(!$photo) {
          $photo = ORM::for_table('photos')->create();
          $photo->user_id = $user->id;
          $photo->instagram_url = $url;

          $entry = h_entry_from_photo($url);

          $photo->instagram_data = json_encode($entry);
          $photo->instagram_img = $entry['photo'];
          $photo->save();

          // Post to the Micropub endpoint
          $filename = download_file($entry['photo']);

          if(isset($entry['video'])) {
            $video_filename = download_file($entry['video']);
          } else {
            $video_filename = false;
          }

          // Collapse category to a comma-separated list if they haven't upgraded yet
          if($user->send_category_as_array != 1) {
            if($entry['category'] && is_array($entry['category']) && count($entry['category'])) {
              $entry['category'] = implode(',', $entry['category']);
            }
          }

          $rules = ORM::for_table('syndication_rules')->where('user_id', $user->id)->find_many();
          $syndications = '';
          foreach($rules as $rule) {
            if(stripos($entry['content'], $rule->match) !== false) {
              if(!isset($entry['mp-syndicate-to']))
                $entry['mp-syndicate-to'] = [];
              $entry['mp-syndicate-to'][] = $rule->syndicate_to;
              $syndications .= ' +'.$rule->syndicate_to_name;
            }
          }

          echo date('Y-m-d H:i:s ')."[".$user->url."] Sending ".($video_filename ? 'video' : 'photo')." ".$url." to micropub endpoint: ".$user->micropub_endpoint.$syndications."\n";

          $response = micropub_post($user->micropub_endpoint, $user->micropub_access_token, $entry, $filename, $video_filename);
          unlink($filename);

          $user->last_micropub_response = json_encode($response);
          $user->last_instagram_photo = $photo->id;
          $user->last_photo_date = date('Y-m-d H:i:s');

          if($response && preg_match('/Location: (.+)/', $response['response'], $match)) {
            $user->last_micropub_url = $match[1];
            $user->last_instagram_img_url = $entry['photo'];
            $user->photo_count = $user->photo_count + 1;
            $user->photo_count_this_week = $user->photo_count_this_week + 1;

            $photo->canonical_url = $match[1];
            $photo->save();
            echo date('Y-m-d H:i:s ')."Posted to ".$match[1]."\n";
          } else {
            // Their micropub endpoint didn't return a location, notify them there's a problem somehow
            echo date('Y-m-d H:i:s ')."This user's endpoint did not return a location header\n";
          }

          $user->save();
        }

      }
    } else {
      echo date('Y-m-d H:i:s ')."Error retrieving user's Instagram feed: ".$user->url."\n";
    }

  } catch(Exception $e) {
    echo date('Y-m-d H:i:s ')."Error processing user: ".$user->url."\n";
  }
}



