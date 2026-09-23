<?php
/**
 * File path + filename: app/Options/ShopSettings.php
 *
 * Purpose:
 * - Register the Shop Settings child page under Theme Settings.
 * - Store SCCC-specific shop values that should be editable by admins.
 *
 * Why this file exists:
 * - Printify will push merchandise products into WooCommerce.
 * - Those pushed prices may already include the vendor/creative cost recovery.
 * - The club also needs a separate editable markup percentage that can later be
 *   used by the WooCommerce pricing layer.
 * - Keeping this value in Theme Settings avoids hardcoding the markup in PHP.
 *
 * Important notes:
 * - This file only registers the setting and provides helper methods for reading
 *   it later.
 * - This file does not change WooCommerce product prices yet.
 * - Future pricing code should use ShopSettings::getMarkupPercent() or
 *   ShopSettings::getMarkupMultiplier() instead of reading the ACF field directly.
 */

declare(strict_types=1);

namespace App\Options;

use Log1x\AcfComposer\Builder;
use Log1x\AcfComposer\Options as Field;

class ShopSettings extends Field
{
    /**
     * ACF option field name used for the club markup percentage.
     *
     * Why this is a constant:
     * - Future pricing code can reference one source of truth.
     * - It avoids mismatched field names when we build the WooCommerce price layer.
     */
    public const MARKUP_FIELD = 'sccc_shop_markup_percent';

    /**
     * Default markup percentage.
     *
     * Why 25:
     * - This matches the current club decision.
     * - A saved admin value can override it later.
     */
    public const DEFAULT_MARKUP_PERCENT = 25.0;

    /**
     * The child page menu label.
     *
     * @var string
     */
    public $name = 'Shop Settings';

    /**
     * The child page document title.
     *
     * @var string
     */
    public $title = 'Shop Settings | Theme Settings';

    /**
     * Stable child page slug.
     *
     * Why this matters:
     * - ThemeSettings.php uses this slug in the shared left-side navigation.
     * - Existing admin links should not break later.
     *
     * @var string
     */
    public $slug = 'theme-settings-shop-settings';

    /**
     * Attach this page under the Theme Settings parent page.
     *
     * @var string
     */
    public $parent = 'theme-settings';

    /**
     * The option page field group.
     *
     * @return array
     */
    public function fields(): array
    {
        $fields = Builder::make('shop_settings');

        $fields
            ->addNumber(self::MARKUP_FIELD, [
                'label' => 'Club Merchandise Markup Percentage',
                'instructions' => 'Default is 25%. This percentage will later be used to calculate the club markup on top of the stored base merchandise price. This is not displayed as a charitable donation.',
                'default_value' => self::DEFAULT_MARKUP_PERCENT,
                'min' => 0,
                'max' => 100,
                'step' => 0.01,
                'append' => '%',
                'required' => 1,
                'wrapper' => [
                    'width' => '33',
                ],
            ]);

        return $fields->build();
    }

    /**
     * Read the saved markup percentage from Theme Settings.
     *
     * Why this helper exists:
     * - Future WooCommerce pricing code should not need to know the ACF field name.
     * - We can safely normalize the value in one place.
     * - If ACF is unavailable for any reason, pricing code can still fall back to
     *   the current default instead of breaking.
     */
    public static function getMarkupPercent(): float
    {
        if (! \function_exists('get_field')) {
            return self::DEFAULT_MARKUP_PERCENT;
        }

        $value = \get_field(self::MARKUP_FIELD, 'option');

        if ($value === null || $value === '' || ! is_numeric($value)) {
            return self::DEFAULT_MARKUP_PERCENT;
        }

        /**
         * The ACF field itself limits the UI to 0–100, but this clamp protects us
         * if a value is changed directly in the database or imported incorrectly.
         */
        $percent = round((float) $value, 2);

        return max(0.0, min(100.0, $percent));
    }

    /**
     * Return the saved markup as a price multiplier.
     *
     * Example:
     * - 25% becomes 1.25.
     * - 30% becomes 1.30.
     *
     * Why this helper exists:
     * - Future pricing code can calculate the final price cleanly from the stored
     *   Printify/WooCommerce base price.
     */
    public static function getMarkupMultiplier(): float
    {
        return 1 + (self::getMarkupPercent() / 100);
    }
}