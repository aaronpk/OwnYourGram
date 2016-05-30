<?php if($this->success): ?>

  <div class="alert alert-success">All set!</div>

  <p>Your Instagram account links back to "<b><?= $this->user->url ?></b>".</p>

  <p>Ensure that your Instagram account links back to your website, either in the "website" field or "bio" of your Instagram profile. In the next step, we'll look for this link. We will be looking for an exact match of "<b><?= $this->user->url ?></b>" so make sure you enter your URL on your Instagram profile exactly as shown, including http/https as appropriate.</p>
  
  <p><a href="/dashboard" class="btn btn-primary">Continue to Dashboard</a></p>

  <p style="color: #999;">If this is not your Instagram account, make sure your website includes a rel=me link to the correct account, then refresh this page to check again.</p>

<?php else: ?>

  <div class="alert alert-danger">Error verifying your Instagram account!</div>

  <p>You need to add a link to your website in your Instagram account. You can add it in either the "website" field or the "biography" fields. Make sure to add exactly the URL you signed in here: "<b><?= $this->user->url ?></b>".</p>

  <p>When you've added a link in your Instagram account, you can click below to check again.</p>

  <p><a href="/instagram/verify" class="btn btn-primary">Check Again</a></p>

<?php endif; ?>