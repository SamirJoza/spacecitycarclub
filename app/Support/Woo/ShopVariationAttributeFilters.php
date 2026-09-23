<?php

/**
 * File path + filename: app/Support/Woo/ShopVariationAttributeFilters.php
 *
 * Purpose:
 * - Support shop archive filtering for Printify variation attributes.
 * - Filter WooCommerce product archives by custom SCCC query vars:
 *   - sccc_filter_colors
 *   - sccc_filter_sizes
 *
 * Why this file exists:
 * - Printify can push real WooCommerce variable products while Colors/Sizes are
 *   stored on the child variations as post meta.
 * - WooCommerce's built-in layered navigation works best with global attributes
 *   such as pa_colors / pa_sizes.
 * - This class is a fallback for variation meta attributes such as:
 *   - attribute_colors
 *   - attribute_color
 *   - attribute_pa_colors
 *   - attribute_pa_color
 *   - attribute_sizes
 *   - attribute_size
 *   - attribute_pa_sizes
 *   - attribute_pa_size
 *
 * Current update:
 * - Supports Amazon-style checkbox form submissions:
 *   - sccc_filter_colors[]=black&sccc_filter_colors[]=red
 * - Still supports comma-style URLs:
 *   - sccc_filter_colors=black,red
 * - Registers the custom query vars so WordPress accepts them publicly.
 * - Filters the main WooCommerce archive query before the product loop runs.
 *
 * Important:
 * - This file does not change prices.
 * - This file does not alter membership visibility.
 * - This file does not change product category routing.
 * - This file only modifies the main WooCommerce product archive query when one
 *   of the custom SCCC variation filter query vars is present.
 */

declare(strict_types=1);

namespace App\Support\Woo;

use WP_Query;

class ShopVariationAttributeFilters
{
    /**
     * Supported variation filter groups.
     *
     * Why:
     * - Printify uses plural names like Colors and Sizes.
     * - WooCommerce variation meta keys can be singular, plural, global, or
     *   non-global depending on how the product was imported.
     *
     * @var array<string, array<string, mixed>>
     */
    private const GROUPS = [
        'colors' => [
            'query_var' => 'sccc_filter_colors',
            'meta_keys' => [
                'attribute_colors',
                'attribute_color',
                'attribute_pa_colors',
                'attribute_pa_color',
            ],
        ],
        'sizes' => [
            'query_var' => 'sccc_filter_sizes',
            'meta_keys' => [
                'attribute_sizes',
                'attribute_size',
                'attribute_pa_sizes',
                'attribute_pa_size',
            ],
        ],
    ];

    /**
     * Prevent duplicate hook registration.
     */
    private static bool $registered = false;

    /**
     * Register WordPress hooks.
     */
    public static function register(): void
    {
        if (self::$registered) {
            return;
        }

        self::$registered = true;

        /*
         * Register custom public query vars.
         *
         * Why:
         * WordPress needs to know that sccc_filter_colors and sccc_filter_sizes
         * are allowed query vars. This helps prevent routing/canonical behavior
         * from treating the URL as invalid.
         */
        add_filter('query_vars', [self::class, 'registerQueryVars']);

        /*
         * Modify the main product archive query before it runs.
         *
         * Why:
         * The Blade template renders after the main query is already prepared,
         * so filtering must happen here instead of inside the template.
         */
        add_action('pre_get_posts', [self::class, 'filterMainProductQuery'], 35);
    }

    /**
     * Register SCCC filter query vars with WordPress.
     *
     * @param array<int, string> $queryVars
     * @return array<int, string>
     */
    public static function registerQueryVars(array $queryVars): array
    {
        foreach (self::GROUPS as $groupConfig) {
            $queryVar = isset($groupConfig['query_var'])
                ? (string) $groupConfig['query_var']
                : '';

            if ($queryVar === '') {
                continue;
            }

            if (! in_array($queryVar, $queryVars, true)) {
                $queryVars[] = $queryVar;
            }
        }

        return $queryVars;
    }

