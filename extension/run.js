console.log("Owning this photo");

/*
This script is injected into the active tab when the button is pressed. This has access to the DOM,
and finds the script tag containing the photo data and sends a message to the background script.
*/

window.__additionalDataLoaded = function(path, data) {
  console.log(path, data);
  // Send the photo details to the background script
  chrome.runtime.sendMessage({ type: "own_this_photo", path: path, data: data });
}

for(var i in document.scripts) {
  var js = document.scripts[i].innerText;
  if(js && js.match(/^window.__additionalDataLoaded/)) {
    eval(js);
  }
}


