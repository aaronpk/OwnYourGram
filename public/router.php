<?php
chdir(__DIR__);

if (preg_match('/^\/(bootstrap|css|icons|images|js|favicon\.ico)\//', $_SERVER["REQUEST_URI"])) {
    return false;    // serve the requested resource as-is.
}

include('index.php');

