<?php
/**
 * app/Support/Users/LocalAvatar.php
 * File: app/Support/Users/LocalAvatar.php
 *
 * Purpose
 * -----------------------------------------------------------------------------
 * Make WordPress (including wp-admin) use the uploaded profile image stored in:
 * - user meta: sccc_profile_image_id (WP attachment ID)
 *
 * Why you want this
 * -----------------------------------------------------------------------------
 * - Frontend already shows the uploaded image in your dashboard shell,
 *   but wp-admin still uses the default avatar system (Gravatar).
 * - This filter makes the admin UI (Users list, profile page, comment avatars,
 *   admin bar, etc.) show the same uploaded image when it exists.
 *
 * Behavior
 * -----------------------------------------------------------------------------
 * - If a user has sccc_profile_image_id and it’s a valid image attachment,
 *   we return that URL as the avatar URL.
 * - Otherwise, WordPress behaves normally.
 */

namespace App\Support\Users;

class LocalAvatar
{
    /**
     * Boot (self-register)
     */
    public static function boot(): void
    {
        // Best modern hook: lets us override avatar URL safely.
        add_filter('pre_get_avatar_data', [static::class, 'overrideAvatar'], 10, 2);
    }

    /**
     * Override avatar URL using the user's uploaded profile image (if set).
     *
     * @param array $args        Avatar data (url, size, default, etc.)
     * @param mixed $id_or_email A user ID, email, user object, comment object, etc.
     */
    public static function overrideAvatar(array $args, $id_or_email): array
    {
        $user_id = static::resolveUserId($id_or_email);
        if ($user_id <= 0) {
            return $args;
        }

        $attachment_id = (int) get_user_meta($user_id, 'sccc_profile_image_id', true);
        if ($attachment_id <= 0) {
            return $args;
        }

        // Ensure the attachment is an image and get a size appropriate URL.
        if (! wp_attachment_is_image($attachment_id)) {
            return $args;
        }

        $size = isset($args['size']) ? (int) $args['size'] : 96;

        // Use WP image sizes; "thumbnail" is usually fine, but match requested size.
        // WordPress will pick the closest available size.
        $url = (string) wp_get_attachment_image_url($attachment_id, [$size, $size]);

        if ($url === '') {
            // Fallback to thumbnail if custom size lookup fails.
            $url = (string) wp_get_attachment_image_url($attachment_id, 'thumbnail');
        }

        if ($url !== '') {
            $args['url'] = $url;
        }

        return $args;
    }

    /**
     * Resolve user ID from the mixed $id_or_email parameter WordPress passes.
     */
    protected static function resolveUserId($id_or_email): int
    {
        // User ID
        if (is_numeric($id_or_email)) {
            return (int) $id_or_email;
        }

        // WP_User object
        if (is_object($id_or_email) && isset($id_or_email->ID)) {
            return (int) $id_or_email->ID;
        }

        // Email string
        if (is_string($id_or_email) && $id_or_email !== '') {
            $user = get_user_by('email', $id_or_email);
            return $user ? (int) $user->ID : 0;
        }

        // Comment object can contain a user_id
        if (is_object($id_or_email) && isset($id_or_email->user_id)) {
            return (int) $id_or_email->user_id;
        }

        return 0;
    }
}

// Self-register once required.
add_action('after_setup_theme', [LocalAvatar::class, 'boot']);
