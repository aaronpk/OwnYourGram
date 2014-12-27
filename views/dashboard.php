
<?php if($this->entry): ?>
  <div class="row">
    <div class="col-md-4">
      <form role="form">
        <div class="form-group">
          <img src="<?= $this->photo_url ?>" class="img-thumbnail">
        </div>
        <div class="form-group">
          <label for="photo_content"><code>content</code></label>
          <input type="text" id="photo_content" value="<?= $this->entry['content'] ?>" class="form-control">
        </div>
        <div class="form-group">
          <label for="photo_published">Date (<code>published</code>)</label>
          <input type="text" id="photo_published" value="<?= $this->entry['published'] ?>" class="form-control">
        </div>
        <div class="form-group">
          <label for="photo_location">Location (<code>location</code>)</label>
          <input type="text" id="photo_location" value="<?= $this->entry['location'] ?>" class="form-control">
        </div>
        <div class="form-group">
          <label for="photo_place_name">Place Name (<code>place_name</code>)</label>
          <input type="text" id="photo_place_name" value="<?= $this->entry['place_name'] ?>" class="form-control">
        </div>
        <div class="form-group">
          <label for="photo_category">Category (<code>category</code>)</label>
          <input type="text" id="photo_category" value="<?= $this->entry['category'] ?>" class="form-control">
        </div>
        <div class="form-group">
          <label for="photo_url">Photo URL (sent as a file named <code>photo</code>)</label>
          <input type="text" id="photo_url" value="<?= $this->photo_url ?>" class="form-control">
        </div>
        <div class="form-group">
          <label for="photo_syndication">Instagram URL (<code>syndication</code>)</label>
          <input type="text" id="photo_syndication" value="<?= $this->entry['syndication'] ?>" class="form-control">
        </div>
      </form>
    </div>
    <div class="col-md-8">
      <button class="btn btn-success" id="btn_test_post">Test Post</button>
      <p>Click the "Test" button to send a Micropub request with this photo to your endpoint. After you successfully handle the request and post this photo to your site, the real-time stream will be enabled for your Instagram account.</p>
      <p>The request will be sent to your Micropub endpoint, <code><?= $this->micropub_endpoint ?></code>. See below the photo on the left to see the full list of fields that will be sent to the endpoint.</p>

      <div class="alert alert-success hidden" id="test_success"><strong>Success! We found a Location header in the response!</strong><br>Your photo should be posted on your website now, and the realtime stream for your account is enabled!</div>
      <div class="alert alert-danger hidden" id="test_error"><strong>Your endpoint did not return a Location header.</strong><br>See <a href="/creating-a-micropub-endpoint">Creating a Micropub Endpoint</a> for more information.</div>

      <?php if($this->test_response): ?>
        <h4>Last response from your Micropub endpoint</h4>
      <?php endif; ?>
      <pre id="test_response" style="width: 100%; min-height: 240px;"><?= htmlspecialchars($this->test_response) ?></pre>
    </div>
  </div>
<?php else: ?>
  <p>We couldn't find any photos in your Instagram account. Come back after you've posted a photo.</p>
<?php endif; ?>

<script>
$(function(){
  $("#btn_test_post").click(function(){
    $.post("/micropub/test", {
      content: $("#photo_content").val(),
      url: $("#photo_url").val(),
      published: $("#photo_published").val(),
      location: $("#photo_location").val(),
      place_name: $("#photo_place_name").val(),
      category: $("#photo_category").val(),
      syndication: $("#photo_syndication").val()
    }, function(data){
      var response = JSON.parse(data);
      if(response.location != false) {
        $("#test_success").removeClass('hidden');
        $("#test_error").addClass('hidden');
      } else {
        $("#test_success").addClass('hidden');
        $("#test_error").removeClass('hidden');
      }
      $("#test_response").html(response.response);
    })
  });
});
</script>
