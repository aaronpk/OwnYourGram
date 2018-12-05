
<form action="/auth/start" method="get">
  <input type="url" name="me" placeholder="http://me.com" value="" class="form-control" autocomplete="url"><br>

  <input type="hidden" name="client_id" value="http://ownyourgram.com">
  <input type="hidden" name="redirect_uri" value="http://ownyourgram.com/auth/callback">

  <input type="submit" value="Sign In" class="btn btn-primary">
</form>

