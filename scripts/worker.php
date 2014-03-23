<?php
chdir(dirname(__FILE__).'/..');
require 'vendor/autoload.php';
require 'lib/Savant.php';
require 'lib/config.php';
require 'lib/helpers.php';
require 'lib/markdown.php';
require 'lib/instagram.php';

echo "Watching tube: " . Config::$hostname . "-worker\n";
bs()->watch(Config::$hostname.'-worker')->ignore('default');

if(isset($pcntl_continue)) {

  while($pcntl_continue)
  {
    if(($job=bs()->reserve(2)) == FALSE)
      continue;

    process_job($job);
  } // while true

  echo "\nBye from pid " . posix_getpid() . "!\n";

} else {
  if(($job=bs()->reserve())) {
    process_job($job);
  }  
}


function process_job(&$jobData) {
  $data = json_decode($jobData->getData());

  if(!is_array($data)) {
    echo "Found bad job:\n";
    print_r($data);
    echo "\n";
    bs()->delete($jobData);
    continue;
  }

  echo "===============================================\n";
  echo "# Beginning job\n";
  print_r($data);

  foreach($data as $job) {
    // Get the instagram access token for this user ID
    $user = ORM::for_table('users')->where('instagram_user_id', $job->object_id)->find_one();

    if($user) {

      if($user->micropub_success) {
        // Retrieve recent photos for the user after the time specified in the post
        // https://api.instagram.com/v1/users/self/media/recent?min_timestamp=1394295266

        $timestamp = $job->time;
        $media_id = $job->data->media_id;

        if($photo = IG\get_photo($user, $media_id)) {
          #print_r($photo);

          $entry = h_entry_from_photo($photo);
          $photo_url = $photo->images->standard_resolution->url;

          // Download the photo to a temp folder
          echo "Downloading photo...\n";
          $filename = download_file($photo_url);

          // Send the photo to the micropub endpoint
          echo "Sending photo to micropub endpoint: ".$user->micropub_endpoint."\n";
          print_r($entry);
          echo "\n";
          $response = micropub_post($user->micropub_endpoint, $entry, $filename, $user->micropub_access_token);
          echo $response."\n";
          unlink($filename);

          // Store the request and response from the micropub endpoint in the DB so it can be displayed to the user
          $user->last_micropub_response = json_encode($response);
          $user->last_instagram_photo = $photo->id;
          $user->last_photo_date = date('Y-m-d H:i:s');
          $user->save();

          /*
          // Add the link to the photo caption

          $comment_text = '';

          if($photo->caption && $photo->caption->id) {
            $comment_id = $photo->caption->id;
            $comment_text = $photo->caption->text;

            // Now delete the comment (caption) if there is one
            $result = IG\delete_comment($user, $media_id, $comment_id);
            print_r($result);
          }

          // Re-add the caption with the citation 
          $canonical = 'http://aaron.pk/xxxxx';
          $comment_text .= ' ('.$canonical.')';
          $result = IG\add_comment($user, $media_id, $comment_text);
          print_r($result);
          */

        }
      } else {
        echo "This user has not successfully completed a test micropub post yet\n";
      }
    } else {
      echo "No user account found for Instagram user ".$job->object_id."\n";
    }

  }
  
  echo "# Job Complete\n-----------------------------------------------\n\n";
  bs()->delete($jobData);
}


