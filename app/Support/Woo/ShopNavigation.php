<?php

/**
 * File path + filename: app/Support/Woo/ShopNavigation.php
 *
 * Purpose:
 * - Provide the WooCommerce shop category navigation data.
 * - Render the shop navigation inside the site header.
 * - Show the shop navigation on shop archives, product category archives,
 *   product tag archives, and single product pages.
 * - Move WooCommerce breadcrumbs into a styled wrapper below the sticky header.
 * - Hide the default WooCommerce archive title on shop archive pages.
 *
 * Why this file exists:
 * - The shop category structure is controlled by WooCommerce product_cat terms.
 * - The header Blade file should not contain taxonomy/query logic.
 * - The shop nav should live visually inside the sticky header, not inside the
 *   WooCommerce product loop area.
 *
 * What this file does not do yet:
 * - It does not hide member-only products.
 * - It does not change Printify/WooCommerce prices.
 * - It does not alter WooCommerce product queries.
 */

declare(strict_types=1);

namespace App\Support\Woo;

use WP_Term;

class ShopNavigation
{
    /**
     * WooCommerce product category taxonomy.
     */
    private const TAXONOMY = 'product_cat';

    /**
     * Maximum category depth rendered in the navigation.
     *
     * Current structure:
     * - Apparel
     *   - Men
     *     - Hoodies
     */
    private const MAX_DEPTH = 3;

    /**
     * Whether empty categories should show.
     *
     * Why false:
     * During setup, imported categories should remain visible even before all
     * Printify products are pushed.
     */
    private const HIDE_EMPTY_CATEGORIES = false;

    /**
     * Product category slugs that should never appear in the shop nav.
     *
     * @var array<int, string>
     */
    private const EXCLUDED_SLUGS = [
        'uncategorized',
    ];

    /**
     * Prevent duplicate hook registration.
     */
    private static bool $registered = false;

    /**
     * Prevent duplicate frontend hook registration.
     */
    private static bool $frontendHooksRegistered = false;

    /**
     * Register WordPress/WooCommerce hooks.
     */
    public static function register(): void
    {
        if (self::$registered) {
            return;
        }

        self::$registered = true;

        /**
         * Register frontend breadcrumb behavior after the main query is ready.
         *
         * Why:
         * WooCommerce conditional tags are safest once WordPress has resolved the
         * current request.
         */
        add_action('wp', [self::class, 'registerFrontendHooks']);

        /**
         * Hide the default WooCommerce archive title where this custom shop nav
         * becomes the visual shop browser.
         */
        add_filter('woocommerce_show_page_title', [self::class, 'hideArchivePageTitle']);
    }

    /**
     * Register frontend-only WooCommerce hook changes.
     *
     * Why:
     * The shop nav itself is rendered in the header. Breadcrumbs should stay in
     * the WooCommerce content area, directly below the sticky header/shop nav.
     */
    public static function registerFrontendHooks(): void
    {
        if (self::$frontendHooksRegistered) {
            return;
        }

        if (! self::shouldShowShopNavigation()) {
            return;
        }

        self::$frontendHooksRegistered = true;

        /**
         * Remove the default WooCommerce breadcrumb output and replace it with a
         * styled wrapper. The hook location stays the same so the breadcrumb
         * remains in the normal WooCommerce content flow.
         */
        remove_action('woocommerce_before_main_content', 'woocommerce_breadcrumb', 20);
        add_action('woocommerce_before_main_content', [self::class, 'renderBreadcrumbs'], 20);
    }

    /**
     * Render the header shop navigation.
     *
     * Why:
     * Header Blade can call this safely without needing to know WooCommerce logic.
     */
    public static function renderHeaderNavigation(): string
    {
        if (! self::shouldShowShopNavigation()) {
            return '';
        }

        if (! function_exists('view')) {
            return '';
        }

        $nodes = self::getCategoryTree();

        if (empty($nodes)) {
            return '';
        }

        return view('woocommerce.partials.shop-category-nav', [
            'nodes' => $nodes,
            'shopUrl' => self::getShopUrl(),
            'isShopLanding' => self::isShopLanding(),
        ])->render();
    }