    /**
     * Filter the main WooCommerce product archive query.
     */
    public static function filterMainProductQuery(WP_Query $query): void
    {
        if (is_admin() || ! $query->is_main_query()) {
            return;
        }

        if (! self::isProductQuery($query)) {
            return;
        }

        $selectedGroups = [];

        foreach (self::GROUPS as $group => $config) {
            $queryVar = isset($config['query_var'])
                ? (string) $config['query_var']
                : '';

            if ($queryVar === '') {
                continue;
            }

            $selectedValues = self::selectedValues($queryVar);

            if (empty($selectedValues)) {
                continue;
            }

            $selectedGroups[$group] = $selectedValues;
        }

        if (empty($selectedGroups)) {
            return;
        }

        /*
         * Build the matching product parent IDs.
         *
         * Why:
         * - Each selected group returns parent product IDs from matching child
         *   variations.
         * - Multiple groups should narrow results, so Colors + Sizes become an
         *   intersection.
         */
        $matchingParentIds = null;

        foreach ($selectedGroups as $group => $selectedValues) {
            $groupMatches = self::parentProductIdsForSelection($group, $selectedValues);

            if ($matchingParentIds === null) {
                $matchingParentIds = $groupMatches;
                continue;
            }

            $matchingParentIds = array_values(array_intersect($matchingParentIds, $groupMatches));
        }

        if ($matchingParentIds === null) {
            return;
        }

        $matchingParentIds = array_values(array_unique(array_map('intval', $matchingParentIds)));

        /*
         * No matches.
         *
         * Why:
         * Setting post__in to [0] keeps WooCommerce on the archive template
         * while returning no matching products.
         */
        if (empty($matchingParentIds)) {
            $query->set('post__in', [0]);

            return;
        }

        /*
         * Preserve any existing post__in narrowing.
         *
         * Why:
         * Another plugin or WooCommerce itself may already have restricted the
         * query. We intersect with it instead of replacing it blindly.
         */
        $existingPostIn = $query->get('post__in');

        if (is_array($existingPostIn) && ! empty($existingPostIn)) {
            $matchingParentIds = array_values(array_intersect(
                array_map('intval', $existingPostIn),
                $matchingParentIds
            ));

            if (empty($matchingParentIds)) {
                $query->set('post__in', [0]);

                return;
            }
        }

        $query->set('post__in', $matchingParentIds);
    }

    /**
     * Determine whether this query is a WooCommerce product archive query.
     */
    private static function isProductQuery(WP_Query $query): bool
    {
        if ($query->is_post_type_archive('product')) {
            return true;
        }

        if ($query->is_tax(['product_cat', 'product_tag'])) {
            return true;
        }

        $postType = $query->get('post_type');

        if ($postType === 'product') {
            return true;
        }

        if (is_array($postType) && in_array('product', $postType, true)) {
            return true;
        }

        return false;
    }

    /**
     * Read selected values from the URL.
     *
     * Supports:
     * - sccc_filter_colors=black,red
     * - sccc_filter_colors[]=black&sccc_filter_colors[]=red
     *
     * @return array<int, string>
     */
    private static function selectedValues(string $queryVar): array
    {
        if (! isset($_GET[$queryVar])) {
            return [];
        }

        $rawValue = $_GET[$queryVar];

        if (is_array($rawValue)) {
            $values = array_map(static function ($value): string {
                return sanitize_title(sanitize_text_field(wp_unslash((string) $value)));
            }, $rawValue);
        } else {
            $rawString = sanitize_text_field(wp_unslash((string) $rawValue));

            if ($rawString === '') {
                return [];
            }

            $values = array_map('sanitize_title', explode(',', $rawString));
        }

        $values = array_filter($values, static fn ($value): bool => $value !== '');

        return array_values(array_unique($values));
    }

    /**
     * Return parent product IDs matching selected variation values.
     *
     * @param array<int, string> $selectedValues
     * @return array<int, int>
     */
    private static function parentProductIdsForSelection(string $group, array $selectedValues): array
    {
        if (! isset(self::GROUPS[$group])) {
            return [];
        }

        $selectedValues = array_values(array_unique(array_filter(array_map(
            'sanitize_title',
            $selectedValues
        ))));

        if (empty($selectedValues)) {
            return [];
        }

        $rows = self::variationAttributeRows($group);

        if (empty($rows)) {
            return [];
        }

        $parentIds = [];

        foreach ($rows as $row) {
            $rawValue = isset($row->meta_value)
                ? trim((string) $row->meta_value)
                : '';

            if ($rawValue === '') {
                continue;
            }

            $valueSlug = sanitize_title($rawValue);

            if ($valueSlug === '' || ! in_array($valueSlug, $selectedValues, true)) {
                continue;
            }

            $parentId = isset($row->post_parent)
                ? (int) $row->post_parent
                : 0;

            if ($parentId > 0) {
                $parentIds[] = $parentId;
            }
        }

        return array_values(array_unique($parentIds));
    }

    /**
     * Query variation rows for one filter group.
     *
     * @return array<int, object>
     */
    private static function variationAttributeRows(string $group): array
    {
        global $wpdb;

        if (! isset(self::GROUPS[$group])) {
            return [];
        }

        $metaKeys = isset(self::GROUPS[$group]['meta_keys']) && is_array(self::GROUPS[$group]['meta_keys'])
            ? self::GROUPS[$group]['meta_keys']
            : [];

        $metaKeys = array_values(array_unique(array_filter(array_map(
            'sanitize_key',
            $metaKeys
        ))));

        if (empty($metaKeys)) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($metaKeys), '%s'));

        $sql = $wpdb->prepare(
            "
            SELECT
                p.post_parent,
                m.meta_key,
                m.meta_value
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} m
                ON p.ID = m.post_id
            WHERE p.post_type = 'product_variation'
                AND p.post_status IN ('publish', 'private')
                AND p.post_parent > 0
                AND m.meta_key IN ({$placeholders})
                AND m.meta_value <> ''
            ",
            $metaKeys
        );

        $rows = $wpdb->get_results($sql);

        return is_array($rows) ? $rows : [];
    }
}

ShopVariationAttributeFilters::register();