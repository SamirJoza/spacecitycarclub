<?php

/**
 * File path + filename: app/Support/Woo/ProductMarkup.php
 *
 * Purpose:
 * - Apply the club merchandise markup percentage to WooCommerce product prices.
 * - Round final marked-up prices up to the next half-dollar increment.
 * - Use the editable markup percentage stored in Theme Settings via
 *   App\Options\ShopSettings.
 * - Support both simple products and variable product variations.
 *
 * Why this file exists:
 * - app/Options/ShopSettings.php only stores the markup percentage.
 * - Printify pushes products and variation prices into WooCommerce.
 * - WooCommerce variable product archive/single prices come from child variation
 *   prices, not just the parent product.
 * - Therefore, markup must be applied to simple products and each variation.
 *
 * Important:
 * - This file does not label anything as a donation.
 * - This file does not change checkout fees.
 * - This file does not change order totals after checkout.
 * - This file persists the adjusted WooCommerce product price.
 * - This file rounds the adjusted final price upward to the next .50 step so
 *   storefront prices stay clean and intentional.
 * - This file stores the detected base price in product meta so the markup does
 *   not compound every time Printify republishes the same product.
 *
 * Example:
 * - Printify pushes a variation at $40.00.
 * - Theme Settings markup is 25%.
 * - WooCommerce variation regular price becomes $50.00.
 * - Stored base price remains $40.00.
 * - If Printify republishes $40.00 again, the price stays $50.00, not $62.50.
 *
 * Rounding examples:
 * - A calculated final price of $28.26 becomes $28.50.
 * - A calculated final price of $28.52 becomes $29.00.
 */

declare(strict_types=1);

namespace App\Support\Woo;

use App\Options\ShopSettings;
use WC_Product;
use WC_Product_Variable;
use WC_Product_Variation;

class ProductMarkup
{
    /**
     * Product meta key that can be manually set to disable markup on one product.
     *
     * Why:
     * - Gives us an emergency escape hatch without changing code.
     * - Set this custom field to "1" on a product to skip markup.
     */
    private const DISABLE_META_KEY = '_sccc_disable_shop_markup';

    /**
     * Meta keys used to remember base/final prices.
     *
     * Why:
     * - Prevents repeated markup compounding.
     * - Lets future Printify price changes become the new base price.
     */
    private const BASE_REGULAR_META_KEY = '_sccc_markup_base_regular_price';
    private const FINAL_REGULAR_META_KEY = '_sccc_markup_final_regular_price';
    private const BASE_SALE_META_KEY = '_sccc_markup_base_sale_price';
    private const FINAL_SALE_META_KEY = '_sccc_markup_final_sale_price';

    /**
     * Final price rounding behavior.
     *
     * Why:
     * - Printify + markup math can produce odd-looking prices such as $28.26.
     * - For storefront polish, final marked-up prices should land on clean
     *   half-dollar steps.
     * - This intentionally rounds upward to the next .50 increment:
     *     28.00 => 28.00
     *     28.01 => 28.50
     *     28.26 => 28.50
     *     28.50 => 28.50
     *     28.52 => 29.00
     */
    private const ROUND_FINAL_PRICES_TO_HALF_DOLLAR = true;
    private const PRICE_ROUNDING_INCREMENT = 0.50;
    private const PRICE_ROUNDING_EPSILON = 0.000001;

    /**
     * General audit/meta keys.
     */
    private const MARKUP_PERCENT_META_KEY = '_sccc_markup_percent';
    private const MARKUP_MULTIPLIER_META_KEY = '_sccc_markup_multiplier';
    private const MARKUP_UPDATED_AT_META_KEY = '_sccc_markup_updated_at';

    /**
     * Product category slugs that should never receive the merch markup.
     *
     * Why:
     * - This protects membership/event/ticket products.
     * - Printify merchandise should still be marked up.
     *
     * Adjust this list later if your real category slugs differ.
     *
     * @var array<int, string>
     */
    private const EXCLUDED_CATEGORY_SLUGS = [
        'membership',
        'memberships',
        'membership-plan',
        'membership-plans',
        'events',
        'event',
        'tickets',
        'ticket',
    ];

    /**
     * Prevent duplicate hook registration.
     */
    private static bool $registered = false;

    /**
     * Prevent recursion when this class saves a product internally.
     */
    private static bool $internalSave = false;

    /**
     * Prevent parent sync loops.
     */
    private static bool $syncingParent = false;

