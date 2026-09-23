<?php
/**
 * File: header.php
 * Path: /wp-content/themes/<your-theme>/header.php
 *
 * What this file does
 * -----------------------------------------------------------------------------
 * Compatibility shim for third-party templates (like event single templates)
 * that call get_header()/get_footer() and expect classic header.php/footer.php.
 *
 * Your Sage site normally renders through Blade (index.php + app.blade.php),
 * so these files are typically not needed until a plugin template calls
 * get_header() directly.
 *
 * This header.php mirrors the critical structure of:
 * resources/views/layouts/app.blade.php
 * - Loads Vite assets (app.css + app.js)
 * - Runs the same "No-FOUC theme boot" script
 * - Renders Blade header sections (eyebrow + header)
 * - Opens <main> so plugin content lands in the right place
 */

use Illuminate\Support\Facades\Vite;

?><!doctype html>
<html <?php language_attributes(); ?>>
  <head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <?php wp_head(); ?>

    <!-- No-FOUC theme boot (same as app.blade.php) -->
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

    <?php
      /**
       * Load the same entrypoints as @vite(['resources/css/app.css','resources/js/app.js'])
       * so plugin templates get your theme styling and JS.
       */
      if (class_exists(Vite::class)) {
        echo Vite::withEntryPoints([
          'resources/css/app.css',
          'resources/js/app.js',
        ])->toHtml();
      }
    ?>
  </head>

  <body <?php body_class('bg-canvas text-text antialiased selection:bg-primary-500/20 min-h-dvh flex flex-col'); ?>>
    <?php wp_body_open(); ?>

    <div id="app" data-push-target class="min-h-dvh flex flex-col transition-transform duration-300 will-change-transform">
      <a class="sr-only focus:not-sr-only focus:absolute focus:top-3 focus:left-3 focus:z-50 focus:rounded focus:bg-surface focus:px-3 focus:py-2" href="#main">
        <?php echo esc_html__('Skip to content', 'sage'); ?>
      </a>

      <?php
        /**
         * Render the same Blade header parts your layout uses.
         * Guarded so a failure here doesn't white-screen plugin templates.
         */
        if (function_exists('view')) {
          try {
            echo view('partials.eyebrow')->render();
          } catch (\Throwable $e) {
            // fail silently (dev will still show errors elsewhere if needed)
          }

          try {
            echo view('sections.header')->render();
          } catch (\Throwable $e) {
            // fail silently
          }
        }
      ?>

      <main id="main" tabindex="-1">
