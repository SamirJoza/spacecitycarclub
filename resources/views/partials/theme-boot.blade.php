{{--
  resources/views/partials/theme-boot.blade.php

  No-FOUC theme boot. Runs before first paint so the page never flashes the
  wrong theme. Rule (must match resources/js/components/theme.js):
    1. the visitor's saved choice ("scc-theme" in localStorage), otherwise
    2. dark mode.

  header.php carries a plain-PHP copy of this script for plugin templates
  that call get_header(). Keep the two in sync.
--}}
<script>
  (function () {
    var mode='dark';
    try {
      var saved=localStorage.getItem('scc-theme');
      if (saved==='light' || saved==='dark') mode=saved;
    } catch(e){}
    if (mode==='dark') document.documentElement.classList.add('dark');
    document.documentElement.setAttribute('data-theme', mode);
  })();
</script>
