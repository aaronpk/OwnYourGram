<?php
if($this->user->send_category_as_array != 1):
?>
  <div class="alert alert-warning" id="array_notice">
    <b>Upcoming change!</b>
    The Micropub spec now requires values be sent as arrays instead of as comma-separated lists. When
    you are ready to receive the category property as an array, click the button below to switch. <a href="https://www.w3.org/TR/micropub/#form-encoded-and-multipart-requests">More Info</a>
    <p><button class="btn btn-default" id="micropub_array">Upgrade me!</button></p>
  </div>
<?php
endif;
?>

<?php if($this->user->instagram_username && $this->user->micropub_success): ?>
  <div class="bs-callout bs-callout-success">
    <p><b>Your account is active and we're sending your Instagram photos to your site!</b></p>
    <p>Please note that due to changes in Instagram's API, we are unable to send your photos in realtime, so you may experience some delay between posting photos on Instagram and seeing them on your website.</p>
  </div>
<?php else: ?>
  <div class="bs-callout bs-callout-warning">
    <p>Alright, that's progress! We're almost ready to start sending your Instagram photos to your website.</p>
  </div>

  <h2>Test Post</h2>

  <p>Before we enable automatic posting, you need to first test that your website accepts the requests that OwnYourGram sends. Some of your latest Instagram photos are below. Click the "post" button on any of them, and we'll send that photo to your website. If your website accepts the photo, then automatic posting will be enabled!</p>

  <p><a href="/docs">Read the documentation</a> on what fields OwnYourGram sends to your website.</p>

  <div id="instagram_photos"></div>
  <div style="clear:both;"></div>

  <div id="loading" class="hidden"><span class="glyphicon glyphicon-refresh glyphicon-spin"></span></div>

  <div id="test-error" class="hidden">
    <div class="alert alert-warning">
      <b>Error!</b>
      <p>There was an error posting the photo to your website. Please check that you're handling the <a href="/docs">expected parameters</a> and returning the "Location" header and try again.</p>
    </div>
    <p>Raw response:</p>
    <pre></pre>
  </div>

  <div id="test-success" class="hidden">
    <div class="bs-callout bs-callout-success">
      <b>Great!</b>
      <p>Your website accepted the photo! View your post at the link below. Automatic import is now enabled for your site!</p>
      <a href="">View Post</a>
    </div>
  </div>

  <script>
  $(function(){
    $.get("/instagram/photos.json", function(data){
      var template = $("#photo-template").html();
      for(var i in data.items) {
        var item = $(template).clone();
        $(item).find("img").attr("src",data.items[i].instagram_img);
        $(item).find(".btn").attr("data-id", data.items[i].id);
        $("#instagram_photos").append(item);
      }
      $("#instagram_photos .btn").click(function(){
        $("#instagram_photos .btn").addClass("disabled");
        $("#loading").removeClass("hidden");

        $.post("/instagram/test.json", {
          id: $(this).data("id")
        }, function(data){
          $("#instagram_photos .btn").removeClass("disabled");
          $("#loading").addClass("hidden");

          if(data.location) {
            $("#test-error").addClass("hidden");
            $("#test-success a").attr("href", data.location);
            $("#test-success").removeClass("hidden");
          } else {
            $("#test-success").addClass("hidden");
            $("#test-error").removeClass("hidden");
            $("#test-error pre").text(data.response);
          }
        });
        return false;
      });
    });

    $("#micropub_array").click(function(){
      $.post("/prefs/array", {
        upgrade: "yes"
      }, function(data) {
        $("#array_notice").hide();
        window.location = window.location;
      });
    });

  });
  </script>
  <script id="photo-template" type="text/x-ownyourgram-template">
    <div class="col-xs-6 col-md-3">
      <div class="thumbnail">
        <img src="">
        <div class="caption" style="text-align: center;">
          <a href="" class="btn btn-success">Post</a>
        </div>
      </div>
    </div>
  </script>
<?php endif; ?>

