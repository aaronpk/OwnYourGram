<?php if($this->instagram_username): ?>

  <div class="alert alert-success">We found your Instagram account!</div>

  <p>Your website indicates that your Instagram account is <b><a href="https://instagram.com/<?= $this->instagram_username ?>">@<?= $this->instagram_username ?></a></b>.</p>

  <p>Ensure that your Instagram account links back to your website, either in the "website" field or "bio" of your Instagram profile. In the next step, we'll look for this link. We will be looking for an exact match of <b><code><?= $this->user->url ?></code></b> so make sure you enter your URL on your Instagram profile exactly as shown, including http/https as appropriate.</p>
  
  <p><a href="/instagram/verify" class="btn btn-primary">Continue</a></p>

  <p style="color: #999;">If this is not your Instagram account, make sure your website includes a rel=me link to the correct account, then refresh this page to check again.</p>

<?php else: ?>

  <div class="alert alert-danger">Counldn't find your Instagram account!</div>

  <p>You need to add a rel=me link to your Instagram profile on your website. On your home page, add a link to your Instagram profile such as the one below:</p>

  <p><pre><?= htmlspecialchars('<a href="https://instagram.com/username" rel="me">@username on Instagram</a>') ?></pre></pre>

  <p>When you've added a link to your Instagram account, you can click below to check again.</p>

  <p><a href="/instagram" class="btn btn-primary">Check Again</a></p>

<?php endif; ?>