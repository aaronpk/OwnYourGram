
console.log("OwnYourGram Background Script Loaded");

/*
  This listener is called when the extension icon is clicked. This injects javascript into the active
  tab so that the contents of the DOM can be extracted
*/
chrome.pageAction.onClicked.addListener(function(tab) {
  chrome.tabs.executeScript(null, {file: "run.js"});
});


var currentPhoto;

/*
  Tell the browser to activate the extension page action icon
*/
chrome.runtime.onMessage.addListener(function(message, sender, sendResponse) {

    if(typeof message === 'object' && message.type === 'show_page_action') {
        chrome.pageAction.show(sender.tab.id);
    }

    if(typeof message === 'object' && message.type === 'own_this_photo') {
      console.log("Path: "+message.path);
      console.log(message.data);

      var media = message.data.graphql.shortcode_media;

      var entry = {};

      entry.content = [media.edge_media_to_caption.edges[0].node.text];

      if(entry.content[0]) {
        let tags = entry.content[0].matchAll(/#([^ ]+)/g);
        entry.category = [];
        for(let tag of tags) {
          entry.category.push(tag[1]);
        }
      }

      entry.photo = [];
      entry.video = [];

      if(media.edge_sidecar_to_children) {
        // Multi-photo or photo/video
        for(var i in media.edge_sidecar_to_children.edges) {
          if(media.edge_sidecar_to_children.edges[i].node.video_url) {
            entry.video.push(media.edge_sidecar_to_children.edges[i].node.video_url);
          } else {
            entry.photo.push(media.edge_sidecar_to_children.edges[i].node.display_url);
          }
        }
      } else {
        // Single photo or video. Use photo as thumbnail when sending video

        entry.photo.push(media.display_url);

        if(media.video_url) {
          entry.video = [media.video_url];
        }
      }

      if(entry.video.length == 0) {
        delete entry.video;
      }

      entry.syndication = ["https://www.instagram.com/p/"+media.shortcode];

      entry.published = (new Date(media.taken_at_timestamp*1000)).toISOString();

      currentPhoto = {
        type: "h-entry",
        properties: entry
      };

      chrome.tabs.create({url: "popup.html"});
    }
});
