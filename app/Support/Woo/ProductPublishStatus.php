<?php

/**
 * File path + filename: app/Support/Woo/ProductPublishStatus.php
 *
 * Purpose:
 * - Products pushed from Printify land in WooCommerce as drafts, not published.
 * - Any later Printify republish of the same product keeps whatever status the
 *   product already has in WordPress (draft, pending, private or publish).
 *
 * Why this file exists:
 * - Printify creates and updates products through the WooCommerce REST API and
 *   always sends status "publish".
 * - New products still need categories, attributes and other assignments
 *   before they go live, so they should start as drafts.
 * - Once a product is live (or deliberately kept as a draft), a republish from
 *   Printify should not flip it.
 *
 * Scope:
 * - Only WooCommerce REST API product create/update requests are affected
 *   (woocommerce_rest_pre_insert_product_object). Saving a product in
 *   wp-admin is not affected, so publishing by hand works as normal.
 * - Variations are not touched (their "status" is the enabled/disabled toggle).
 * - Printify is currently the only REST client that writes products. If another
 *   integration is added later, it will get the same behaviour.
 */

declare(strict_types=1);

namespace App\Support\Woo;

use WC_Product;
use WP_REST_Request;

class ProductPublishStatus
{
    /**
     * Status new REST-created products start in.
     */
    private const NEW_PRODUCT_STATUS = 'draft';

    /**
     * Prevent duplicate hook registration.
     */
    private static bool $registered = false;

    public static function register(): void
    {
        if (self::$registered) {
            return;
        }

        self::$registered = true;

        add_filter('woocommerce_rest_pre_insert_product_object', [self::class, 'filterStatus'], 10, 3);
    }

    /**
     * Force drafts on create; keep the WordPress status on update.
     *
     * @param WC_Product|mixed $product
     * @return WC_Product|mixed
     */
    public static function filterStatus($product, $request, $creating)
    {
        if (! $product instanceof WC_Product || ! $request instanceof WP_REST_Request) {
            return $product;
        }

        /**
         * Escape hatch: return false to let REST requests set status as sent.
         */
        if (! apply_filters('sccc/printify/control_product_status', true, $product, $request, $creating)) {
            return $product;
        }

        if ($creating) {
            $product->set_status(self::NEW_PRODUCT_STATUS);

            return $product;
        }

        // Update: only step in if the request tried to set a status.
        if ($request->get_param('status') === null) {
            return $product;
        }

        $currentStatus = get_post_status($product->get_id());

        if ($currentStatus && $currentStatus !== 'auto-draft' && $currentStatus !== 'trash') {
            $product->set_status($currentStatus);
        }

        return $product;
    }
}

ProductPublishStatus::register();
