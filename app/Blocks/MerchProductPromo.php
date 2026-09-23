<?php

/**
 * ============================================================================
 * File path + filename: app/Blocks/MerchProductPromo.php
 * ============================================================================
 *
 * Purpose:
 * - Register a dynamic ACF Composer block for a sidebar-style merch/product
 *   promo card that mirrors the provided "Repping the Club?" widget layout.
 *
 * Why this file exists:
 * - The user wants a reusable Gutenberg block that keeps the exact visual
 *   pattern of the merch sidebar card, but makes key content editable:
 *   - headline
 *   - supporting copy
 *   - selected WooCommerce product
 *   - button text
 *   - button link override
 * - Product data should remain authoritative in WooCommerce:
 *   - product image
 *   - product name
 *   - product price
 *
 * Troubleshooting note:
 * - This block uses `auto` mode so the editor can more easily switch between
 *   preview and edit behavior inside Gutenberg.
 *
 * EventPress filtering note:
 * - Mage EventPress creates hidden linked WooCommerce products for events.
 * - Those linked event products store the meta key `link_mep_event`.
 * - We exclude those products from this block’s product selector so only
 *   normal merch/products appear in the editor dropdown.
 *
 * Implementation notes:
 * - This block uses ACF Composer inside the Sage theme, matching the existing
 *   project convention for Gutenberg blocks.
 * - The product selector uses an ACF Post Object field filtered to WooCommerce
 *   products and returns a product ID for predictable handling.
 * - The block is server-rendered and passes a compact view model into Blade.
 *
 * Styling note:
 * - Styles are intentionally kept inside the Blade view so this block does not
 *   add weight or side effects to the main stylesheet bundle.
 */

namespace App\Blocks;

use Log1x\AcfComposer\Block;
use Log1x\AcfComposer\Builder;
use Log1x\AcfComposer\AcfComposer;
use WC_Product;
use WP_Post;

class MerchProductPromo extends Block
{
    /**
     * Block display name.
     *
     * @var string
     */
    public $name = 'Merch Product Promo';

    /**
     * Block description shown in the editor.
     *
     * @var string
     */
    public $description = 'A sidebar merch promo card with editable copy and a selected WooCommerce product.';

    /**
     * Explicit slug for predictable view naming.
     *
     * @var string
     */
    public $slug = 'merch-product-promo';

    /**
     * Gutenberg category.
     *
     * @var string
     */
    public $category = 'widgets';

    /**
     * Block icon.
     *
     * @var string|array
     */
    public $icon = 'cart';

    /**
     * Search keywords in block inserter.
     *
     * @var array<int, string>
     */
    public $keywords = ['merch', 'product', 'shop', 'woocommerce', 'sidebar'];

    /**
     * Blade view used to render the block.
     *
     * @var string
     */
    public $view = 'blocks.merch-product-promo';

    /**
     * Default editor mode.
     *
     * Why auto:
     * - ACF supports `auto`, `preview`, and `edit`.
     * - `auto` is the best fit here because the card still previews nicely,
     *   but clicking the block gives the editor a clearer path to the form.
     *
     * @var string
     */
    public $mode = 'auto';

    /**
     * Block support flags.
     *
     * Why these settings:
     * - This is intended to be a fluid sidebar-friendly card.
     * - Anchor support is useful.
     * - Multiple usage is allowed.
     * - We intentionally do NOT disable mode here, so the editor can still
     *   access the block’s edit/preview behavior when needed.
     *
     * @var array<string, mixed>
     */
    public $supports = [
        'align' => false,
        'align_text' => false,
        'align_content' => false,
        'anchor' => true,
        'multiple' => true,
        'jsx' => false,
    ];

    /**
     * Create a new block instance.
     *
     * Why this constructor exists:
     * - ACF Composer composers are container-resolved.
     * - We keep the parent constructor intact and register our field-level
     *   selector filter during block bootstrap.
     *
     * @param  \Log1x\AcfComposer\AcfComposer  $composer
     * @return void
     */
    public function __construct(AcfComposer $composer)
    {
        parent::__construct($composer);

        /**
         * Restrict the ACF Post Object selector for this field name so
         * EventPress-linked hidden event products do not appear.
         *
         * Field-specific ACF query filter:
         * - Targets only Post Object fields named `product_id`.
         * - Leaves unrelated Post Object fields alone.
         */
        add_filter(
            'acf/fields/post_object/query/name=product_id',
            [$this, 'filterProductSelectorQuery'],
            10,
            3
        );
    }

    /**
     * Filter the editor query for the selected WooCommerce product field.
     *
     * Why this filter exists:
     * - Mage EventPress creates linked hidden WooCommerce products for events.
     * - Those should not be selectable in this merch block.
     * - We exclude products that have the `link_mep_event` meta key.
     *
     * @param  array              $args
     * @param  array<string,mixed> $field
     * @param  int|string         $postId
     * @return array<string,mixed>
     */
    public function filterProductSelectorQuery(array $args, array $field, int|string $postId): array
    {
        $metaQuery = $args['meta_query'] ?? [];

        if (! is_array($metaQuery)) {
            $metaQuery = [];
        }

        /**
         * Ensure meta query clauses combine cleanly with any future editor-side
         * query args ACF may already provide.
         */
        if (! isset($metaQuery['relation'])) {
            $metaQuery['relation'] = 'AND';
        }

        /**
         * Keep only products that are not linked to a Mage EventPress event.
         * Normal merch/products do not have this meta key at all.
         */
        $metaQuery[] = [
            'key' => 'link_mep_event',
            'compare' => 'NOT EXISTS',
        ];

        $args['post_type'] = ['product'];
        $args['post_status'] = ['publish'];
        $args['meta_query'] = $metaQuery;

        return $args;
    }