    /**
     * Hide WooCommerce archive page titles.
     *
     * Why:
     * The giant "Shop" title became redundant once the shop nav moved into the
     * header as the main shop browsing interface.
     */
    public static function hideArchivePageTitle(bool $show): bool
    {
        if (! self::isShopArchive()) {
            return $show;
        }

        return false;
    }

    /**
     * Render WooCommerce breadcrumbs with SCCC classes.
     *
     * Why:
     * This keeps breadcrumbs below the sticky header/shop nav and above the shop
     * controls/product content.
     */
    public static function renderBreadcrumbs(): void
    {
        if (! self::shouldShowShopNavigation()) {
            return;
        }

        if (! function_exists('woocommerce_breadcrumb')) {
            return;
        }

        woocommerce_breadcrumb([
            'wrap_before' => '<nav class="sccc-shop-breadcrumbs" aria-label="' . esc_attr__('Breadcrumb', 'woocommerce') . '"><div class="sccc-shop-breadcrumbs__container">',
            'wrap_after' => '</div></nav>',
            'delimiter' => '<span class="sccc-shop-breadcrumbs__separator" aria-hidden="true">/</span>',
            'before' => '<span class="sccc-shop-breadcrumbs__item">',
            'after' => '</span>',
        ]);
    }

    /**
     * Decide whether the shop nav should show.
     *
     * Included:
     * - Main shop archive.
     * - Product category archives.
     * - Product tag archives.
     * - Single product pages.
     *
     * Excluded:
     * - Cart.
     * - Checkout.
     * - My Account.
     * - Normal pages/posts.
     */
    public static function shouldShowShopNavigation(): bool
    {
        if (is_admin()) {
            return false;
        }

        if (function_exists('wp_doing_ajax') && wp_doing_ajax()) {
            return false;
        }

        $isShop = function_exists('is_shop') && is_shop();
        $isProductCategory = function_exists('is_product_category') && is_product_category();
        $isProductTag = function_exists('is_product_tag') && is_product_tag();
        $isProduct = function_exists('is_product') && is_product();

        return $isShop || $isProductCategory || $isProductTag || $isProduct;
    }

    /**
     * Decide whether the current request is a shop archive.
     *
     * Why:
     * We hide the big WooCommerce archive title on archives only, not on single
     * product pages.
     */
    private static function isShopArchive(): bool
    {
        $isShop = function_exists('is_shop') && is_shop();
        $isProductCategory = function_exists('is_product_category') && is_product_category();
        $isProductTag = function_exists('is_product_tag') && is_product_tag();

        return $isShop || $isProductCategory || $isProductTag;
    }

    /**
     * Check if the current page is the main shop landing page.
     */
    private static function isShopLanding(): bool
    {
        return function_exists('is_shop') && is_shop();
    }

