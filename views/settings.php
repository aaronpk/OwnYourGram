<?php if($this->user->instagram_username && $this->user->micropub_success): ?>
  <?php if($this->user->tier == 0): ?>
  <div class="bs-callout bs-callout-danger">
    <p>Your account has been disabled. To enable your account again, please <a href="/photos">manually import a photo</a>.</p>
  </div>
  <?php else: ?>
  <div class="bs-callout bs-callout-success">
    <p><b>Your account is active and we're sending your Instagram photos to your site!</b></p>
    <p>Please note that due to changes in Instagram's API, we are unable to send your photos in realtime. The more photos you post, the more often OwnYourGram will check your account. <a href="/docs">See the documentation</a> for more information about the polling tiers.</p>
    <p>Your account is currently being checked <b>every <?= polling_tier_description($this->user->tier) ?></b>.</p>
    <?php if($this->user->last_poll_date): ?>
      <p>Your account was last checked <b><?= time_ago($this->user->last_poll_date) ?></b> and will be checked again in <b><?= time_until_next_poll($this->user->last_poll_date, $this->user->tier) ?></b>.</p>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <h3>Settings</h3>

  <div class="panel">
    <h4>Instagram</h4>

    <p>You are logged in as <b><?= $this->user->url ?></b> and are connected to the Instagram username <b><a href="https://instagram.com/<?= $this->user->instagram_username ?>"><?= $this->user->instagram_username ?></a></b>.</p>

    <a href="#" id="disconnect-instagram" class="btn btn-xs btn-warning">Disconnect Instagram</a>
  </div>

  <div class="panel container-fluid">
    <form class="row">
      <div class="col-md-12 always-add-tags">
        <h4>Always Add Tags</h4>
        <p>If you'd like, OwnYourGram can add one or more tags to your post when it sends it to your site. Enter one or more space-separated words below and they will be sent along with any hashtags in your photo caption.</p>
        <textarea class="form-control" id="always-add-tags" placeholder="space-separated tags"><?= htmlspecialchars($this->user->add_tags) ?></textarea>
        <input type="button" class="btn btn-primary" value="Save" id="add-tags-save" style="margin-top:4px;">
        <div class="hidden check">&check;</div>
      </div>
    </form>
  </div>

  <div class="panel container-fluid">
    <form class="row">
      <div class="col-md-6 whitelist">
        <h4>Match Photos</h4>
        <p>Import photos <b>only if</b> the photo caption has one of the keywords listed here.</p>
        <textarea class="form-control" id="whitelist-keywords" placeholder="space-separated keywords"><?= htmlspecialchars($this->user->whitelist) ?></textarea>
        <input type="button" class="btn btn-primary" value="Save" id="whitelist-save" style="margin-top:4px;">
        <div class="hidden check">&check;</div>
      </div>
      <div class="col-md-6 blacklist">
        <h4>Block Photos</h4>
        <p>Prevent importing photos that contain any of the keywords listed here.</p>
        <textarea class="form-control" id="blacklist-keywords" placeholder="space-separated keywords"><?= htmlspecialchars($this->user->blacklist) ?></textarea>
        <input type="button" class="btn btn-primary" value="Save" id="blacklist-save" style="margin-top:4px;">
        <div class="hidden check">&check;</div>
      </div>
    </form>
  </div>

  <div id="automatic-syndication" class="hidden panel">
    <h4>Automatic Syndication</h4>

    <p>You can add rules that will match words in your Instagram post and tell your Micropub endpoint to syndicate your post to various destinations. You can use this to make rules like "syndicate my photos tagged #indieweb to Twitter". Enter <code>*</code> to match every photo.</p>

    <table class="table">
      <tr>
        <td>Keyword</td>
        <td>Syndication Target</td>
        <td width="110"></td>
      </tr>
      <?php foreach($this->rules as $rule): ?>
        <tr class="rule">
          <td><?= htmlspecialchars($rule->match); ?></td>
          <td><?= htmlspecialchars($rule->syndicate_to_name); ?></td>
          <td align="left"><a href="#" class="hidden delete" data-id="<?= $rule->id ?>">&times;</a></td>
        </tr>
      <?php endforeach; ?>
      <tr>
        <td><input type="text" id="new-syndicate-to-keyword" class="form-control"></td>
        <td>
          <select name="new-syndicate-to" id="new-syndicate-to" class="form-control">
            <option value="">-- select an endpoint --</option>
          </select>
        </td>
        <td><input type="button" id="new-syndicate-to-btn" class="btn btn-primary" value="Add Rule"></td>
      </tr>
    </table>

    <p style="font-size:0.9em; color: #999;">Note that OwnYourGram won't actually post anything to Twitter or Facebook for you. All this does is set the appropriate parameter in the Micropub request to indicate to your Micropub endpoint that the post should be syndicated. If you don't yet have this set up, you might want to try <a href="https://indieweb.org/silo.pub">silo.pub</a> or <a href="https://www.brid.gy/publish">Bridgy Publish</a> for an easy way to post to Twitter, Facebook and others.</p>
  </div>

  <div class="panel">
    <h4>Syndication Endpoints</h4>

    <a href="javascript:reload_syndication_endpoints()" class="btn btn-xs btn-default">Reload</a>

    <div id="syndication-endpoints">
      <div class="alert alert-warning hidden" style="margin-top: 1em;">
        <b>No Syndication Targets</b>
        <p>OwnYourGram didn't find any syndication targets at your Micropub endpoint. Learn more about <a href="https://www.w3.org/TR/micropub/#syndication-targets">Micropub syndication</a>.</p>
        <p class="error hidden">Error: <span class="details"></span></p>
      </div>
      <ul class="list" style="margin-top: 1em;"></ul>
    </div>
  </div>

  <div id="send-media-as" class="panel">
    <h4>Micropub Settings</h4>

    <form>
      <p>Send photo and videos as:</p>
      <ul>
        <li><input type="radio" name="send_media_as" value="upload" <?= $this->user->send_media_as == 'upload' ? 'checked="checked"' : '' ?>> Multipart Upload</li>
        <li><input type="radio" name="send_media_as" value="url" <?= $this->user->send_media_as == 'url' ? 'checked="checked"' : '' ?>> URLs (JSON request)</li>
      </ul>
      <p class="note">Note: Choosing the JSON option will also change the location property to send a named location as an h-card rather than a string <code>geo://</code> URI. See <a href="/docs#json">the documentation</a> for more details.</p>

      <p>For Instagram posts with multiple photos, send:</p>
      <ul>
        <li><input type="radio" name="send_multi" value="0" <?= $this->user->multi_photo == 0 ? 'checked="checked"' : '' ?>> only the first photo</li>
        <li><input type="radio" name="send_multi" value="1" <?= $this->user->multi_photo == 1 ? 'checked="checked"' : '' ?>> all photos</li>
      </ul>
      <p class="note">Note: People tagged in the individual photos will be tagged in the main post rather than in each individual image. If any of the items in the Instagram post are videos, only the poster frame will be used.</p>

      <input type="button" class="btn btn-primary" value="Save" id="settings-save-button" style="margin-top:4px;">
      <div class="hidden check">&check;</div>
    </form>
  </div>

  <script>
  $(function(){
    $.get("/settings/syndication-targets.json", function(data){
      handle_discovered_syndication_targets(data);
    });

    $("#new-syndicate-to-btn").click(function(){
      if($("#new-syndicate-to-keyword").val() != "" && $("#new-syndicate-to").val() != "") {
        $.post("/settings/syndication-rules.json", {
          action: 'create',
          keyword: $("#new-syndicate-to-keyword").val(),
          target: $("#new-syndicate-to").val(),
          target_name: $("#new-syndicate-to :selected").text()
        }, function(data){
          window.location.reload();
        });
      }
      return false;
    });

    $("#automatic-syndication .rule").on("mouseover", function(){
      $(this).find(".delete").removeClass("hidden");
    });
    $("#automatic-syndication .rule").on("mouseout", function(){
      $(this).find(".delete").addClass("hidden");
    });
    $("#automatic-syndication .delete").click(function(){
      $.post("/settings/syndication-rules.json", {
        action: 'delete',
        id: $(this).data('id')
      }, function(data){
        window.location.reload();
      });
      return false;
    });

    $("#whitelist-save").click(function(){
      $("#whitelist-save").addClass("disabled");
      $.post("/prefs/save", {
        whitelist: $("#whitelist-keywords").val()
      }, function(data){
        $("#whitelist-save").removeClass("disabled");
        $(".whitelist .check").removeClass("hidden");
        setTimeout(function(){
          $(".whitelist .check").addClass("hidden");
        }, 500);
      });
    });

    $("#blacklist-save").click(function(){
      $("#blacklist-save").addClass("disabled");
      $.post("/prefs/save", {
        blacklist: $("#blacklist-keywords").val()
      }, function(data){
        $("#blacklist-save").removeClass("disabled");
        $(".blacklist .check").removeClass("hidden");
        setTimeout(function(){
          $(".blacklist .check").addClass("hidden");
        }, 500);
      });
    });

    $("#add-tags-save").click(function(){
      $("#add-tags-save").addClass("disabled");
      $.post("/prefs/save", {
        add_tags: $("#always-add-tags").val()
      }, function(data){
        $("#add-tags-save").removeClass("disabled");
        $(".always-add-tags .check").removeClass("hidden");
        setTimeout(function(){
          $(".always-add-tags .check").addClass("hidden");
        }, 500);
      });
    });

    $("#settings-save-button").click(function(){
      $("#blacklist-save").addClass("disabled");
      $.post("/prefs/save", {
        send_media_as: $("input[name=send_media_as]:checked").val(),
        multi_photo: $("input[name=send_multi]:checked").val(),
      }, function(data){
        $("#settings-save-button").removeClass("disabled");
        $("#send-media-as .check").removeClass("hidden");
        setTimeout(function(){
          $("#send-media-as .check").addClass("hidden");
        }, 500);
      });
    });

    $("#disconnect-instagram").click(function(){
      $.post("/settings/instagram.json", {
        action: 'disconnect'
      }, function(data){
        window.location = window.location;
      });
      return false;
    });

  });

  function handle_discovered_syndication_targets(data) {
    if(data.targets) {

      $("#syndication-endpoints .list").html('');
      $("#new-syndicate-to").html('');
      $("#new-syndicate-to").append('<option value="">-- select an endpoint --</option>');
      for(var i in data.targets) {
        $("#syndication-endpoints .list").append('<li>'+data.targets[i].name+'</li>');
        $("#new-syndicate-to").append('<option value="'+data.targets[i].uid+'">'+data.targets[i].name+'</option>');
      }

      $("#syndication-endpoints .alert-warning").addClass("hidden");
      $("#automatic-syndication").removeClass("hidden");
    } else {
      if(data.error) {
        $("#syndication-endpoints .details").text(data.error);
        $("#syndication-endpoints .error").removeClass("hidden");
      }
      $("#syndication-endpoints .alert-warning").removeClass("hidden");
      $("#automatic-syndication").addClass("hidden");
    }
  }

  function reload_syndication_endpoints() {
    $("#syndication-endpoints .list").html('');
    $.post("/settings/syndication-targets.json", function(data){
      handle_discovered_syndication_targets(data);
    });
  }
  </script>

