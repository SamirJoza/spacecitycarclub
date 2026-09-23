<?php
/**
 * app/Support/Members/VeteranStatusToggle.php
 * File: app/Support/Members/VeteranStatusToggle.php
 *
 * What this file does
 * -----------------------------------------------------------------------------
 * Handles the Veteran / First Responder toggle on the MEMBER dashboard
 * (Woo → My Account → Dashboard).
 *
 * Why this exists
 * -----------------------------------------------------------------------------
 * You wanted the dashboard "top section" to be display-only EXCEPT:
 * - Veteran status (toggle now)
 * - Pause membership (later)
 *
 * This keeps the dashboard Blade simple and keeps write logic in PHP.
 */

namespace App\Support\Members;

class VeteranStatusToggle
{
    public static function boot(): void
    {
        // Run early on front-end requests so we can process the POST and redirect.
        add_action('template_redirect', [static::class, 'maybeHandle']);
    }

    /**
     * If we detect the dashboard toggle POST, save + redirect.
     */
    public static function maybeHandle(): void
    {
        if (! is_user_logged_in()) {
            return;
        }

        // Only handle our specific POST.
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            return;
        }

        $action = isset($_POST['sccc_action']) ? sanitize_text_field(wp_unslash($_POST['sccc_action'])) : '';
        if ($action !== 'toggle_veteran') {
            return;
        }

        $user_id = (int) get_current_user_id();

        // Members only.
        if (! MemberContext::isTrueMember($user_id)) {
            wp_safe_redirect(home_url('/'));
            exit;
        }

        $nonce = isset($_POST['_wpnonce']) ? (string) $_POST['_wpnonce'] : '';
        if (! wp_verify_nonce($nonce, 'sccc_toggle_veteran')) {
            wp_die('Invalid request (nonce).');
        }

        $new_value = ! empty($_POST['sccc_is_veteran']) ? 1 : 0;
        $acf_user_key = 'user_' . $user_id;

        if (function_exists('update_field')) {
            update_field('is_veteran_first_responder', $new_value, $acf_user_key);
        } else {
            update_user_meta($user_id, 'is_veteran_first_responder', $new_value);
        }

        // Back to My Account (dashboard).
        if (function_exists('wc_get_page_permalink')) {
            wp_safe_redirect(wc_get_page_permalink('myaccount'));
        } else {
            wp_safe_redirect(home_url('/my-account/'));
        }
        exit;
    }
}

add_action('after_setup_theme', [VeteranStatusToggle::class, 'boot']);
