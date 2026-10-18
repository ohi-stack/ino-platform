/* Administrative clipboard helper. No shortcode is executed or rendered here. */
(function () {
  'use strict';
  document.addEventListener('click', function (event) {
    var btn = event.target.closest && event.target.closest('.ino-sc-copy');
    if (!btn) { return; }
    var value = btn.getAttribute('data-shortcode') || '';
    var feedback = document.getElementById('ino-sc-copy-feedback');
    if (!value || !navigator.clipboard || !navigator.clipboard.writeText) {
      if (feedback) { feedback.textContent = 'Clipboard access unavailable. Select and copy the shortcode text instead.'; }
      return;
    }
    navigator.clipboard.writeText(value).then(function () {
      if (feedback) { feedback.textContent = value + ' copied to clipboard.'; }
      btn.textContent = 'Copied';
      window.setTimeout(function () { btn.textContent = 'Copy'; }, 1800);
    }, function () {
      if (feedback) { feedback.textContent = 'Could not copy. Select and copy the shortcode manually.'; }
    });
  });
}());
