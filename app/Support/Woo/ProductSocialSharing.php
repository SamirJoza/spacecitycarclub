<?php

/**
 * File path + filename: app/Support/Woo/ProductSocialSharing.php
 *
 * Purpose:
 * - Products are NOT shared to Facebook (or other Jetpack Social connections)
 *   automatically when they are published.
 * - Products CAN still be shared by choice: tick the connection in the Jetpack
 *   Social box of the product editor before publishing (or scheduling).
 * - Blog posts and every other post type keep Jetpack's normal auto-sharing.
 *
 * How (checked against Jetpack / Jetpack Social's Publicize package):
 * 1. publicize_checkbox_default
 *    - The connection checkboxes in the product editor start unticked, instead
 *      of Jetpack's default of all ticked.
 * 2. publicize_should_publicize_published_post (backstop)
 *    - A product is only shared when someone ticked at least one connection in
 *      the product editor. Publishing from anywhere else (REST API, Printify,
 *      quick/bulk edit) never shares a product.
 *    - The choice is stored in product meta so scheduled products, which are
 *      published later by WP-Cron, still honour what was ticked.
 *
 * Note:
 * - Re-sharing an already-published product ("Share now" button / "Share"
 *   row action) is a Jetpack paid-plan feature. When available, it is not
 *   affected by this file.
 */

declare(strict_types=1);

namespace App\Support\Woo;

use WP_Post;

class ProductSocialSharing
{
    private const POST_TYPE = 'product';

    /**
     * "1" when a connection was ticked in the product editor, "0" when none.
     */
    private const OPT_IN_META_KEY = '_sccc_social_share_opt_in';

    private static bool $registered = false;

    public static function register(): void
    {
        if (self::$registered) {
            return;
        }

        self::$registered = true;

        add_filter('publicize_checkbox_default', [self::class, 'uncheckByDefault'], 10, 4);
        add_filter('publicize_should_publicize_published_post', [self::class, 'onlyWhenOptedIn'], 10, 2);
    }

    /**
     * Start product connection checkboxes unticked.
     *
     * Jetpack forces the box ticked again for connections already shared to,
     * so the editor still shows what was shared.
     *
     * @param bool|mixed $enabled
     * @param int|null $postId
     * @return bool|mixed
     */
    public static function uncheckByDefault($enabled, $postId, $serviceName = '', $connection = [])
    {
        if (! $postId || get_post_type((int) $postId) !== self::POST_TYPE) {
            return $enabled;
        }

        // Keep a choice the user already made on this product (e.g. scheduled).
        if (get_post_meta((int) $postId, self::OPT_IN_META_KEY, true) === '1') {
            return $enabled;
        }

        return false;
    }

    /**
     * Share a product on publish only if a connection was ticked for it.
     *
     * @param bool|mixed $shouldPublicize
     * @param WP_Post|mixed $post
     * @return bool|mixed
     */
    public static function onlyWhenOptedIn($shouldPublicize, $post)
    {
        if (! $post instanceof WP_Post || $post->post_type !== self::POST_TYPE) {
            return $shouldPublicize;
        }

        if (! $shouldPublicize) {
            return false;
        }

        $optedIn = self::readEditorChoice($post->ID);

        if ($optedIn === null) {
            // Not a product editor save (cron, REST, etc.): use the stored choice.
            $optedIn = get_post_meta($post->ID, self::OPT_IN_META_KEY, true) === '1';
        }

        return $optedIn;
    }

    /**
     * Read and store the Jetpack Social checkboxes from a product editor save.
     *
     * Returns null when this request is not a product editor form submit.
     */
    private static function readEditorChoice(int $postId): ?bool
    {
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- WordPress has verified the post.php nonce; Jetpack reads the same field.
        if (! is_admin() || ! isset($_POST['wpas']) || ! is_array($_POST['wpas'])) {
            return null;
        }

        if (! current_user_can('edit_post', $postId)) {
            return null;
        }

        $submitted = $_POST['wpas']['submit'] ?? [];
        // phpcs:enable

        $optedIn = is_array($submitted) && array_filter($submitted) !== [];

        update_post_meta($postId, self::OPT_IN_META_KEY, $optedIn ? '1' : '0');

        return $optedIn;
    }
}

ProductSocialSharing::register();