<?php elseif($this->user->micropub_success): ?>
  <? /* they've already verified their micropub endpoint, but have been disconnected from instagram */ ?>

  <div class="bs-callout bs-callout-warning">
    <p>There is no Instagram account associated with your account.</p>
  </div>

  <a href="/instagram" class="btn btn-success">Connect Instagram</a>

<?php else: ?>
  <div class="bs-callout bs-callout-warning">
    <p>Alright, that's progress! We're almost ready to start sending your Instagram photos to your website.</p>
  </div>

  <h2>Test Post</h2>

  <p>Before we enable automatic posting, you need to first test that your website accepts the requests that OwnYourGram sends. Some of your latest Instagram photos are below. Click the "post" button on any of them, and we'll send that photo to your website. If your website accepts the photo, then automatic posting will be enabled!</p>

  <p><a href="/docs">Read the documentation</a> on what fields OwnYourGram sends to your website.</p>

  <div id="instagram_photos"></div>
  <div id="instagram_photos_error" class="hidden">
    <div class="alert alert-warning">
      We didn't find any photos in your Instagram profile. If your account is set to "private", OwnYourGram will not be able to import your photos. If you're sure you've posted photos and your account is public and are still seeing this message, you can <a href="https://github.com/aaronpk/OwnYourGram/issues">report an issue</a>.
    </div>
  </div>
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

  <style>
    #instagram_photos .photo-row {
      margin-bottom: 8px;
      z-index: 0;
    }

    #instagram_photos .p {
      position: relative;
      width: 212px;
      margin: 0 auto;
    }

    #instagram_photos img {
      box-shadow: 2px 2px 5px rgba(0,0,0,0.3);
      width: 200px;
      border-radius: 3px;
    }
    #instagram_photos .multi img {
      box-shadow: none;
    }

    #instagram_photos .btn {
      margin-top: 10px;
    }
  </style>

  <script>
  $(function(){
    $("#loading").removeClass("hidden");
    $.get("/instagram/photos.json", function(data){
      $("#loading").addClass("hidden");

      if(data.items.length == 0) {
        $("#instagram_photos_error").removeClass("hidden");
        return;
      }

      var template = $("#photo-template").html();
      for(var i in data.items) {
        var item = $(template).clone();
        if(data.items[i].instagram_img_list) {
          $(item).find(".photo-img").addClass("multi").append('<img src="'+data.items[i].instagram_img_list[0]+'">');
        } else {
          $(item).find(".photo-img").html('<img src="'+data.items[i].instagram_img+'">');
        }
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
  });
  </script>
  <script id="photo-template" type="text/x-ownyourgram-template">
    <div class="col-xs-6 col-md-3 photo-row">
      <div class="p">
        <div class="photo-img">
        </div>
        <div class="caption" style="text-align: center;">
          <a href="" class="btn btn-success">Post</a>
        </div>
      </div>
    </div>
  </script>
<?php endif; ?>

