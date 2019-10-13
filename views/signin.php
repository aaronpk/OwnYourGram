
<form action="/auth/start" method="get">
  <input type="url" name="me" placeholder="https://example.com" value="" class="form-control" autocomplete="url"><br>

  <input type="hidden" name="client_id" value="https://ownyourgram.com">
  <input type="hidden" name="redirect_uri" value="https://ownyourgram.com/auth/callback">

  <input type="submit" value="Sign In" class="btn btn-primary">
</form>

