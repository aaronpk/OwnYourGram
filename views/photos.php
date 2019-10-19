<style type="text/css">
#instagram_photos_list .photo-row {
  display: flex;
  flex-direction: row;
  box-shadow: 0 1px 2px rgba(0,0,0,.075);
  border: 1px solid #ddd;
  border-radius: 4px;
  padding: 4px;
  margin-bottom: 8px;
  position: relative;
  z-index: 0;
}

.photo-img {
  width: 200px;
}
.photo-img img {
  width: 200px;
}
.photo-img.multi img:first-child {
  display: block;
}
.photo-img.multi img:not(:first-child) {
  width: 100px;
}
@media(max-width: 450px) {
  .photo-img img {
    width: 120px;
  }
  .photo-img.multi img:not(:first-child) {
    width: 60px;
  }
}

#import-preview .photo-img {
  border-radius: 3px;
  overflow: hidden;
}
#import-preview .photo-img, #import-preview .photo-img img {
  width: 300px;
}
#import-preview .photo-img.multi img:not(:first-child) {
  width: 150px;
}

#instagram_photos_list .info {
  margin-left: 6px;
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
#instagram_photos_list .caption, #import-preview .caption {
  clear: both;
  white-space: pre-wrap;
  margin: 4px 0;
  padding: 4px;
  background: #fafafa;
  border: 1px #e5e5e5 solid;
  border-radius: 4px;
}
.include-syndications-checkbox {
  margin-left: 20px;
}
#import-preview {
  max-width: 300px;
  margin: 0 auto;
}
#preview-import-form {
  margin-bottom: 1em;
}
#loading-preview {
  height: 300px;
}
</style>

<div style="margin-bottom: 3em;">
  <h2>Import a Photo</h2>

  <form id="preview-import-form">
    <div class="form-group">
      <label for="import-url">Instagram Post</label>
      <input type="url" id="import-url" class="form-control" placeholder="https://www.instagram.com/p/B3fIP5zhT0P/" value="<?= htmlspecialchars($this->input_url) ?>">
      <p class="help-block">Paste a link to one of your Instagram posts above to begin importing it</p>
    </div>
    <button type="submit" class="btn btn-primary disabled" id="preview-import-button">Preview Import</button>
  </form>

  <div id="loading-preview" class="hidden"><span class="glyphicon glyphicon-refresh glyphicon-spin"></span></div>

  <div id="import-preview" class="hidden">
    <div class="photo-img"><img></div>
    <div class="caption"></div>
    <div>
      <button class="btn btn-primary" id="do-import-button">Post Photo</button>
      <div id="posting-in-progress" class="hidden"><span class="glyphicon glyphicon-refresh glyphicon-spin"></span></div>
    </div>
    <input type="hidden" id="import-preview-id">
  </div>

  <div id="import-danger" class="hidden">
    <div class="alert alert-error"></div>
  </div>

</div>

<div id="instagram_photos_list">
<h2>Last Imported Photos</h2>
<?php foreach($this->photos as $photo): ?>

  <div class="photo-row">
    <div class="photo-img <?php echo $photo['instagram_img_list'] ? 'multi' : '' ?>">
      <?php if($photo['instagram_img_list']): ?>
        <img src="<?php echo $photo['instagram_img_list'][0] ?>">
        <?php for($i=1; $i<count($photo['instagram_img_list']); $i++) {
          echo '<img src="'.$photo['instagram_img_list'][$i].'">';
        } ?>
      <?php else: ?>
        <img src="<?php echo $photo['instagram_img'] ?>">
      <?php endif ?>
    </div>
    <div class="info">
      <div class="top">
        <span class="type-icon photo glyphicon <?php echo empty($photo['data']['video']) ? 'glyphicon-picture' : 'glyphicon-facetime-video' ?>"></span>
        <div><a href="<?php echo $photo['instagram_url'] ?>" class="ig_url">View on Instagram</a></div>
        <div style="word-break: break-all;"><a href="<?php echo $photo['canonical_url'] ?>" class="canonical_url"><?php echo $photo['canonical_url'] ?></a></div>
        <div class="caption"><?php echo (isset($photo['data']['content']) ? htmlspecialchars($photo['data']['content']) : '') ?></div>
      </div>
      <div class="bottom">
        <div class="error hidden alert alert-warning">There was an error posting the photo!</div>
        <a href="" class="btn btn-success post-button"><?php echo $photo['canonical_url'] ? 'Post Again' : 'Post' ?></a>
        <label class="checkbox-inline include-syndications-checkbox">
          <input type="checkbox" class="" name="syndicate" value="1" checked>
          Send syndications
        </label>
      </div>
    </div>
  </div>

