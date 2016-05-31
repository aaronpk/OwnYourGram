<?php if($this->user->instagram_username && $this->user->micropub_success): ?>
  <div class="bs-callout bs-callout-success">
    <p>Your account is active and we're sending your Instagram photos to your site!</p>
    <p>Please note that due to changes in Instagram's API, we are unable to send your photos in realtime, so you may experience some delay between posting photos on Instagram and seeing them on your website.</p>
  </div>
<?php else: ?>
  <div class="bs-callout bs-callout-danger">
    <p>Your account is almost ready. Unfortunately we aren't accepting new users right now. Check back again later, or see if there is any discussion on <a href="https://github.com/aaronpk/OwnYourGram/issues">GitHub Issues</a>.</p>
  </div>
<?php endif; ?>

