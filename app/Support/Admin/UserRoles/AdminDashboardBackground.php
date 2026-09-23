<?php

declare(strict_types=1);

/**
 * Space City Car Club Admin Dashboard Background
 *
 * Full file path:
 * app/Support/Admin/UserRoles/AdminDashboardBackground.php
 *
 * Purpose:
 * Adds a branded background image to the WordPress Dashboard screen only.
 *
 * Sage asset handling:
 * This project uses Roots Sage, so admin PHP files resolve theme images through
 * Laravel's Vite facade instead of get_theme_file_uri().
 *
 * Source image location:
 * resources/images/admin/admin-dashboard-bg.webp
 *
 * Sage/Vite resolves that source path into the correct built asset URL.
 *
 * Scope:
 * This only affects:
 * /wp-admin/index.php
 *
 * It does not affect:
 * - Post editor screens
 * - Page editor screens
 * - WooCommerce screens
 * - PMPro screens
 * - User profile screens
 * - Settings screens
 *
 * Self-booting:
 * This file registers its own hooks at the bottom of the file.
 * Do not call AdminDashboardBackground::boot() from setup.php or bootstrap.php.
 */

namespace App\Support\Admin\UserRoles;

use Illuminate\Support\Facades\Vite;

if (! defined('ABSPATH')) {
    exit;
}

final class AdminDashboardBackground
{
    /**
     * Inline style handle used for the dashboard-only background CSS.
     */
    private const STYLE_HANDLE = 'sccc-admin-dashboard-background';

    /**
     * Sage/Vite source path for the dashboard background image.
     *
     * Keep this as a resources path. Do not change this to a public/build URL.
     * Sage/Vite will resolve the correct final URL with Vite::asset().
     */
    private const DEFAULT_IMAGE_PATH = 'resources/images/admin/admin-dashboard-bg.webp';

    /**
     * Register WordPress admin hooks.
     */
    public static function boot(): void
    {
        add_action('admin_enqueue_scripts', [self::class, 'enqueue'], 20);
    }

    /**
     * Enqueue dashboard background styles.
     *
     * WordPress passes the current admin page hook suffix here. For the main
     * Dashboard screen, the suffix is index.php.
     *
     * @param string $hookSuffix Current admin page hook suffix.
     */
    public static function enqueue(string $hookSuffix): void
    {
        if ($hookSuffix !== 'index.php') {
            return;
        }

        if (! self::shouldShowBackground()) {
            return;
        }

        $imagePath = apply_filters(
            'sccc_admin_dashboard_background_image_path',
            self::DEFAULT_IMAGE_PATH
        );

        if (! is_string($imagePath) || trim($imagePath) === '') {
            return;
        }

        $imagePath = ltrim(trim($imagePath), '/');

        /**
         * Sage/Vite asset URL.
         *
         * resources/images and resources/fonts are copied into public/build by
         * the Sage Vite configuration.
         */
        $imageUrl = Vite::asset($imagePath);

        $blur = apply_filters('sccc_admin_dashboard_background_blur', '1px');
        $blur = is_string($blur) && trim($blur) !== '' ? trim($blur) : '1px';

        wp_register_style(self::STYLE_HANDLE, false, [], null);
        wp_enqueue_style(self::STYLE_HANDLE);

        wp_add_inline_style(
            self::STYLE_HANDLE,
            self::css((string) $imageUrl, $blur)
        );
    }

    /**
     * Decide who should see the branded dashboard background.
     *
     * Administrators always see it.
     * Operational roles see it when they have the dashboard widget capability.
     */
    private static function shouldShowBackground(): bool
    {
        if (current_user_can('manage_options')) {
            return true;
        }

        return current_user_can('sccc_use_dashboard_widgets');
    }

    /**
     * Generate scoped dashboard CSS.
     *
     * @param string $imageUrl Background image URL resolved by Sage/Vite.
     * @param string $blur     Background blur amount.
     */
    private static function css(string $imageUrl, string $blur): string
    {
        $safeImageUrl = esc_url($imageUrl);
        $safeBlur = esc_attr($blur);

        return <<<CSS
body.index-php {
  background-color: #0b1120;
}

body.index-php #wpwrap {
  position: relative;
  z-index: 1;
  isolation: isolate;
  min-height: 100vh;
}

body.index-php #wpwrap::before {
  content: "";
  position: fixed;
  inset: 32px 0 0 160px;
  z-index: 0;
  pointer-events: none;
  background:
    linear-gradient(135deg, rgba(3, 7, 18, 0.84) 0%, rgba(15, 23, 42, 0.70) 46%, rgba(3, 7, 18, 0.90) 100%),
    url("{$safeImageUrl}");
  background-size: cover;
  background-position: center;
  background-repeat: no-repeat;
  filter: blur({$safeBlur});
  transform: scale(1.015);
}

/**
 * Do not set z-index on #wpcontent / #wpfooter: #wpadminbar is rendered inside
 * #wpcontent (in_admin_header). A stacking context here traps the toolbar below
 * #adminmenumain. Content still paints above #wpwrap::before by tree order.
 */
body.index-php #wpcontent,
body.index-php #wpfooter {
  position: relative;
}

/**
 * Keep the WordPress admin bar (top) and its flyouts above the left menu and widgets.
 */
body.index-php #wpadminbar {
  z-index: 100050;
}

body.index-php #wpadminbar .menupop .ab-sub-wrapper,
body.index-php #wpadminbar .shortlink-input {
  z-index: 100051;
}

/**
 * Keep the WordPress admin menu (left) and flyouts above #wpcontent / widgets.
 */
body.index-php #adminmenumain,
body.index-php #adminmenuback,
body.index-php #adminmenuwrap,
body.index-php #adminmenu {
  position: relative;
  z-index: 1000;
}

body.index-php #adminmenu .wp-submenu,
body.index-php #adminmenu .opensub .wp-submenu,
body.index-php #adminmenu .wp-has-current-submenu .wp-submenu,
body.index-php #adminmenu .wp-has-submenu:hover .wp-submenu {
  z-index: 1001;
}

body.index-php #wpcontent {
  min-height: calc(100vh - 32px);
}

body.index-php #wpbody-content .wrap > h1:first-child {
  color: #ffffff;
  text-shadow: 0 2px 18px rgba(0, 0, 0, 0.55);
}

body.index-php #dashboard-widgets .postbox {
  border-color: rgba(148, 163, 184, 0.34);
  background: rgba(255, 255, 255, 0.94);
  box-shadow: 0 18px 45px rgba(15, 23, 42, 0.18);
  backdrop-filter: blur(12px);
}

body.index-php #dashboard-widgets .postbox-header {
  border-bottom-color: rgba(148, 163, 184, 0.26);
}

body.index-php #dashboard-widgets .inside {
  position: relative;
}

@media screen and (max-width: 960px) {
  body.index-php #wpwrap::before {
    left: 36px;
  }
}

@media screen and (max-width: 782px) {
  body.index-php #wpwrap::before {
    inset: 46px 0 0 0;
  }
}

@media (prefers-reduced-transparency: reduce) {
  body.index-php #dashboard-widgets .postbox {
    background: #ffffff;
    backdrop-filter: none;
  }
}
CSS;
    }
}

/**
 * Self-boot this file once it is included by the theme.
 *
 * Do not call AdminDashboardBackground::boot() anywhere else.
 */
AdminDashboardBackground::boot();