<?php endforeach ?>
</div>

<div id="instagram_photos_error" class="hidden">
  <div class="alert alert-warning">
    It looks like you haven't imported any photos yet! If you just started using OwnYourGram, you may need to wait a day or two, or you can manually add a photo above.
  </div>
</div>

<script>
$(function(){
    $("#instagram_photos_list .post-button").click(function(){
      $("#instagram_photos_list .post-button").addClass("disabled");
      $("#instagram_photos_list .error").addClass("hidden");
      $(this).text("Working...");
      var btn = $(this);
      var sendSyndications = btn.siblings("label.checkbox-inline").find("input[type=checkbox]").prop('checked')
      $.post("/instagram/test.json", {
        id: $(this).data("id"),
        syndicate: sendSyndications
      }, function(data){
        $("#instagram_photos_list .post-button").removeClass("disabled");

        if(data.location) {
          window.location.reload();
        } else {
          btn.parent().find(".error").removeClass("hidden");
          btn.text("Post");
        }
      });
      return false;
    });

    $("#import-url").on('keyup', function(){
      if($(this).val().match(/https?:\/\/www\.instagram\.com\/p\/[a-zA-Z0-9]+/)) {
        $("#preview-import-button").removeClass("disabled");
      } else {
        $("#preview-import-button").addClass("disabled");
      }
    });

    $("#preview-import-button").click(function(){
      $("#preview-import-button").addClass("disabled");
      $("#preview-import-form").addClass("hidden");
      $("#loading-preview").removeClass("hidden");

      $.post("/instagram/preview-import.json", {
        url: $("#import-url").val()
      }, function(data){
        $("#preview-import-button").removeClass("disabled");
        $("#loading-preview").addClass("hidden");

        if(data.error) {
          $("#import-error .alert").text(data.error_description);
          $("#import-error").removeClass("hidden");
          $("#preview-import-form").removeClass("hidden");
          $("#import-preview").addClass("hidden");
        } else {
          $("#import-error").addClass("hidden");

          if(data.instagram_img_list) {
            $("#import-preview .photo-img img").attr("src", data.instagram_img_list[0]);
            for(var i=1; i<data.instagram_img_list.length; i++) {
              $("#import-preview .photo-img").append('<img src="'+data.instagram_img_list[i]+'">');
            }
            $("#import-preview .photo-img").addClass("multi");
          } else {
            $("#import-preview img").attr("src", data.instagram_img);
          }
          if(data.data.content) {
            $("#import-preview .caption").text(data.data.content);
          }
          $("#import-preview-id").val(data.id);

          $("#import-preview").removeClass("hidden");
        }

      });
      return false;
    });

    // click the preview button if a URL is prefilled from the query string
    if($("#import-url").val()) {
      $("#preview-import-button").click();
    }

    $("#do-import-button").click(function(){
      $("#do-import-button").addClass("disabled");
      $("#posting-in-progress").removeClass("hidden");
      $.post("/instagram/test.json", {
        id: $("#import-preview-id").val(),
        syndicate: 1
      }, function(data){
        if(data.location) {
          window.location = data.location;
        } else {
          $("#import-error .alert").text(data.error);
          $("#import-error").removeClass("hidden");
          $("#import-preview").addClass("hidden");
        }
      })
    });

});
</script>

