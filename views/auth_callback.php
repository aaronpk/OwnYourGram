<h2><?= $this->me ?></h2>



<?php if($this->tokenEndpoint): ?>

<pre>
<? print_r($this->token) ?>
</pre>


Below is the raw response from your token endpoint:
<pre>
<?= htmlspecialchars($this->debug) ?>
</pre>

<?php else: ?>

<p>Could not find your token endpoint. We found it last time, so double check nothing on your website has changed in the mean time.</p>

<?php endif; ?>
