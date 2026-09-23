<?php

/**
 * File: app/Support/Woo/ProductAccess.php
 *
 * Space City Car Club WooCommerce product access rules.
 *
 * Purpose:
 * - Product tags with slug "sccc-member" or "member" are visible/purchasable only by users with the "sccc_member" role.
 * - Product tags with slug "sccc-supporter" or "supporter" are visible/purchasable only by guests and normal WooCommerce shoppers.
 * - Products without either access tag are visible/purchasable by everyone.
 *
 * Notes:
 * - Categories remain clean for store navigation.
 * - Tags are used as internal access-control flags.
 * - Logged-in WooCommerce customers are NOT considered members unless they have the "sccc_member" role.
 * - This file auto-registers once it is loaded with require_once.
 */

namespace App\Support\Woo;

use WP_Post;
use WP_Query;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_User;

final class ProductAccess
{
    /**
     * Prevent duplicate hook registration if the file is loaded more than once.
     */
    private static bool $registered = false;

    /**
     * The real club member role.
     */
    private const MEMBER_ROLE = 'sccc_member';

    /**
     * Product tag slugs for member-only products.
     */
    private const MEMBER_TAGS = [
        'sccc-member',
        'member',
    ];

    /**
     * Product tag slugs for public/supporter products.
     */
    private const SUPPORTER_TAGS = [
        'sccc-supporter',
        'supporter',
    ];

    /**
     * Keep false for real frontend testing.
     *
     * If true, admins/shop managers can see all frontend products.
     */
    private const FRONTEND_ADMIN_BYPASS = false;

    /**
     * Set true temporarily if you need debug.log entries.
     */
    private const DEBUG = false;

    /**
     * Register all WooCommerce access hooks.
     */
    public static function register(): void
    {
        if (self::$registered) {
            return;
        }

        self::$registered = true;

        /*
         * Direct product URL protection.
         */
        add_action('template_redirect', [self::class, 'protectSingleProductPage'], 20);

        /*
         * Classic WP/WooCommerce query layers.
         */
        add_action('pre_get_posts', [self::class, 'filterWpProductQueries'], 20);
        add_action('woocommerce_product_query', [self::class, 'filterWooCommerceProductQuery'], 20, 1);

        add_filter('woocommerce_product_query_tax_query', [self::class, 'filterWooCommerceTaxQuery'], 20, 1);
        add_filter('woocommerce_shortcode_products_query', [self::class, 'filterShortcodeProductsQuery'], 20, 3);

        /*
         * WC_Product_Query / wc_get_products() layer.
         */
        add_filter('woocommerce_product_data_store_cpt_get_products_query', [self::class, 'filterProductDataStoreQuery'], 20, 3);

        /*
         * SQL-level safety net for product queries, including many block/custom query paths.
         */
        add_filter('posts_clauses', [self::class, 'filterProductSqlClauses'], 20, 2);

        /*
         * Final post-array safety net.
         */
        add_filter('the_posts', [self::class, 'filterReturnedPosts'], 20, 2);

        /*
         * WooCommerce visibility / related products / linked product layers.
         */
        add_filter('woocommerce_product_is_visible', [self::class, 'filterProductVisibility'], 20, 2);

        add_filter('woocommerce_related_products', [self::class, 'filterProductIdList'], 20, 3);
        add_filter('woocommerce_product_get_upsell_ids', [self::class, 'filterProductIdList'], 20, 2);
        add_filter('woocommerce_product_get_cross_sell_ids', [self::class, 'filterProductIdList'], 20, 2);

        /*
         * Purchasing protection.
         */
        add_filter('woocommerce_is_purchasable', [self::class, 'filterPurchasable'], 20, 2);
        add_filter('woocommerce_variation_is_purchasable', [self::class, 'filterPurchasable'], 20, 2);

        add_filter('woocommerce_add_to_cart_validation', [self::class, 'validateAddToCart'], 20, 6);
        add_action('woocommerce_check_cart_items', [self::class, 'validateCartItems'], 20);

        /*
         * Store API response safety net for WooCommerce Blocks/Product Collection output.
         */
        add_filter('rest_post_dispatch', [self::class, 'filterStoreApiProductsResponse'], 20, 3);
    }