    /**
     * Register WooCommerce hooks.
     */
    public static function register(): void
    {
        if (self::$registered) {
            return;
        }

        self::$registered = true;

        /*
         * Apply markup before WooCommerce saves the product object.
         *
         * Why:
         * - This catches normal admin saves.
         * - This also catches REST/API saves that use WooCommerce CRUD objects,
         *   which is the normal path for integrations.
         */
        add_action('woocommerce_before_product_object_save', [self::class, 'applyBeforeSave'], 20, 2);

        /*
         * Sync variable product parent prices after variation saves.
         *
         * Why:
         * - Variable product display prices come from child variations.
         * - After a variation price changes, the parent product needs to sync its
         *   min/max price data.
         */
        add_action('woocommerce_after_product_object_save', [self::class, 'afterProductSave'], 20, 2);
    }

    /**
     * Apply markup before WooCommerce persists a product object.
     *
     * @param mixed $product
     * @param mixed $dataStore
     */
    public static function applyBeforeSave($product, $dataStore): void
    {
        if (self::$internalSave) {
            return;
        }

        if (! $product instanceof WC_Product) {
            return;
        }

        self::applyMarkupToProduct($product);
    }

    /**
     * Handle parent syncing after WooCommerce saves products/variations.
     *
     * @param mixed $product
     * @param mixed $dataStore
     */
    public static function afterProductSave($product, $dataStore): void
    {
        if (self::$internalSave || self::$syncingParent) {
            return;
        }

        if (! $product instanceof WC_Product) {
            return;
        }

        /*
         * If a variation was saved, sync its variable parent.
         */
        if ($product instanceof WC_Product_Variation) {
            $parentId = (int) $product->get_parent_id();

            if ($parentId > 0) {
                self::syncVariableParent($parentId);
            }

            return;
        }

        /*
         * If the parent variable product was saved, make sure all existing child
         * variations receive markup too.
         *
         * Why:
         * Some integrations create/update the parent and then children.
         * Others update the parent after children already exist.
         * This covers the second case safely.
         */
        if ($product instanceof WC_Product_Variable || $product->is_type('variable')) {
            self::applyMarkupToExistingVariations($product);
        }
    }

    /**
     * Apply markup to all existing variations on a variable product.
     */
    private static function applyMarkupToExistingVariations(WC_Product $product): void
    {
        if (! method_exists($product, 'get_children')) {
            return;
        }

        $variationIds = $product->get_children();

        if (empty($variationIds)) {
            return;
        }

        $changedAnyVariation = false;

        foreach ($variationIds as $variationId) {
            $variation = wc_get_product((int) $variationId);

            if (! $variation instanceof WC_Product_Variation) {
                continue;
            }

            $changed = self::applyMarkupToProduct($variation);

            if (! $changed) {
                continue;
            }

            self::$internalSave = true;

            try {
                $variation->save();
                $changedAnyVariation = true;
            } finally {
                self::$internalSave = false;
            }
        }

        if ($changedAnyVariation) {
            self::syncVariableParent((int) $product->get_id());
        }
    }

