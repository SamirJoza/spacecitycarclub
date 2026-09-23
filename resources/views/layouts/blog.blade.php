{{--
|--------------------------------------------------------------------------
| File path + filename: resources/views/layouts/blog.blade.php
|--------------------------------------------------------------------------
| Purpose:
| - Provide a blog-specific layout that preserves the normal site header and
|   footer, while giving the blog index its own content/sidebar structure.
| - Keep the default `layouts.app` untouched so other templates continue to
|   use the normal global layout.
|
| Why this file exists:
| - The blog overview needs a custom shell: a full-width hero slot above the
|   grid, a content column, and a sidebar column beside it.
| - This avoids the "sidebar dropped below" problem and the "hero crushed into
|   8 columns" problem that both occur when trying to solve layout with only
|   utility classes in the post template.
|
| Hero slot (@yield('hero')):
| - Renders BEFORE the content/sidebar grid, inside its own container that
|   matches the blog layout max-width.
| - If a template does not define @section('hero'), this slot is skipped
|   cleanly. No empty wrapper is rendered.
|
| Sidebar slot (@yield('sidebar')):
| - When present, the layout switches to a two-column CSS grid.
| - When absent, the content renders in a single full-width container.
|
| Newsletter section:
| - Included between </main> and the footer via sections.newsletter.
| - Visibility and all copy are controlled by the ACF Options page registered
|   in app/Options/NewsletterSection.php.
| - The partial returns early (renders nothing) when toggled off in Options.
|
| Notes:
| - `home.blade.php` extends this layout and provides the hero + sidebar slots.
| - The post list stays inside the normal container (padding, max-width).
| - Sidebar is intentionally outside the inner container and positioned
|   by the scoped blog layout CSS in `resources/styles/components/blog-list.css`.
|--------------------------------------------------------------------------
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
          Hero slot.
          Renders full-width (up to the blog layout max-width) ABOVE the
          content/sidebar grid. Wrapped in its own container so horizontal
          padding and max-width align with the grid below.
          Only rendered when home.blade.php (or another template) defines
          @section('hero').
        --}}
        @hasSection('hero')
          <div class="sccc-blog-above-fold">
            @yield('hero')
          </div>
        @endif

        {{--
          Content / sidebar grid.
          Two branches:
          1. Sidebar present → CSS grid layout (content column + sidebar column).
          2. No sidebar     → simple full-width container.
        --}}
        @hasSection('sidebar')
          <div class="sccc-blog-layout">

            <div class="sccc-blog-layout__content">
              <div class="container mx-auto px-4 py-8 md:px-6 md:py-10 xl:px-0">
                @yield('content')
              </div>
            </div>

            <aside class="sccc-blog-layout__sidebar" aria-label="{{ esc_attr__('Blog sidebar', 'sage') }}">
              <div class="sccc-blog-layout__sidebar-inner">
                @yield('sidebar')
              </div>
            </aside>

          </div>
        @else
          <div class="container mx-auto px-4 py-8 md:px-6 md:py-10 xl:px-0">
            @yield('content')
          </div>
        @endif

      </main>

      {{--
        Newsletter section.
        Sits between </main> and the footer — outside the post content area —
        so it spans the full layout width on every blog page.
        All copy and visibility are controlled via the ACF Options page
        (app/Options/NewsletterSection.php). The partial renders nothing when
        the section is toggled off.
      --}}
      @include('sections.newsletter')

      @include('sections.footer')
    </div>

    @php(do_action('get_footer'))
    @php(wp_footer())
  </body>
</html>