    /**
     * Hide restricted products from standard WordPress product queries.
     */
    public static function filterWpProductQueries(WP_Query $query): void
    {
        if (! self::shouldFilterCatalogQuery($query)) {
            return;
        }

        $taxQuery = $query->get('tax_query');

        $query->set(
            'tax_query',
            self::appendAccessTaxQuery(is_array($taxQuery) ? $taxQuery : [])
        );
    }

    /**
     * Hide restricted products from WooCommerce catalog queries.
     *
     * @param mixed $query WC_Query query object.
     */
    public static function filterWooCommerceProductQuery($query): void
    {
        if (is_admin() || self::isFrontendAdminBypassed()) {
            return;
        }

        if (! is_object($query) || ! method_exists($query, 'get') || ! method_exists($query, 'set')) {
            return;
        }

        $taxQuery = $query->get('tax_query');

        $query->set(
            'tax_query',
            self::appendAccessTaxQuery(is_array($taxQuery) ? $taxQuery : [])
        );
    }

    /**
     * Hide restricted products from WooCommerce catalog tax queries.
     */
    public static function filterWooCommerceTaxQuery(array $taxQuery): array
    {
        if (is_admin() || self::isFrontendAdminBypassed()) {
            return $taxQuery;
        }

        return self::appendAccessTaxQuery($taxQuery);
    }

    /**
     * Hide restricted products from WooCommerce [products] shortcode queries.
     */
    public static function filterShortcodeProductsQuery(array $queryArgs, array $atts = [], string $type = ''): array
    {
        if (is_admin() || self::isFrontendAdminBypassed()) {
            return $queryArgs;
        }

        $existingTaxQuery = [];

        if (! empty($queryArgs['tax_query']) && is_array($queryArgs['tax_query'])) {
            $existingTaxQuery = $queryArgs['tax_query'];
        }

        $queryArgs['tax_query'] = self::appendAccessTaxQuery($existingTaxQuery);

        return $queryArgs;
    }

    /**
     * Hide restricted products from WC_Product_Query / wc_get_products().
     *
     * @param array<string,mixed> $wpQueryArgs
     * @param array<string,mixed> $queryVars
     * @param mixed $dataStore
     *
     * @return array<string,mixed>
     */
    public static function filterProductDataStoreQuery(array $wpQueryArgs, array $queryVars, $dataStore): array
    {
        if (is_admin() || self::isFrontendAdminBypassed()) {
            return $wpQueryArgs;
        }

        $existingTaxQuery = [];

        if (! empty($wpQueryArgs['tax_query']) && is_array($wpQueryArgs['tax_query'])) {
            $existingTaxQuery = $wpQueryArgs['tax_query'];
        }

        $wpQueryArgs['tax_query'] = self::appendAccessTaxQuery($existingTaxQuery);

        return $wpQueryArgs;
    }

    /**
     * SQL-level safety net for product catalog queries.
     *
     * This catches product grids/custom queries that do not reliably pass through
     * the normal WooCommerce loop filters.
     *
     * @param array<string,string> $clauses
     *
     * @return array<string,string>
     */
    public static function filterProductSqlClauses(array $clauses, WP_Query $query): array
    {
        if (! self::shouldFilterCatalogQuery($query)) {
            return $clauses;
        }

        $termTaxonomyIds = self::restrictedTermTaxonomyIdsForCurrentUser();

        if (empty($termTaxonomyIds)) {
            return $clauses;
        }

        global $wpdb;

        $ids = implode(',', array_map('absint', $termTaxonomyIds));

        if ($ids === '') {
            return $clauses;
        }

        $clauses['where'] .= "
            AND NOT EXISTS (
                SELECT 1
                FROM {$wpdb->term_relationships} AS sccc_access_tr
                WHERE sccc_access_tr.term_taxonomy_id IN ({$ids})
                AND (
                    sccc_access_tr.object_id = {$wpdb->posts}.ID
                    OR sccc_access_tr.object_id = {$wpdb->posts}.post_parent
                )
            )
        ";

        return $clauses;
    }

    /**
     * Final safety net after posts are returned.
     *
     * @param array<int,WP_Post|mixed> $posts
     *
     * @return array<int,WP_Post|mixed>
     */
    public static function filterReturnedPosts(array $posts, WP_Query $query): array
    {
        if (! self::shouldFilterPostArray($query)) {
            return $posts;
        }

        $filtered = [];

        foreach ($posts as $post) {
            if (! $post instanceof WP_Post) {
                $filtered[] = $post;
                continue;
            }

            if (! in_array($post->post_type, ['product', 'product_variation'], true)) {
                $filtered[] = $post;
                continue;
            }

            if (self::isProductAllowedForCurrentUser((int) $post->ID)) {
                $filtered[] = $post;
            }
        }

        return array_values($filtered);
    }

