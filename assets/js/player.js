(function () {
  'use strict';
  // Basic, honest deterrents against casual download/saving of streamed media.
  // Not real DRM — a determined user can always capture a stream — but this
  // keeps the player consistent with the "stream only, no download" UX.

  document.querySelectorAll('video, audio, iframe.pdf-frame, img.protected').forEach(function (el) {
    el.addEventListener('contextmenu', function (e) { e.preventDefault(); });
  });

  document.addEventListener('keydown', function (e) {
    var key = e.key.toLowerCase();
    if ((e.ctrlKey || e.metaKey) && key === 's') e.preventDefault();
  });
})();