    /**
     * Data passed into the Blade view.
     *
     * @return array<string, mixed>
     */
    public function with(): array
    {
        $headline = $this->stringField('headline', 'Repping the Club?');
        $copy = $this->stringField('copy', "Get the limited edition club merch before it's gone.");
        $buttonText = $this->stringField('button_text', 'Shop Merch');
        $buttonUrlOverride = $this->stringField('button_url', '');
        $product = $this->selectedProduct($this->field('product_id'));

        return [
            'headline' => $headline,
            'copy' => $copy,
            'buttonText' => $buttonText,
            'card' => $product ? $this->mapProductForView($product, $buttonUrlOverride, $buttonText) : null,
            'isPreview' => (bool) $this->preview,
        ];
    }

    /**
     * Block field group.
     *
     * @return array<string, mixed>
     */
    public function fields(): array
    {
        $fields = Builder::make('merch_product_promo');

        $fields
            ->addText('headline', [
                'label' => 'Headline',
                'default_value' => 'Repping the Club?',
                'required' => 0,
                'wrapper' => [
                    'width' => '50',
                ],
            ])
            ->addText('button_text', [
                'label' => 'Button Text',
                'default_value' => 'Shop Merch',
                'required' => 0,
                'wrapper' => [
                    'width' => '50',
                ],
            ])
            ->addTextarea('copy', [
                'label' => 'Body Copy',
                'default_value' => "Get the limited edition club merch before it's gone.",
                'rows' => 3,
                'new_lines' => 'br',
                'required' => 0,
            ])
            ->addPostObject('product_id', [
                'label' => 'WooCommerce Product',
                'instructions' => 'Select the WooCommerce product to feature in this card. EventPress-linked event products are excluded automatically.',
                'post_type' => ['product'],
                'post_status' => ['publish'],
                'return_format' => 'id',
                'ui' => 1,
                'required' => 1,
            ])
            ->addUrl('button_url', [
                'label' => 'Button Link Override',
                'instructions' => 'Optional. Leave empty to link the button to the selected product page.',
                'required' => 0,
            ]);

        return $fields->build();
    }

    /**
     * Normalize a field value into a trimmed string.
     *
     * @param  string  $key
     * @param  string  $default
     * @return string
     */
    protected function stringField(string $key, string $default = ''): string
    {
        $value = $this->field($key);

        if (! is_string($value)) {
            return $default;
        }

        $value = trim($value);

        return $value !== '' ? $value : $default;
    }

    /**
     * Safe wrapper around get_field().
     *
     * @param  string  $key
     * @return mixed
     */
    protected function field(string $key): mixed
    {
        return function_exists('get_field') ? get_field($key) : null;
    }

    /**
     * Resolve the selected WooCommerce product from the ACF field.
     *
     * @param  mixed  $value
     * @return \WC_Product|null
     */
    protected function selectedProduct(mixed $value): ?WC_Product
    {
        if (! function_exists('wc_get_product')) {
            return null;
        }

        $productId = $this->normalizeProductId($value);

        if (! $productId) {
            return null;
        }

        $product = wc_get_product($productId);

        if (! $product instanceof WC_Product) {
            return null;
        }

        return $product;
    }

    /**
     * Convert possible ACF return values into a product ID.
     *
     * @param  mixed  $value
     * @return int
     */
    protected function normalizeProductId(mixed $value): int
    {
        if (is_numeric($value)) {
            return (int) $value;
        }

        if ($value instanceof WP_Post) {
            return (int) $value->ID;
        }

        if (is_object($value) && isset($value->ID) && is_numeric($value->ID)) {
            return (int) $value->ID;
        }

        return 0;
    }

    /**
     * Build a small, Blade-friendly product view model.
     *
     * @param  \WC_Product  $product
     * @param  string       $buttonUrlOverride
     * @param  string       $buttonText
     * @return array<string, mixed>
     */
    protected function mapProductForView(WC_Product $product, string $buttonUrlOverride, string $buttonText): array
    {
        $imageId = (int) $product->get_image_id();
        $imageUrl = $imageId ? wp_get_attachment_image_url($imageId, 'woocommerce_thumbnail') : '';
        $buttonUrl = trim($buttonUrlOverride) !== '' ? $buttonUrlOverride : $product->get_permalink();
        $priceHtml = $product->get_price_html();

        if ($priceHtml === '' && $product->get_price() !== '') {
            $priceHtml = wc_price((float) $product->get_price());
        }

        return [
            'id' => $product->get_id(),
            'title' => $product->get_name(),
            'priceHtml' => $priceHtml,
            'imageUrl' => $imageUrl ?: '',
            'buttonUrl' => $buttonUrl,
            'buttonText' => $buttonText,
        ];
    }
}