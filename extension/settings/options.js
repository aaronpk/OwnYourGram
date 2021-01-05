function saveOptions(e) {
  e.preventDefault();
  chrome.storage.sync.set({
    micropub_endpoint: document.querySelector("#micropub_endpoint").value,
    micropub_access_token: document.querySelector("#micropub_access_token").value
  });
}

function restoreOptions() {

  chrome.storage.sync.get("micropub_endpoint", function(result){
    document.querySelector("#micropub_endpoint").value = result.micropub_endpoint || "";
  });

  chrome.storage.sync.get("micropub_access_token", function(result){
    document.querySelector("#micropub_access_token").value = result.micropub_access_token || "";
  });

}

document.addEventListener("DOMContentLoaded", restoreOptions);
document.querySelector("form").addEventListener("submit", saveOptions);
