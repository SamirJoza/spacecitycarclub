{{-- 
  File: resources/views/layouts/video-background.blade.php
  Purpose: Dedicated layout for pages using a selectable background video.
--}}

<!doctype html>
<html @php(language_attributes())>
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    @php(do_action('get_header'))
    @php(wp_head())

    {{-- No-FOUC theme boot --}}
    <script>
      (function () {
        try {
          var KEY='scc-theme';
          var saved=localStorage.getItem(KEY);
          var prefersDark=window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
          var mode=saved ? saved : (prefersDark ? 'dark' : 'light');
          if (mode==='dark') document.documentElement.classList.add('dark');
          document.documentElement.setAttribute('data-theme', mode);
        } catch(e){}
      })();
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
  </head>

  <body @php(body_class('sccc-has-video-background bg-canvas text-text antialiased selection:bg-primary-500/20 min-h-dvh flex flex-col'))>
    @php(wp_body_open())

    @include('partials.video-background')

    {{-- push target so header+main+footer slide right on mobile menu open --}}
    <div id="app" data-push-target class="min-h-dvh flex flex-col transition-transform duration-300 will-change-transform">
      <a class="sr-only focus:not-sr-only focus:absolute focus:top-3 focus:left-3 focus:z-50 focus:rounded focus:bg-surface focus:px-3 focus:py-2" href="#main">
        {{ __('Skip to content', 'sage') }}
      </a>

      @include('partials.eyebrow')
      @include('sections.header')

      <main id="main" tabindex="-1">
        @yield('content')
      </main>

      @include('sections.newsletter')
      @include('sections.footer')
    </div>

    @php(do_action('get_footer'))
    @php(wp_footer())
  </body>
</html>