  <div class="jumbotron">
    <h2>#OwnYourGram</h2>
    <?php if($this->signed_in): ?>
      <p><a href="/dashboard" class="btn btn-primary btn-lg" role="button">Dashboard &raquo;</a></p>
    <?php else: ?>
      <p>How does it work?</p>
      <ol>
        <li>Sign in with your domain</li>
        <li>Connect your Instagram account</li>
        <li>When you post to Instagram, the photos will be sent to your site!</li>
      </ol>
      <p><a href="/signin" class="btn btn-primary btn-lg" role="button">Get Started &raquo;</a></p>
    <?php endif; ?>
  </div>

  <div class="alert alert-success">
    <i><strong><?= $this->total_photos ?></strong> grams owned by <?= $this->total_users ?> users on their own sites and counting!</i>
  </div>

  <div class="top-users">
    <h3>Top users this week</h3>
    
    <?php
    if(count($this->users) == 0):
      ?>
      <p>No photos were imported in the last 7 days.</p>
      <?php
    endif;
    ?>

    <div class="photo-list">
      <?php
      foreach($this->users as $user):
      ?>
        <div class="photo">
          <div class="thumbnail">
            <a href="<?= $user->last_micropub_url ?>"><img src="<?= $user->last_instagram_img_url ?>"></a>
            <div class="caption">
              <p><a href="<?= $user->url ?>"><?= friendly_url($user->url) ?></a></p>
              <p>
                <?= $user->num ?> this week<br>
                <?= $user->photo_count ?> total<br>
              </p>
            </div>
          </div>
        </div>
      <?php
      endforeach;
      ?>
    </div>
  </div>
