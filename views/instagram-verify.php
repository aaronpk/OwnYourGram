<?php if($this->success): ?>

  <div class="alert alert-success">All set!</div>

  <p>We found the link in your Instagram profile to <b><code><?= $this->user->url ?></code></b></p>

  <p><a href="/dashboard" class="btn btn-primary">Continue to Dashboard</a></p>

<?php else: ?>

  <div class="alert alert-danger">Error verifying your Instagram account!</div>

  <p>You need to add a link to your website in your Instagram account. You can add it in either the "website" field or the "biography" fields. Make sure to add exactly the URL you signed in here: <b><code><?= $this->user->url ?></code></b>.</p>

  <p>When you've added a link in your Instagram account, you can click below to check again.</p>

  <p><a href="/instagram/verify" class="btn btn-primary">Check Again</a></p>

<?php endif; ?>