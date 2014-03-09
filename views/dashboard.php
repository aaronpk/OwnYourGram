<h1>Congrats, you successfully signed in!</h1>

<?php if($this->photo): ?>
  <p>Below is the latest photo from your Instagram account</p>
  <div class="row">
    <div class="col-md-4">
      <form role="form">
        <div class="form-group">
          <img src="<?= $this->photo->images->standard_resolution->url ?>" class="img-thumbnail">
        </div>
        <div class="form-group">
          <label for="photo_url"><code>content</code></label>
          <input type="text" id="photo_content" value="<?= $this->photo->caption ? k($this->photo->caption, 'text') : '' ?>" class="form-control">
        </div>
        <div class="form-group">
          <label for="photo_url">Date (<code>published</code>)</label>
          <input type="text" id="photo_published" value="<?= $this->date ?>" class="form-control">
        </div>
        <div class="form-group">
          <label for="photo_url">Location (<code>location</code>)</label>
          <input type="text" id="photo_location" value="<?= $this->photo->location ? ('geo:'.$this->photo->location->latitude.','.$this->photo->location->longitude) : '' ?>" class="form-control">
        </div>
        <div class="form-group">
          <label for="photo_url">Place Name (<code>place_name</code>)</label>
          <input type="text" id="photo_place_name" value="<?= $this->photo->location ? k($this->photo->location, 'name') : '' ?>" class="form-control">
        </div>
        <div class="form-group">
          <label for="photo_url">Category (<code>category</code>)</label>
          <input type="text" id="photo_category" value="<?= $this->photo->tags ? implode(',', $this->photo->tags) : '' ?>" class="form-control">
        </div>
        <div class="form-group">
          <label for="photo_url">URL (sent as a file named <code>photo</code>)</label>
          <input type="text" id="photo_url" value="<?= $this->photo->images->standard_resolution->url ?>" class="form-control">
        </div>
      </form>
    </div>
    <div class="col-md-8">
      <button class="btn btn-success" id="btn_test_post">Test Post</button>
      <p>Click the "Test" button to send a Micropub request with this photo to your endpoint. After you successfully handle the request and post this photo to your site, the real-time stream will be enabled for your Instagram account.</p>
      <pre id="test_response" style="width: 100%; min-height: 240px;"></pre>
    </div>
  </div>
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
    }, function(data){
      var response = JSON.parse(data);
      console.debug(response.response);
      $("#test_response").html(response.response);
    })
  });
});
</script>