    /**
     * Build the full product category tree.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function getCategoryTree(): array
    {
        $terms = get_terms([
            'taxonomy' => self::TAXONOMY,
            'hide_empty' => self::HIDE_EMPTY_CATEGORIES,
            'orderby' => 'name',
            'order' => 'ASC',
        ]);

        if (is_wp_error($terms) || empty($terms)) {
            return [];
        }

        $termsByParent = [];

        foreach ($terms as $term) {
            if (! $term instanceof WP_Term) {
                continue;
            }

            if (in_array($term->slug, self::EXCLUDED_SLUGS, true)) {
                continue;
            }

            $parentId = (int) $term->parent;

            if (! isset($termsByParent[$parentId])) {
                $termsByParent[$parentId] = [];
            }

            $termsByParent[$parentId][] = $term;
        }

        return self::buildNodes(
            $termsByParent,
            0,
            self::getActiveCategoryIds(),
            1
        );
    }

    /**
     * Recursively build navigation nodes.
     *
     * @param array<int, array<int, WP_Term>> $termsByParent
     * @param array<int, int> $activeIds
     *
     * @return array<int, array<string, mixed>>
     */
    private static function buildNodes(array $termsByParent, int $parentId, array $activeIds, int $depth): array
    {
        if ($depth > self::MAX_DEPTH) {
            return [];
        }

        if (empty($termsByParent[$parentId])) {
            return [];
        }

        $nodes = [];

        foreach ($termsByParent[$parentId] as $term) {
            $url = get_term_link($term);

            if (is_wp_error($url)) {
                continue;
            }

            $children = self::buildNodes(
                $termsByParent,
                (int) $term->term_id,
                $activeIds,
                $depth + 1
            );

            $isCurrent = in_array((int) $term->term_id, $activeIds, true);
            $isActiveTrail = $isCurrent || self::childrenContainActiveNode($children);

            $nodes[] = [
                'id' => (int) $term->term_id,
                'name' => (string) $term->name,
                'slug' => (string) $term->slug,
                'url' => (string) $url,
                'depth' => $depth,
                'isCurrent' => $isCurrent,
                'isActiveTrail' => $isActiveTrail,
                'children' => $children,
            ];
        }

        return $nodes;
    }

    /**
     * Check whether any child node is part of the active trail.
     *
     * @param array<int, array<string, mixed>> $children
     */
    private static function childrenContainActiveNode(array $children): bool
    {
        foreach ($children as $child) {
            if (! empty($child['isActiveTrail'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Return active product category IDs for the current request.
     *
     * Why:
     * - On category archives, the queried category should be active.
     * - On single product pages, assigned product categories should activate the
     *   matching trail in the shop nav.
     *
     * @return array<int, int>
     */
    private static function getActiveCategoryIds(): array
    {
        $activeIds = [];
        $queriedObject = get_queried_object();

        if ($queriedObject instanceof WP_Term && $queriedObject->taxonomy === self::TAXONOMY) {
            $activeIds[] = (int) $queriedObject->term_id;
            $activeIds = array_merge($activeIds, self::getAncestorIds((int) $queriedObject->term_id));

            return self::normalizeIds($activeIds);
        }

        if (function_exists('is_product') && is_product()) {
            $productId = get_the_ID();

            if (! $productId) {
                return [];
            }

            $termIds = wp_get_post_terms((int) $productId, self::TAXONOMY, [
                'fields' => 'ids',
            ]);

            if (is_wp_error($termIds) || empty($termIds)) {
                return [];
            }

            foreach ($termIds as $termId) {
                $termId = (int) $termId;

                if ($termId <= 0) {
                    continue;
                }

                $activeIds[] = $termId;
                $activeIds = array_merge($activeIds, self::getAncestorIds($termId));
            }
        }

        return self::normalizeIds($activeIds);
    }

    /**
     * Get ancestor category IDs for a product category.
     *
     * @return array<int, int>
     */
    private static function getAncestorIds(int $termId): array
    {
        $ancestors = get_ancestors($termId, self::TAXONOMY, 'taxonomy');

        if (empty($ancestors)) {
            return [];
        }

        return array_map('intval', $ancestors);
    }

    /**
     * Normalize a list of IDs.
     *
     * @param array<int, mixed> $ids
     *
     * @return array<int, int>
     */
    private static function normalizeIds(array $ids): array
    {
        $ids = array_map('intval', $ids);
        $ids = array_filter($ids, static fn (int $id): bool => $id > 0);

        return array_values(array_unique($ids));
    }

    /**
     * Get the main WooCommerce shop URL.
     */
    private static function getShopUrl(): string
    {
        if (function_exists('wc_get_page_permalink')) {
            $shopUrl = wc_get_page_permalink('shop');

            if (is_string($shopUrl) && $shopUrl !== '') {
                return $shopUrl;
            }
        }

        $archiveUrl = get_post_type_archive_link('product');

        if (is_string($archiveUrl) && $archiveUrl !== '') {
            return $archiveUrl;
        }

        return home_url('/shop/');
    }
}

ShopNavigation::register();