    /**
     * Filter WooCommerce Store API product responses used by product blocks.
     *
     * @param mixed $response
     *
     * @return mixed
     */
    public static function filterStoreApiProductsResponse($response, WP_REST_Server $server, WP_REST_Request $request)
    {
        if (is_admin() || self::isFrontendAdminBypassed()) {
            return $response;
        }

        if (! $response instanceof WP_REST_Response) {
            return $response;
        }

        $route = $request->get_route();

        if (! self::isStoreApiProductsListRoute($route)) {
            return $response;
        }

        $data = $response->get_data();

        if (! is_array($data) || ! array_is_list($data)) {
            return $response;
        }

        $filtered = array_filter($data, static function ($item): bool {
            if (! is_array($item) || empty($item['id'])) {
                return true;
            }

            return self::isProductAllowedForCurrentUser((int) $item['id']);
        });

        $response->set_data(array_values($filtered));

        return $response;
    }

    /**
     * Hide restricted products from WooCommerce loops/cards.
     */
    public static function filterProductVisibility(bool $visible, int $productId): bool
    {
        if (! $visible) {
            return false;
        }

        return self::isProductAllowedForCurrentUser($productId);
    }

    /**
     * Remove restricted products from related products, upsells, and cross-sells.
     *
     * @param array<int> $productIds
     * @param mixed ...$args
     *
     * @return array<int>
     */
    public static function filterProductIdList(array $productIds, ...$args): array
    {
        if (self::isFrontendAdminBypassed()) {
            return $productIds;
        }

        $filtered = array_filter(
            $productIds,
            static fn ($productId): bool => self::isProductAllowedForCurrentUser((int) $productId)
        );

        return array_values($filtered);
    }

    /**
     * Prevent restricted products from being purchasable.
     *
     * @param mixed $product
     */
    public static function filterPurchasable(bool $purchasable, $product): bool
    {
        if (! $purchasable) {
            return false;
        }

        $productId = self::getProductIdFromMixedProduct($product);

        if (! $productId) {
            return $purchasable;
        }

        return self::isProductAllowedForCurrentUser($productId);
    }

    /**
     * Block direct add-to-cart attempts.
     *
     * @param mixed $variations
     * @param mixed $cartItemData
     */
    public static function validateAddToCart(
        bool $passed,
        int $productId,
        int $quantity,
        int $variationId = 0,
        $variations = [],
        $cartItemData = []
    ): bool {
        if (! $passed) {
            return false;
        }

        $accessProductId = $variationId > 0 ? $variationId : $productId;

        if (self::isProductAllowedForCurrentUser($accessProductId)) {
            return true;
        }

        self::addAccessNotice($accessProductId, 'error');

        return false;
    }

    /**
     * Remove invalid items from the cart if the user's access state changed.
     */
    public static function validateCartItems(): void
    {
        if (! function_exists('WC') || ! WC()->cart) {
            return;
        }

        foreach (WC()->cart->get_cart() as $cartItemKey => $cartItem) {
            $productId = 0;

            if (! empty($cartItem['variation_id'])) {
                $productId = (int) $cartItem['variation_id'];
            } elseif (! empty($cartItem['product_id'])) {
                $productId = (int) $cartItem['product_id'];
            }

            if (! $productId) {
                continue;
            }

            if (self::isProductAllowedForCurrentUser($productId)) {
                continue;
            }

            WC()->cart->remove_cart_item($cartItemKey);

            self::addAccessNotice($productId, 'notice');
        }
    }

    /**
     * Protect direct single product URLs.
     */
    public static function protectSingleProductPage(): void
    {
        if (self::isFrontendAdminBypassed()) {
            return;
        }

        if (! function_exists('is_product') || ! is_product()) {
            return;
        }

        $productId = get_queried_object_id();

        if (! $productId) {
            return;
        }

        if (self::isProductAllowedForCurrentUser((int) $productId)) {
            return;
        }

        self::debug('Blocked direct product URL.', [
            'product_id' => $productId,
            'user_id'    => get_current_user_id(),
            'is_member'  => self::currentUserIsScccMember(),
        ]);

        self::addAccessNotice((int) $productId, 'notice');

        wp_safe_redirect(self::getShopRedirectUrl());
        exit;
    }

