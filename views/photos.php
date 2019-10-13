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

#instagram_photos_list img {
  width: 200px;
  border-radius: 3px;
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
#instagram_photos_list .caption {
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
</style>


<div id="instagram_photos_list">
<h2>Last Imported Photos</h2>
<?php foreach($this->photos as $photo): ?>

  <div class="photo-row">
    <div class="photo-img">
      <?php if($photo['instagram_img_list']): ?>
        <img src="<?php echo $photo['instagram_img_list'][0] ?>" class="multi">
      <?php else: ?>
        <img src="<?php echo $photo['instagram_img'] ?>" class="multi">
      <?php endif ?>
    </div>
    <div class="info">
      <div class="top">
        <span class="type-icon photo glyphicon <?php echo empty($photo['data']['video']) ? 'glyphicon-picture' : 'glyphicon-facetime-video' ?>"></span>
        <div><a href="<?php echo $photo['instagram_url'] ?>" class="ig_url">View on Instagram</a></div>
        <div><a href="<?php echo $photo['canonical_url'] ?>" class="canonical_url"></a></div>
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
    It looks like you haven't imported any photos yet! If you just started using OwnYourGram, you may need to wait a day or two, or you can manually add a photo below.
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
});
</script>

