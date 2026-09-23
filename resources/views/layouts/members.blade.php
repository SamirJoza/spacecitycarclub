{{-- resources/views/layouts/members.blade.php --}}
{{--
  File: resources/views/layouts/members.blade.php

  Purpose
  ------------------------------------------------------------------------------
  A dedicated layout for the Members / My Account area.

  Why this exists
  ------------------------------------------------------------------------------
  We want certain DOM elements (like a crop modal) to live OUTSIDE of the main
  content flow (outside #app, outside any account shell containers), so they can
  always:
  - center correctly (position: fixed / viewport)
  - avoid weird stacking contexts from transforms/filters inside the page
  - avoid overlap / clipping due to parent overflow rules

  Implementation
  ------------------------------------------------------------------------------
  This file is intentionally a near-copy of layouts/app.blade.php, with ONE
  addition:
    @stack('members_footer')
  placed at the end of <body>, after #app but before get_footer/wp_footer hooks.

  Usage
  ------------------------------------------------------------------------------
  Create a page template that extends this layout and assign it to the
  WooCommerce "My Account" page in WP Admin.
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

  {{-- pass your token utilities into body_class() --}}
  <body @php(body_class('bg-canvas text-text antialiased selection:bg-primary-500/20 min-h-dvh flex flex-col'))>
    @php(wp_body_open())

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

      @include('sections.footer')
    </div>

    {{--
      Members Footer DOM (Blade-only, no hooks)
      ----------------------------------------------------------------------------
      This is where we will render the avatar crop modal markup so it lives at the
      end of <body>, outside #app and outside any transformed containers.
      Example usage from a view:
        @push('members_footer')
          <div class="sccc-avatar-modal">...</div>
        @endpush
    --}}
    @stack('members_footer')

    @php(do_action('get_footer'))
    @php(wp_footer())
  </body>
</html>