    /**
     * Apply markup to one product or variation object.
     *
     * Returns true when the product object was changed.
     */
    private static function applyMarkupToProduct(WC_Product $product): bool
    {
        if (! self::shouldApplyMarkup($product)) {
            return false;
        }

        $multiplier = self::getMarkupMultiplier();
        $percent = self::getMarkupPercent();

        $changed = false;

        /*
         * Regular price.
         *
         * Why:
         * - Printify normally pushes the retail price as the product/variation
         *   price.
         * - We treat the incoming value as the base value unless it already
         *   matches our previously-applied final value.
         */
        $incomingRegularPrice = self::normalizePrice((string) $product->get_regular_price('edit'));
        $baseRegularPrice = self::resolveBasePrice(
            $product,
            self::BASE_REGULAR_META_KEY,
            self::FINAL_REGULAR_META_KEY,
            $incomingRegularPrice
        );

        $finalRegularPrice = '';

        if ($baseRegularPrice !== '') {
            $finalRegularPrice = self::calculateMarkedUpPrice($baseRegularPrice, $multiplier);

            if (! self::pricesEqual($incomingRegularPrice, $finalRegularPrice)) {
                $product->set_regular_price($finalRegularPrice);
                $changed = true;
            }

            self::updateMetaIfChanged($product, self::BASE_REGULAR_META_KEY, $baseRegularPrice);
            self::updateMetaIfChanged($product, self::FINAL_REGULAR_META_KEY, $finalRegularPrice);
        }

        /*
         * Sale price.
         *
         * Why:
         * - If Printify or an admin sets a sale price, keep the same markup logic.
         * - If there is no sale price, clear stored sale markup meta.
         */
        $incomingSalePrice = self::normalizePrice((string) $product->get_sale_price('edit'));

        if ($incomingSalePrice !== '') {
            $baseSalePrice = self::resolveBasePrice(
                $product,
                self::BASE_SALE_META_KEY,
                self::FINAL_SALE_META_KEY,
                $incomingSalePrice
            );

            $finalSalePrice = self::calculateMarkedUpPrice($baseSalePrice, $multiplier);

            if (! self::pricesEqual($incomingSalePrice, $finalSalePrice)) {
                $product->set_sale_price($finalSalePrice);
                $changed = true;
            }

            self::updateMetaIfChanged($product, self::BASE_SALE_META_KEY, $baseSalePrice);
            self::updateMetaIfChanged($product, self::FINAL_SALE_META_KEY, $finalSalePrice);
        } else {
            if ($product->get_meta(self::BASE_SALE_META_KEY, true) !== '') {
                $product->delete_meta_data(self::BASE_SALE_META_KEY);
                $changed = true;
            }

            if ($product->get_meta(self::FINAL_SALE_META_KEY, true) !== '') {
                $product->delete_meta_data(self::FINAL_SALE_META_KEY);
                $changed = true;
            }
        }

        /*
         * Active price.
         *
         * Why:
         * WooCommerce uses _price as the active displayed price. For sale items,
         * active price should be sale price. Otherwise, regular price.
         */
        $activePrice = self::normalizePrice((string) $product->get_price('edit'));
        $desiredActivePrice = $product->get_sale_price('edit') !== ''
            ? self::normalizePrice((string) $product->get_sale_price('edit'))
            : self::normalizePrice((string) $product->get_regular_price('edit'));

        if ($desiredActivePrice !== '' && ! self::pricesEqual($activePrice, $desiredActivePrice)) {
            $product->set_price($desiredActivePrice);
            $changed = true;
        }

        /*
         * Store audit data.
         */
        self::updateMetaIfChanged($product, self::MARKUP_PERCENT_META_KEY, (string) $percent);
        self::updateMetaIfChanged($product, self::MARKUP_MULTIPLIER_META_KEY, (string) $multiplier);
        self::updateMetaIfChanged($product, self::MARKUP_UPDATED_AT_META_KEY, gmdate('Y-m-d H:i:s'));

        return $changed;
    }

    /**
     * Decide whether markup should apply to this product.
     */
    private static function shouldApplyMarkup(WC_Product $product): bool
    {
        /*
         * Only simple products and variations get direct price changes.
         *
         * Why:
         * - Variable parent prices are synced from child variations.
         * - Grouped/external products have different price behavior.
         */
        if (! $product->is_type('simple') && ! $product instanceof WC_Product_Variation) {
            return false;
        }

        $productId = (int) $product->get_id();
        $parentId = $product instanceof WC_Product_Variation ? (int) $product->get_parent_id() : 0;
        $sourceProductId = $parentId > 0 ? $parentId : $productId;

        if ($sourceProductId <= 0) {
            return false;
        }

        /*
         * Manual escape hatch.
         */
        if (get_post_meta($sourceProductId, self::DISABLE_META_KEY, true) === '1') {
            return false;
        }

        if ($productId > 0 && get_post_meta($productId, self::DISABLE_META_KEY, true) === '1') {
            return false;
        }

        /*
         * Skip virtual/downloadable products.
         *
         * Why:
         * - Memberships, tickets, and digital items should not accidentally get a
         *   merchandise markup.
         * - Printify products are physical merchandise.
         */
        if ($product->is_virtual() || $product->is_downloadable()) {
            return false;
        }

        if ($parentId > 0) {
            $parentProduct = wc_get_product($parentId);

            if ($parentProduct instanceof WC_Product && ($parentProduct->is_virtual() || $parentProduct->is_downloadable())) {
                return false;
            }
        }

        /*
         * Skip protected product categories.
         */
        if (self::hasExcludedCategory($sourceProductId)) {
            return false;
        }

        return true;
    }

