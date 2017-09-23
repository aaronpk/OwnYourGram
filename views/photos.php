
<div style="text-align: right; margin: 8px;">
  <a id="reload_photos" class="btn btn-xs btn-default">Reload Photos</a>
</div>

<div id="loading"><span class="glyphicon glyphicon-refresh glyphicon-spin"></span></div>

<div id="instagram_photos_list"></div>

<div id="instagram_photos_error" class="hidden">
  <div class="alert alert-danger">
    We didn't find any photos in your Instagram profile. If your account is set to "private", OwnYourGram will not be able to import your photos. If you're sure you've posted photos and your account is public and are still seeing this message, you can <a href="https://github.com/aaronpk/OwnYourGram/issues">report an issue</a>.
  </div>
</div>

<script>
function load_photos(force_refresh) {
  $("#loading").removeClass("hidden");
  $("#instagram_photos_list").html('');

  $.get("/instagram/photos.json", {
    force_refresh: force_refresh?1:0,
    num: 20
  }, function(data){
    $("#loading").addClass("hidden");

    if(data.items.length == 0) {
      $("#instagram_photos_error").removeClass("hidden");
    }

    $("#instagram_photos_list").html('<p>Showing your latest 20 photos below.</p>');

    var template = $("#photo-template").html();
    for(var i in data.items) {
      var item = $(template).clone();
      var photo = data.items[i];
      $(item).find("img").attr("src",photo.instagram_img);
      $(item).find(".btn").attr("data-id", photo.id);
      if(photo.data.content == "") {
        $(item).find(".caption").addClass("hidden");
      } else {
        $(item).find(".caption").text(photo.data.content);
      }
      if(photo.canonical_url) {
        $(item).find(".btn-success").text("Post Again");
      }
      $(item).find(".canonical_url").attr("href", photo.canonical_url).text(photo.canonical_url);
      $(item).find(".ig_url").attr("href", photo.instagram_url);
      if(photo.video) {
        $(item).find('.glyphicon-picture').removeClass('glyphicon-picture').addClass('glyphicon-facetime-video');
      }
      $("#instagram_photos_list").append(item);
    }

    if(data.targets == null) {
      $(".include-syndications-checkbox").addClass("hidden");
    }

    $("#instagram_photos_list .btn").click(function(){
      $("#instagram_photos_list .btn").addClass("disabled");
      $("#instagram_photos_list .error").addClass("hidden");
      $(this).text("Working...");
      var btn = $(this);
      var sendSyndications = btn.siblings("label.checkbox-inline").find("input[type=checkbox]").prop('checked')
      $.post("/instagram/test.json", {
        id: $(this).data("id"),
        syndicate: sendSyndications
      }, function(data){
        $("#instagram_photos_list .btn").removeClass("disabled");
        $("#loading").addClass("hidden");

        if(data.location) {
          load_photos();
        } else {
          btn.parent().find(".error").removeClass("hidden");
          btn.text("Post");
        }
      });
      return false;
    });
  });
}

$(function(){
  load_photos(false);

  $("#reload_photos").click(function(){
    load_photos(true);
    return false;
  });
});
</script>

<script id="photo-template" type="text/x-ownyourgram-template">
  <div class="photo-row">
    <div>
      <img src="">
    </div>
    <div class="info">
      <div class="top">
        <span class="type-icon photo glyphicon glyphicon-picture"></span>
        <div><a href="" class="ig_url">View on Instagram</a></div>
        <div><a href="" class="canonical_url"></a></div>
        <div class="caption"></div>
      </div>
      <div class="bottom">
        <div class="error hidden alert alert-warning">There was an error posting the photo!</div>
        <a href="" class="btn btn-success">Post</a>
        <label class="checkbox-inline include-syndications-checkbox">
          <input type="checkbox" class="" name="syndicate" value="1" checked>
          Send syndications
        </label>
      </div>
    </div>
  </div>
</script>

<style type="text/css">
#instagram_photos_list .photo-row {
  display: flex;
  flex-direction: row;
  box-shadow: 0 1px 2px rgba(0,0,0,.075);
  border: 1px solid #ddd;
  border-radius: 4px;
  padding: 4px;
  margin-bottom: 8px;
}
#instagram_photos_list img {
  width: 200px;
  border-radius: 3px;
  margin-right: 4px;
}
#instagram_photos_list .info {
  display: flex;
  flex-direction: column;
  width: 100%;
}
#instagram_photos_list .type-icon {
  float: left;
  font-size: 36px;
  text-align: right;
  color: #ccc;
  margin-right: 4px;
  margin-bottom: 4px;
}
#instagram_photos_list .top {
  flex: 1 0;
}
#instagram_photos_list .bottom {
  flex: 0 0;
}
#instagram_photos_list .caption {
  clear: both;
  white-space: pre-wrap;
  margin: 4px 0;
  padding: 4px;
  background: #fafafa;
  border: 1px #e5e5e5 solid;
  border-radius: 4px;
}
</style>
