<!doctype html>
<html <?php language_attributes(); ?>>
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <?php do_action('get_header'); ?>
    <?php wp_head(); ?>

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

  <body <?php body_class('bg-canvas text-text antialiased selection:bg-primary-500/20 min-h-dvh flex flex-col sccc-kiosk-layout'); ?>>
    <?php wp_body_open(); ?>

    <div id="app" class="min-h-dvh flex flex-col">
      <a class="sr-only focus:not-sr-only focus:absolute focus:top-3 focus:left-3 focus:z-50 focus:rounded focus:bg-surface focus:px-3 focus:py-2" href="#main">
        <?php echo esc_html__('Skip to content', 'sage'); ?>
      </a>

      <header class="border-b rule bg-surface/95 backdrop-blur" role="banner">
        <div class="mx-auto flex h-14 max-w-7xl items-center justify-center px-4 sm:px-6 lg:h-16 lg:px-8">
          <span class="font-display text-[11px] font-black uppercase tracking-[0.28em] text-text/95 md:text-xs">
            <?php echo esc_html(get_bloginfo('name') ?: 'Space City Car Club'); ?>
          </span>
        </div>
      </header>

      <main id="main" tabindex="-1" class="flex-1">
        @yield('content')
      </main>
    </div>

    <?php do_action('get_footer'); ?>
    <?php wp_footer(); ?>
  </body>
</html>