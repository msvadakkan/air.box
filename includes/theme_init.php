<script>
// Applies a saved theme choice before first paint, so there's no flash of
// the wrong theme. No stored choice -> CSS falls back to prefers-color-scheme.
// Also flags JS-availability so CSS-only entrance effects (image fade-in)
// stay opt-in — without this, a no-JS visitor would see permanently
// invisible images since nothing would ever add the .loaded class.
(function () {
  document.documentElement.classList.add('js');
  var saved = localStorage.getItem('theme');
  if (saved === 'light' || saved === 'dark') {
    document.documentElement.setAttribute('data-theme', saved);
  }
})();
</script>