    /**
     * Check whether a product belongs to an excluded category.
     */
    private static function hasExcludedCategory(int $productId): bool
    {
        if ($productId <= 0) {
            return false;
        }

        $terms = get_the_terms($productId, 'product_cat');

        if (is_wp_error($terms) || empty($terms)) {
            return false;
        }

        foreach ($terms as $term) {
            if (! $term instanceof \WP_Term) {
                continue;
            }

            if (in_array((string) $term->slug, self::EXCLUDED_CATEGORY_SLUGS, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Resolve the base price from the incoming price and stored meta.
     *
     * Why this method matters:
     * - If incoming price equals our previously stored final price, then the
     *   product is simply being saved again. Use the stored base price.
     * - If incoming price differs from our stored final price, assume Printify or
     *   an admin changed the base price. Use the incoming value as the new base.
     */
    private static function resolveBasePrice(
        WC_Product $product,
        string $baseMetaKey,
        string $finalMetaKey,
        string $incomingPrice
    ): string {
        if ($incomingPrice === '') {
            return '';
        }

        $storedBasePrice = self::normalizePrice((string) $product->get_meta($baseMetaKey, true));
        $storedFinalPrice = self::normalizePrice((string) $product->get_meta($finalMetaKey, true));

        if (
            $storedBasePrice !== ''
            && $storedFinalPrice !== ''
            && self::pricesEqual($incomingPrice, $storedFinalPrice)
        ) {
            return $storedBasePrice;
        }

        return $incomingPrice;
    }

    /**
     * Calculate the marked-up final price.
     *
     * Why rounding happens here:
     * - Base prices should stay exact so Printify/admin price changes can still
     *   be detected correctly.
     * - Only the final marked-up storefront price should be rounded.
     */
    private static function calculateMarkedUpPrice(string $basePrice, float $multiplier): string
    {
        $base = (float) $basePrice;
        $final = $base * $multiplier;

        if (self::ROUND_FINAL_PRICES_TO_HALF_DOLLAR) {
            $final = self::roundUpToHalfDollar($final);
        }

        return self::normalizePrice((string) $final);
    }

    /**
     * Round a positive price upward to the next half-dollar increment.
     *
     * Why this is not normal nearest-value rounding:
     * - Your examples map 28.26 => 28.50 and 28.52 => 29.00.
     * - That means the desired behavior is a ceiling to the next .50 step, not
     *   nearest-half rounding.
     *
     * The tiny epsilon prevents values already sitting on a clean boundary, such
     * as 28.50, from floating-point drift rounding up to 29.00.
     */
    private static function roundUpToHalfDollar(float $price): float
    {
        if ($price <= 0.0) {
            return $price;
        }

        $increment = self::PRICE_ROUNDING_INCREMENT;
        $scaledPrice = $price / $increment;
        $roundedScaledPrice = ceil($scaledPrice - self::PRICE_ROUNDING_EPSILON);

        return $roundedScaledPrice * $increment;
    }

    /**
     * Normalize a WooCommerce price string.
     */
    private static function normalizePrice(string $price): string
    {
        $price = trim($price);

        if ($price === '' || ! is_numeric($price)) {
            return '';
        }

        if (function_exists('wc_format_decimal')) {
            return wc_format_decimal($price, self::priceDecimals());
        }

        return number_format((float) $price, self::priceDecimals(), '.', '');
    }

    /**
     * Compare two normalized prices.
     */
    private static function pricesEqual(string $left, string $right): bool
    {
        $left = self::normalizePrice($left);
        $right = self::normalizePrice($right);

        if ($left === '' || $right === '') {
            return $left === $right;
        }

        $epsilon = 1 / (10 ** self::priceDecimals());

        return abs((float) $left - (float) $right) < $epsilon;
    }

    /**
     * Return WooCommerce price decimals.
     */
    private static function priceDecimals(): int
    {
        if (function_exists('wc_get_price_decimals')) {
            return (int) wc_get_price_decimals();
        }

        return 2;
    }

    /**
     * Update product meta only when the value changed.
     */
    private static function updateMetaIfChanged(WC_Product $product, string $key, string $value): void
    {
        $existingValue = (string) $product->get_meta($key, true);

        if ($existingValue === $value) {
            return;
        }

        $product->update_meta_data($key, $value);
    }

    /**
     * Sync a variable product parent after variation prices change.
     */
    private static function syncVariableParent(int $parentId): void
    {
        if ($parentId <= 0 || ! class_exists(WC_Product_Variable::class)) {
            return;
        }

        self::$syncingParent = true;

        try {
            WC_Product_Variable::sync($parentId, true);

            if (function_exists('wc_delete_product_transients')) {
                wc_delete_product_transients($parentId);
            }
        } finally {
            self::$syncingParent = false;
        }
    }

    /**
     * Get markup percentage from Theme Settings.
     */
    private static function getMarkupPercent(): float
    {
        if (class_exists(ShopSettings::class)) {
            return ShopSettings::getMarkupPercent();
        }

        return 25.0;
    }

    /**
     * Get markup multiplier from Theme Settings.
     */
    private static function getMarkupMultiplier(): float
    {
        if (class_exists(ShopSettings::class)) {
            return ShopSettings::getMarkupMultiplier();
        }

        return 1.25;
    }
}

ProductMarkup::register();