<?php
/**
 * app/Support/Members/MemberBio.php
 * File: app/Support/Members/MemberBio.php
 *
 * Member Bio (“Biographical Info”) AJAX save handler.
 *
 * What this does
 * ------------------------------------------------------------------------------
 * - Saves WordPress core “Biographical Info” field (user meta key: `description`)
 * - Plain text only (NO HTML)
 * - Hard limit: 1500 characters (server-side enforced)
 *
 * Where the UI lives
 * ------------------------------------------------------------------------------
 * - resources/views/woocommerce/myaccount/dashboard.blade.php
 *   (inline editor placed above the “Pause Membership” section)
 *
 * Security
 * ------------------------------------------------------------------------------
 * - Logged-in users only can save
 * - Nonce required (but we return JSON errors instead of dying with -1)
 * - Users can only edit their own bio (edit_user on self)
 */

namespace App\Support\Members;

final class MemberBio
{
    /**
     * admin-ajax.php expects `action` to match these hooks:
     * - wp_ajax_{action}        (logged-in)
     * - wp_ajax_nopriv_{action} (logged-out)
     *
     * We register BOTH so the front-end never gets a confusing “0” just because
     * the user session/cookies weren’t present — instead, we return a JSON error.
     *
     * Docs: https://developer.wordpress.org/reference/hooks/wp_ajax_action/
     */
    public const AJAX_ACTION = 'sccc_member_bio_save';

    /**
     * Nonce action string used by wp_create_nonce() and check_ajax_referer().
     *
     * NOTE: We intentionally set $die=false in check_ajax_referer so we can return
     * JSON errors instead of WordPress killing the request with a raw “-1”.
     */
    public const NONCE_ACTION = 'sccc_member_bio_save';

    /**
     * Max characters allowed (plain text).
     */
    public const MAX_CHARS = 1500;

    /**
     * Boot hooks. Call once when this file is loaded.
     */
    public static function boot(): void
    {
        add_action('wp_ajax_' . self::AJAX_ACTION, [__CLASS__, 'ajaxSave']);
        add_action('wp_ajax_nopriv_' . self::AJAX_ACTION, [__CLASS__, 'ajaxNoPriv']);
    }

    /**
     * Logged-out handler (keeps responses consistent and debuggable).
     */
    public static function ajaxNoPriv(): void
    {
        wp_send_json_error(
            ['message' => 'You are not logged in. Please reload and sign in again.'],
            401
        );
    }

    /**
     * Logged-in handler: Save current user's bio.
     */
    public static function ajaxSave(): void
    {
        if (!is_user_logged_in()) {
            // If cookies didn’t come through, this path may be hit even without nopriv.
            wp_send_json_error(['message' => 'You are not logged in. Please reload and sign in again.'], 401);
        }

        // Verify nonce but DO NOT die with "-1" — return JSON instead.
        $nonceOk = check_ajax_referer(self::NONCE_ACTION, 'nonce', false);

        if (!$nonceOk) {
            wp_send_json_error(
                ['message' => 'Security check failed. Please reload the page and try again.'],
                403
            );
        }

        $userId = (int) get_current_user_id();

        // Self-edit only.
        if (!current_user_can('edit_user', $userId)) {
            wp_send_json_error(['message' => 'Not allowed.'], 403);
        }

        $raw = isset($_POST['bio']) ? wp_unslash((string) $_POST['bio']) : '';

        // Plain text only (strips tags, normalizes whitespace).
        $bio = sanitize_textarea_field($raw);

        // Normalize newlines for consistent storage/display.
        $bio = preg_replace("/\r\n?/", "\n", $bio) ?? $bio;

        // Hard-truncate (in case someone bypasses maxlength).
        $bio = self::truncate($bio, self::MAX_CHARS);

        update_user_meta($userId, 'description', $bio);

        wp_send_json_success([
            'bio' => $bio,
        ]);
    }

    /**
     * Truncate string to $max characters (UTF-8 safe when mbstring exists).
     */
    private static function truncate(string $value, int $max): string
    {
        if ($max <= 0) {
            return '';
        }

        if (function_exists('mb_substr')) {
            return (string) mb_substr($value, 0, $max);
        }

        return (string) substr($value, 0, $max);
    }
}

// Auto-boot when included.
MemberBio::boot();