    /**
     * Determines whether the product should be visible/purchasable for the current user.
     */
    private static function isProductAllowedForCurrentUser(int $productId): bool
    {
        if (self::isFrontendAdminBypassed()) {
            return true;
        }

        $isMember = self::currentUserIsScccMember();

        $hasMemberTag = self::productHasAnyTag($productId, self::MEMBER_TAGS);
        $hasSupporterTag = self::productHasAnyTag($productId, self::SUPPORTER_TAGS);

        /*
         * Member-tagged products:
         * Only users with the sccc_member role can see/buy them.
         */
        if ($hasMemberTag && ! $isMember) {
            return false;
        }

        /*
         * Supporter-tagged products:
         * Public shoppers can see/buy them.
         * Real club members do not see/buy them.
         */
        if ($hasSupporterTag && $isMember) {
            return false;
        }

        /*
         * Untagged products:
         * Visible to everyone.
         */
        return true;
    }

    /**
     * Adds the proper NOT IN product_tag filter for the current user.
     */
    private static function appendAccessTaxQuery(array $taxQuery): array
    {
        $restrictedTags = self::restrictedTagSlugsForCurrentUser();

        if (empty($restrictedTags)) {
            return $taxQuery;
        }

        $accessTaxQuery = [
            'taxonomy' => 'product_tag',
            'field'    => 'slug',
            'terms'    => $restrictedTags,
            'operator' => 'NOT IN',
        ];

        if (empty($taxQuery)) {
            return [$accessTaxQuery];
        }

        return [
            'relation' => 'AND',
            $taxQuery,
            $accessTaxQuery,
        ];
    }

    /**
     * Returns tag slugs that should be hidden from the current user.
     *
     * @return array<string>
     */
    private static function restrictedTagSlugsForCurrentUser(): array
    {
        if (self::isFrontendAdminBypassed()) {
            return [];
        }

        if (self::currentUserIsScccMember()) {
            return self::SUPPORTER_TAGS;
        }

        return self::MEMBER_TAGS;
    }

    /**
     * Returns restricted product_tag term_taxonomy_id values for the current user.
     *
     * @return array<int>
     */
    private static function restrictedTermTaxonomyIdsForCurrentUser(): array
    {
        $restrictedTags = self::restrictedTagSlugsForCurrentUser();

        if (empty($restrictedTags) || ! taxonomy_exists('product_tag')) {
            return [];
        }

        $terms = get_terms([
            'taxonomy'   => 'product_tag',
            'slug'       => $restrictedTags,
            'hide_empty' => false,
        ]);

        if (is_wp_error($terms) || empty($terms)) {
            return [];
        }

        $ids = [];

        foreach ($terms as $term) {
            if (! empty($term->term_taxonomy_id)) {
                $ids[] = (int) $term->term_taxonomy_id;
            }
        }

        return array_values(array_unique(array_filter($ids)));
    }

    /**
     * Checks whether the current user is a real SCCC member.
     *
     * Important:
     * Logged-in WooCommerce shoppers are NOT members unless they have this role.
     */
    private static function currentUserIsScccMember(): bool
    {
        $user = wp_get_current_user();

        if (! $user instanceof WP_User || ! $user->exists()) {
            return false;
        }

        return in_array(self::MEMBER_ROLE, (array) $user->roles, true);
    }

    /**
     * Frontend admin/shop-manager bypass.
     *
     * Keep disabled for accurate testing.
     */
    private static function isFrontendAdminBypassed(): bool
    {
        if (! self::FRONTEND_ADMIN_BYPASS) {
            return false;
        }

        if (is_admin()) {
            return false;
        }

        return current_user_can('manage_woocommerce') || current_user_can('manage_options');
    }

    /**
     * Checks whether a product or its parent variable product has any of the given product tags.
     *
     * @param array<string> $tagSlugs
     */
    private static function productHasAnyTag(int $productId, array $tagSlugs): bool
    {
        if (! taxonomy_exists('product_tag')) {
            return false;
        }

        $lookupProductId = self::getParentProductId($productId);

        if (! $lookupProductId) {
            return false;
        }

        return has_term($tagSlugs, 'product_tag', $lookupProductId);
    }

