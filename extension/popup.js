var bg = chrome.extension.getBackgroundPage();

document.addEventListener('DOMContentLoaded', function () {

  chrome.storage.sync.get("micropub_endpoint", function(result){
    document.querySelector("#micropub_endpoint").innerText = result.micropub_endpoint || "";
  });

  chrome.storage.sync.get("micropub_access_token", function(result){
    document.querySelector("#micropub_access_token").value = result.micropub_access_token || "";
  });

  if(bg.currentPhoto.properties.video) {
    for(let video of bg.currentPhoto.properties.video) {
      var el = document.createElement("video");
      el.controls = true;
      el.src = video;
      el.width = 200;
      document.getElementById("photos").appendChild(el);
    }
    document.getElementById("photos").classList.add("has-video");
    document.getElementById("photos").classList.add("video-"+bg.currentPhoto.properties.video.length);
  } else {
    for(let photo of bg.currentPhoto.properties.photo) {
      var el = document.createElement("img");
      el.src = photo;
      el.width = 200;
      document.getElementById("photos").appendChild(el);
    }
    document.getElementById("photos").classList.add("photos-"+bg.currentPhoto.properties.photo.length);
  }

  document.getElementById("caption").innerText = bg.currentPhoto.properties.content;

  document.getElementById("debug").innerText = JSON.stringify(bg.currentPhoto, null, 2);


  document.getElementById("post").addEventListener("click", function(){

    document.getElementById("post").disabled="disabled";

    var micropub_endpoint = document.querySelector("#micropub_endpoint").innerText;
    var access_token = document.querySelector("#micropub_access_token").value;

    console.log("Making request to micropub endpoint "+micropub_endpoint);

    const req = new XMLHttpRequest();
    req.open("POST", micropub_endpoint, true);
    req.setRequestHeader("Content-type", "application/json");
    req.setRequestHeader("Authorization", "Bearer "+access_token);
    req.send(JSON.stringify(bg.currentPhoto));

    req.onreadystatechange = function() {
      if(this.readyState === XMLHttpRequest.DONE) {
        console.log("Got response! "+this.status);
        var location = this.getResponseHeader("Location");
        if(location) {
          window.location = location;
        } else {
          document.getElementById("response").innerText = this.responseText;
        }
      }
    }
  });

});

