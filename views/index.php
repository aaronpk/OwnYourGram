  <div class="jumbotron">
    <h2>#OwnYourGram</h2>
    <p>How does it work?</p>
    <ol>
      <li>Sign in with your domain</li>
      <li>Connect your Instagram account</li>
      <li>When you post to Instagram, the photos will be sent to your site!</li>
    </ol>
    <p><a href="/signin" class="btn btn-primary btn-lg" role="button">Get Started &raquo;</a></p>
  </div>

  <div class="alert alert-success">
    <i><strong><?= $this->total_photos ?></strong> photos imported by <?= $this->total_users ?> users and counting!</i>
  </div>

  <div class="row top-users">
    <h3>Top users this week</h3>
    <?php
    foreach($this->users as $user):
    ?>
      <div class="col-xs-6 col-md-3">
        <div class="thumbnail">
          <a href="<?= $user->last_micropub_url ?>"><img src="<?= $user->last_instagram_img_url ?>"></a>
          <div class="caption">
            <p><a href="<?= $user->url ?>"><?= friendly_url($user->url) ?></a></p>
            <p>
              <?= $user->photo_count_this_week ?> photos this week<br>
              <?= $user->photo_count ?> photos imported<br>
            </p>
          </div>
        </div>
      </div>
    <?php
    endforeach;
    ?>
  </div>