    /**
     * For variations, use the parent product ID because tags normally live on the parent.
     */
    private static function getParentProductId(int $productId): int
    {
        if (! function_exists('wc_get_product')) {
            return $productId;
        }

        $product = wc_get_product($productId);

        if (! $product) {
            return $productId;
        }

        if ($product->is_type('variation')) {
            $parentId = (int) $product->get_parent_id();

            return $parentId > 0 ? $parentId : $productId;
        }

        return (int) $product->get_id();
    }

    /**
     * Safely extracts a product ID from a WC product object or fallback value.
     *
     * @param mixed $product
     */
    private static function getProductIdFromMixedProduct($product): int
    {
        if (is_object($product) && method_exists($product, 'get_id')) {
            return (int) $product->get_id();
        }

        if (is_numeric($product)) {
            return (int) $product;
        }

        return 0;
    }

    /**
     * Determines whether this WP_Query should be filtered as a catalog/listing query.
     */
    private static function shouldFilterCatalogQuery(WP_Query $query): bool
    {
        if (is_admin() || self::isFrontendAdminBypassed()) {
            return false;
        }

        /*
         * Do not filter the main single product query here.
         * Let template_redirect handle direct URL protection so we can show a proper notice/redirect.
         */
        if ($query->is_main_query() && $query->is_singular('product')) {
            return false;
        }

        return self::isProductQuery($query);
    }

    /**
     * Determines whether the final returned post array should be filtered.
     */
    private static function shouldFilterPostArray(WP_Query $query): bool
    {
        if (is_admin() || self::isFrontendAdminBypassed()) {
            return false;
        }

        if ($query->is_main_query() && $query->is_singular('product')) {
            return false;
        }

        return true;
    }

    /**
     * Determines whether this WP_Query is product-related.
     */
    private static function isProductQuery(WP_Query $query): bool
    {
        $postType = $query->get('post_type');

        if ($postType === 'product' || $postType === 'product_variation') {
            return true;
        }

        if (is_array($postType)) {
            if (in_array('product', $postType, true) || in_array('product_variation', $postType, true)) {
                return true;
            }
        }

        if ($query->is_post_type_archive('product')) {
            return true;
        }

        if ($query->is_tax('product_cat') || $query->is_tax('product_tag')) {
            return true;
        }

        return false;
    }

    /**
     * Detect WooCommerce Store API product collection route.
     */
    private static function isStoreApiProductsListRoute(string $route): bool
    {
        return (bool) preg_match('#^/wc/store/v[0-9]+/products/?$#', $route);
    }

    /**
     * Adds a user-facing WooCommerce notice.
     */
    private static function addAccessNotice(int $productId, string $type = 'notice'): void
    {
        if (! function_exists('wc_add_notice')) {
            return;
        }

        $message = self::getAccessMessage($productId);

        wc_add_notice($message, $type);
    }

    /**
     * Returns the correct message for the restricted product.
     */
    private static function getAccessMessage(int $productId): string
    {
        if (self::productHasAnyTag($productId, self::MEMBER_TAGS)) {
            return __('This item is reserved for active Space City Car Club members.', 'sccc');
        }

        if (self::productHasAnyTag($productId, self::SUPPORTER_TAGS)) {
            return __('This supporter item is part of the public shop experience. Please choose the member version instead.', 'sccc');
        }

        return __('This item is not available for your current account type.', 'sccc');
    }

    /**
     * Redirect restricted direct product URLs back to the shop page.
     */
    private static function getShopRedirectUrl(): string
    {
        if (function_exists('wc_get_page_permalink')) {
            $shopUrl = wc_get_page_permalink('shop');

            if (! empty($shopUrl)) {
                return $shopUrl;
            }
        }

        return home_url('/shop/');
    }

    /**
     * Debug helper.
     *
     * @param array<string,mixed> $context
     */
    private static function debug(string $message, array $context = []): void
    {
        if (! self::DEBUG) {
            return;
        }

        error_log('[SCCC ProductAccess] ' . $message . ' ' . wp_json_encode($context));
    }
}

/*
|--------------------------------------------------------------------------
| Auto-register
|--------------------------------------------------------------------------
|
| The file only needs to be loaded once with require_once.
| No separate ProductAccess::register() call is needed elsewhere.
|
*/

ProductAccess::register();