<?php

declare(strict_types=1);

/**
 * Space City Car Club — replace core WordPress branding in wp-admin and wp-login.
 */

namespace App\Support\Admin;

if (! defined('ABSPATH')) {
    exit;
}

final class WordPressBranding
{
    /**
     * Logo under wp-content (resolved with content_url()).
     */
    private const LOGO_RELATIVE = 'uploads/logo.png';

    public static function register(): void
    {
        add_filter('admin_footer_text', [self::class, 'filterAdminFooterText'], 99);

        add_action('admin_head', [self::class, 'printAdminLogoStyles'], 99);
        add_action('admin_bar_menu', [self::class, 'pointWpLogoToHome'], 11);

        add_action('login_enqueue_scripts', [self::class, 'enqueueLoginLogoStyles'], 99);
        add_filter('login_headerurl', [self::class, 'filterLoginHeaderUrl']);
        add_filter('login_headertext', [self::class, 'filterLoginHeaderText']);

        add_filter('gettext', [self::class, 'filterGettext'], 20, 3);
        add_filter('ngettext', [self::class, 'filterNgettext'], 20, 5);
    }

    public static function logoUrl(): string
    {
        return content_url(self::LOGO_RELATIVE);
    }

    /**
     * Left admin footer: custom credit. Version string stays on the right via core {@see 'update_footer'}.
     */
    public static function filterAdminFooterText(string $text): string
    {
        $credit = sprintf(
            '<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
            esc_url('https://samirjoza.dev'),
            esc_html__('Samir Joza', 'sccc')
        );

        $inner = esc_html__('Space City Car Club', 'sccc')
            . ' '
            . esc_html__('is powered by Wordpress and modified by', 'sccc')
            . ' '
            . $credit
            . '.';

        return '<span id="footer-thankyou">' . wp_kses(
            $inner,
            [
                'a' => [
                    'href' => [],
                    'target' => [],
                    'rel' => [],
                ],
            ]
        ) . '</span>';
    }

    public static function printAdminLogoStyles(): void
    {
        $url = esc_url(self::logoUrl());
        echo '<style id="sccc-wp-branding-admin-logos">' . "\n";
        echo <<<CSS
#wpadminbar #wp-admin-bar-wp-logo > .ab-item .ab-icon:before {
  content: "" !important;
}
#wpadminbar #wp-admin-bar-wp-logo > .ab-item .ab-icon {
  background: transparent url("{$url}") center center / contain no-repeat !important;
  width: 20px;
  height: 20px;
}
.about-wrap .wp-badge,
.svg .about-wrap .wp-badge {
  background-image: url("{$url}") !important;
  background-size: contain;
  background-position: center center;
}
CSS;
        echo "\n</style>\n";
    }

    public static function pointWpLogoToHome(\WP_Admin_Bar $wp_admin_bar): void
    {
        $node = $wp_admin_bar->get_node('wp-logo');
        if (! $node instanceof \stdClass) {
            return;
        }

        $args = get_object_vars($node);
        $args['href'] = home_url('/');
        $wp_admin_bar->add_node($args);
    }

    public static function enqueueLoginLogoStyles(): void
    {
        $url = esc_url(self::logoUrl());
        $css = <<<CSS
.login h1 a {
  background-image: url("{$url}") !important;
  background-size: contain !important;
  width: 100% !important;
  max-width: 320px !important;
}
CSS;
        wp_add_inline_style('login', $css);
    }

    public static function filterLoginHeaderUrl(string $login_header_url): string
    {
        return home_url('/');
    }

    public static function filterLoginHeaderText(string $login_header_text): string
    {
        return __('Space City Car Club', 'sccc');
    }

    /**
     * @param mixed $translation
     */
    public static function filterGettext($translation, string $text, string $domain): string
    {
        if (! is_string($translation)) {
            return (string) $translation;
        }

        if ($domain !== 'default' || ! self::shouldFilterStrings()) {
            return $translation;
        }

        return self::replaceWordPressBrand($translation, $text);
    }

    /**
     * @param mixed $translation
     */
    public static function filterNgettext($translation, string $single, string $plural, int $number, string $domain): string
    {
        if (! is_string($translation)) {
            return (string) $translation;
        }

        if ($domain !== 'default' || ! self::shouldFilterStrings()) {
            return $translation;
        }

        return self::replaceWordPressBrand($translation, $single);
    }

    private static function shouldFilterStrings(): bool
    {
        if (defined('WP_CLI') && constant('WP_CLI')) {
            return false;
        }

        if (is_admin()) {
            return true;
        }

        if (isset($GLOBALS['pagenow']) && $GLOBALS['pagenow'] === 'wp-login.php') {
            return true;
        }

        return false;
    }

    private static function replaceWordPressBrand(string $translation, string $sourceEnglish): string
    {
        if ($sourceEnglish === '' || ! str_contains($sourceEnglish, 'WordPress')) {
            return $translation;
        }

        $lower = strtolower($sourceEnglish);
        if (
            str_contains($lower, 'wordpress.org')
            || str_contains($sourceEnglish, 'WordPress database')
            || str_contains($sourceEnglish, 'WordPress filesystem')
        ) {
            return $translation;
        }

        return str_replace('WordPress', __('Space City Car Club', 'sccc'), $translation);
    }
}

WordPressBranding::register();
