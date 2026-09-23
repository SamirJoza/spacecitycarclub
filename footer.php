<?php
/**
 * File: footer.php
 * Path: /wp-content/themes/<your-theme>/footer.php
 *
 * What this file does
 * -----------------------------------------------------------------------------
 * Complements header.php compatibility shim:
 * - Closes <main>
 * - Renders Blade footer section
 * - Outputs wp_footer()
 */

?>
      </main>

      <?php
        if (function_exists('view')) {
          try {
            echo view('sections.footer')->render();
          } catch (\Throwable $e) {
            // fail silently
          }
        }
      ?>
    </div>

    <?php wp_footer(); ?>
  </body>
</html>
