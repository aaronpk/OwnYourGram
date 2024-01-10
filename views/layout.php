<!doctype html>
<html lang="en">
  <head>
    <title><?= $this->title ?></title>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
    <link rel="pingback" href="https://webmention.io/webmention?forward=https://<?=$_SERVER['SERVER_NAME']?>/webmention" />
    <link rel="webmention" href="https://<?=$_SERVER['SERVER_NAME']?>/webmention" />

    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="/bootstrap/css/bootstrap-theme.min.css">
    <link rel="stylesheet" href="/css/style.css">

    <link rel="apple-touch-icon" sizes="180x180" href="/icons/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/icons/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/icons/favicon-16x16.png">
    <link rel="manifest" href="/icons/manifest.json">
    <link rel="mask-icon" href="/icons/safari-pinned-tab.svg" color="#5bbad5">
    <link rel="shortcut icon" href="/favicon.ico">
    <meta name="msapplication-config" content="/icons/browserconfig.xml">
    <meta name="theme-color" content="#ffffff">

    <script src="/js/jquery-1.7.1.min.js"></script>
    <script src="/bootstrap/js/bootstrap.min.js"></script>
  </head>

<body role="document">
<?php if(Config::$gaid): ?>
<script type="text/javascript">
  var _gaq = _gaq || [];
  _gaq.push(['_setAccount', '<?= Config::$gaid ?>']);
  _gaq.push(['_trackPageview']);

  (function() {
    var ga = document.createElement('script'); ga.type = 'text/javascript'; ga.async = true;
    ga.src = ('https:' == document.location.protocol ? 'https://ssl' : 'http://www') + '.google-analytics.com/ga.js';
    var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(ga, s);
  })();
</script>
<?php endif; ?>

<div class="navbar navbar-inverse">
  <div class="container">
    <div class="navbar-header">
      <button type="button" class="navbar-toggle" data-toggle="collapse" data-target=".navbar-collapse">
        <span class="sr-only">Toggle navigation</span>
        <span class="icon-bar"></span>
        <span class="icon-bar"></span>
        <span class="icon-bar"></span>
      </button>
      <a class="navbar-brand" href="/">OwnYourGram</a>
    </div>
    <div class="navbar-collapse collapse">
      <ul class="nav navbar-nav">
        <?php if(p3k\session('me')) { ?>
          <li><a href="/photos">Photos</a></li>
          <li><a href="/settings">Settings</a></li>
        <?php } ?>
        <li><a href="/docs">Docs</a></li>
      </ul>
      <?php if(p3k\session('me')) { ?>
        <ul class="nav navbar-nav navbar-right">
          <li><a><?= p3k\url\display_url(p3k\session('me')) ?></a></li>
          <li><a href="/signout">Sign Out</a></li>
        </ul>
      <?php } else if(property_exists($this, 'authorizing')) { ?>
        <ul class="nav navbar-right">
          <li class="navbar-text"><?= $this->authorizing ?></li>
        </ul>
      <?php } else { ?>
        <ul class="nav navbar-right" style="font-size: 8pt;">
          <li><a href="https://indieauth.net/">What's This?</a></li>
        </ul>
      <?php } ?>
    </div>
  </div>
</div>

<div class="page">

  <div class="container">
    <?= $this->fetch($this->page . '.php') ?>
  </div>

  <div class="footer">
    <p class="credits">&copy; <?=date('Y')?> by <a href="https://aaronparecki.com">Aaron Parecki</a>.
      This code is <a href="https://github.com/aaronpk/OwnYourGram">open source</a>.
      Feel free to send a pull request, or <a href="https://github.com/aaronpk/OwnYourGram/issues">file an issue</a>.</p>
  </div>
</div>

</body>
</html>
