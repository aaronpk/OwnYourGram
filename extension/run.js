console.log("Owning this photo");

/*
This script is injected into the active tab when the button is pressed. This has access to the DOM,
and finds the script tag containing the photo data and sends a message to the background script.
*/

var found = false;
for(var i in document.scripts) {
  var js = document.scripts[i].innerText;
  if(js && js.match(/^window._sharedData/)) {
    // Send the photo details to the background script
    eval(js);
	chrome.runtime.sendMessage({ type: "own_this_photo", path: window.location, data: window._sharedData.entry_data.PostPage[0] });
    found = true;
  }
}

if(!found) {
	console.log("Error finding photo data in page");
}
