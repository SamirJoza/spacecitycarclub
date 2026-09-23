{{--
|--------------------------------------------------------------------------
| File path: resources/views/layouts/single.blade.php
|--------------------------------------------------------------------------
| Purpose:
| - Outer shell for single blog posts: site chrome + max-width container.
|
| Why no grid here:
| - The two-column grid (article body + sidebar) belongs INSIDE the content
|   partial so it can be positioned correctly: only the article body sits
|   beside the sidebar, while the header, author bio, and Read Next section
|   span the full container width above and below it.
| - If the grid lived here (wrapping all of @yield('content')), the entire
|   partial — including the post title and hero image — would be crushed
|   into the 2/3 column.
|
| Sidebar strategy:
| - single.blade.php captures dynamic_sidebar('blog-sidebar') via
|   ob_start/ob_get_clean at template level, then passes $sidebarHtml
|   to the partial via @include. The partial uses it to conditionally
|   render the two-column grid.
| - This keeps dynamic_sidebar() out of the Composer (where it is harder
|   to reason about) while keeping it out of the layout (where it would
|   affect the column structure incorrectly).
|
| Scripts slot:
| - @stack('scripts') placed just before </body> so @push('scripts')
|   calls in content-single.blade.php (GSAP) are flushed correctly.
|--------------------------------------------------------------------------
--}}

<!doctype html>
<html @php(language_attributes())>
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    @php(do_action('get_header'))
    @php(wp_head())

    {{-- No-FOUC theme boot — keep in sync with layouts/app.blade.php --}}
    <script>
      (function () {
        try {
          var KEY = 'scc-theme';
          var saved = localStorage.getItem(KEY);
          var prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
          var mode = saved ? saved : (prefersDark ? 'dark' : 'light');
          if (mode === 'dark') document.documentElement.classList.add('dark');
          document.documentElement.setAttribute('data-theme', mode);
        } catch (e) {}
      })();
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
  </head>

  <body @php(body_class('bg-canvas text-text antialiased selection:bg-primary-500/20 min-h-dvh flex flex-col'))>
    @php(wp_body_open())

    <div id="app" data-push-target class="min-h-dvh flex flex-col transition-transform duration-300 will-change-transform">

      <a class="sr-only focus:not-sr-only focus:absolute focus:top-3 focus:left-3 focus:z-50 focus:rounded focus:bg-surface focus:px-3 focus:py-2" href="#main">
        {{ __('Skip to content', 'sage') }}
      </a>

      @include('partials.eyebrow')
      @include('sections.header')

      <main id="main" tabindex="-1" class="flex-1">

        {{--
          Simple max-width container — no grid.
          The grid (article body + sidebar) is handled inside
          content-single.blade.php so it only wraps the body content,
          leaving the header, author bio, and Read Next full-width.
        --}}
        <div class="max-w-7xl mx-auto px-4 md:px-10 py-8 md:py-12">
          @yield('content')
        </div>

      </main>

      @include('sections.newsletter')
      @include('sections.footer')

    </div>

    @php(do_action('get_footer'))
    @php(wp_footer())

    @stack('scripts')
  </body>
</html>