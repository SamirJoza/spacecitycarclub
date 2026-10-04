<?php

/**
 * File path + filename: app/Support/Woo/ProductSwatches.php
 *
 * Purpose:
 * - Gives every colour option on a product page the right colour dot.
 * - Works from the colours attached to the product itself (global attributes
 *   or the custom attributes Printify creates), so only those colours are sent
 *   to the page.
 *
 * Where a colour comes from, in order:
 *   1. A colour picked by hand in the product's "Color dots" box. It is saved
 *      site-wide by colour name, so fixing "Azalea" once fixes every product.
 *   2. The built-in palette of common garment colours (Gildan, Bella+Canvas,
 *      Comfort Colors names that Printify uses).
 *   3. A best guess from the words in the name ("Heather Navy" → navy).
 *   4. Nothing: the page shows a neutral outlined dot.
 *
 * Two-tone names such as "Black/White" get a split dot.
 */

namespace App\Support\Woo;

defined('ABSPATH') || exit;

final class ProductSwatches
{
    public const OPTION = 'sccc_swatch_colors';
    private const NONCE = 'sccc_swatch_colors_nonce';

    public static function register(): void
    {
        add_action('add_meta_boxes_product', [self::class, 'addMetaBox']);
        add_action('save_post_product', [self::class, 'save']);
        add_action('admin_enqueue_scripts', [self::class, 'enqueue']);
    }

    /**
     * Colour dots for one product: [slug => CSS background].
     * Keys are slugs of both the option value and its label.
     *
     * @return array<string, string>
     */
    public static function forProduct($product): array
    {
        $map = [];

        foreach (self::colorOptions($product) as $option) {
            $css = self::resolve($option['label']);

            if ($css === '' && $option['value'] !== $option['label']) {
                $css = self::resolve($option['value']);
            }

            if ($css === '') {
                continue;
            }

            $map[self::slug($option['value'])] = $css;
            $map[self::slug($option['label'])] = $css;
        }

        return (array) apply_filters('sccc_product_swatches', $map, $product);
    }

    /**
     * The colour options attached to a product.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function colorOptions($product): array
    {
        if (! $product instanceof \WC_Product) {
            return [];
        }

        $options = [];

        foreach ($product->get_attributes() as $attribute) {
            if (! $attribute instanceof \WC_Product_Attribute) {
                continue;
            }

            $name = (string) $attribute->get_name();
            $label = (string) wc_attribute_label($name, $product);

            if (! preg_match('/colou?r/i', $name.' '.$label)) {
                continue;
            }

            if ($attribute->is_taxonomy()) {
                foreach ((array) $attribute->get_terms() as $term) {
                    if ($term instanceof \WP_Term) {
                        $options[] = ['value' => (string) $term->slug, 'label' => (string) $term->name];
                    }
                }

                continue;
            }

            foreach ((array) $attribute->get_options() as $value) {
                $value = trim((string) $value);

                if ($value !== '') {
                    $options[] = ['value' => $value, 'label' => $value];
                }
            }
        }

        return $options;
    }

    /** CSS background for a colour name, or '' when unknown. */
    public static function resolve(string $name): string
    {
        $parts = preg_split('#\s*(?:/|\+|&| and )\s*#i', $name) ?: [];
        $parts = array_values(array_filter(array_map('trim', $parts), 'strlen'));

        // A saved or known colour for the whole name wins ("Black/White" picked by hand).
        $whole = self::hex($name, count($parts) < 2);

        if ($whole !== '' || count($parts) < 2) {
            return $whole;
        }

        $first = self::hex($parts[0], true);
        $second = self::hex($parts[1], true);

        if ($first === '' || $second === '') {
            return $first !== '' ? $first : $second;
        }

        return sprintf('linear-gradient(135deg, %1$s 0 50%%, %2$s 50%% 100%%)', $first, $second);
    }

    /** Hex for one colour name: saved → palette → (optionally) a guess from its words. */
    private static function hex(string $name, bool $guess): string
    {
        $slug = self::slug($name);

        if ($slug === '') {
            return '';
        }

        $saved = self::saved();

        if (isset($saved[$slug])) {
            return $saved[$slug];
        }

        $palette = self::palette();

        if (isset($palette[$slug])) {
            return $palette[$slug];
        }

        if (! $guess) {
            return '';
        }

        $words = explode('-', $slug);

        // "Safety Green", "Neon Pink": highlighter colours.
        if (array_intersect($words, ['safety', 'neon', 'highlighter', 'fluorescent', 'hi', 'vis'])) {
            foreach ($words as $word) {
                if (isset(self::NEON[$word])) {
                    return self::NEON[$word];
                }
            }
        }

        // Longest run of words we know ("Heather Royal Blue" → royal-blue), then
        // let the remaining words lighten, darken or mute it.
        $n = count($words);

        for ($length = $n - 1; $length >= 1; $length--) {
            for ($start = $n - $length; $start >= 0; $start--) {
                $try = implode('-', array_slice($words, $start, $length));

                if (! isset($palette[$try])) {
                    continue;
                }

                $rest = array_merge(array_slice($words, 0, $start), array_slice($words, $start + $length));

                return self::adjust($palette[$try], $rest);
            }
        }

        return '';
    }

    private const NEON = [
        'green' => '#ccff00', 'yellow' => '#f2ff00', 'lime' => '#ccff00', 'orange' => '#ff6a00',
        'pink' => '#ff3eb5', 'red' => '#ff2d3a', 'blue' => '#00b7ff', 'purple' => '#b026ff',
    ];

    /** Shift a colour by the descriptive words around it ("light", "dark", "heather"…). */
    private static function adjust(string $hex, array $words): string
    {
        $mixes = [
            '#ffffff' => [['light', 'pale', 'pastel', 'soft', 'baby', 'ice', 'powder'], 0.45],
            '#000000' => [['dark', 'deep', 'midnight', 'night'], 0.35],
            '#9a9c9f' => [['heather', 'heathered', 'dusty', 'vintage', 'faded', 'antique', 'washed', 'stone', 'triblend'], 0.22],
        ];

        foreach ($mixes as $with => [$triggers, $amount]) {
            if (array_intersect($words, $triggers)) {
                $hex = self::mix($hex, $with, $amount);
            }
        }

        return $hex;
    }

    private static function mix(string $a, string $b, float $amount): string
    {
        $out = '#';

        for ($i = 1; $i < 7; $i += 2) {
            $out .= sprintf('%02x', (int) round(hexdec(substr($a, $i, 2)) * (1 - $amount) + hexdec(substr($b, $i, 2)) * $amount));
        }

        return $out;
    }

    /** @return array<string, string> slug => hex, picked by hand */
    public static function saved(): array
    {
        $saved = get_option(self::OPTION, []);

        return is_array($saved) ? $saved : [];
    }

    /** Same rules as slugify() in content-single-product.blade.php. */
    public static function slug(string $value): string
    {
        $value = strtolower(trim(html_entity_decode($value, ENT_QUOTES, 'UTF-8')));
        $value = str_replace('&', 'and', $value);

        return trim((string) preg_replace('/[^a-z0-9]+/', '-', $value), '-');
    }

    /* ---------------------------------------------------------------------
     * Admin: "Color dots" box on the product edit screen
     * ------------------------------------------------------------------- */

    public static function addMetaBox(): void
    {
        add_meta_box('sccc_swatch_colors', __('Color dots', 'sccc'), [self::class, 'renderMetaBox'], 'product', 'side', 'default');
    }

    public static function enqueue(string $hook): void
    {
        if (! in_array($hook, ['post.php', 'post-new.php'], true) || get_post_type() !== 'product') {
            return;
        }

        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');
        wp_add_inline_script('wp-color-picker', 'jQuery(function($){$(".sccc-swatch-picker").wpColorPicker();});');
    }

    public static function renderMetaBox(\WP_Post $post): void
    {
        $options = self::colorOptions(wc_get_product($post->ID));

        if (! $options) {
            echo '<p>'.esc_html__('This product has no color attribute yet. Colors show up here once the product has one (save the product after adding it).', 'sccc').'</p>';

            return;
        }

        wp_nonce_field(self::NONCE, self::NONCE);
        $saved = self::saved();

        echo '<p class="description">'.esc_html__('The dot shown next to each color on the product page. Leave a field empty to use the automatic color. A color you pick applies to every product with that color name.', 'sccc').'</p>';

        foreach ($options as $option) {
            $slug = self::slug($option['label']);
            $auto = self::resolve($option['label']);
            $picked = $saved[$slug] ?? '';

            echo '<p style="margin:.9em 0 .3em;display:flex;align-items:center;gap:.5em;"><span style="display:inline-block;width:18px;height:18px;border-radius:50%;border:1px solid #8c8f94;background:'.esc_attr($auto !== '' ? $auto : '#9ca3af').';"></span>'
                .'<strong>'.esc_html($option['label']).'</strong>'
                .($auto === '' ? ' <em style="color:#b32d2e;">'.esc_html__('no color found – please pick one', 'sccc').'</em>' : '')
                .'</p>'
                .'<input type="text" class="sccc-swatch-picker" name="sccc_swatch['.esc_attr($slug).']" value="'.esc_attr($picked).'" placeholder="#000000">';
        }
    }

    public static function save(int $postId): void
    {
        if (! isset($_POST[self::NONCE], $_POST['sccc_swatch']) || ! is_array($_POST['sccc_swatch'])) {
            return;
        }

        if (! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[self::NONCE])), self::NONCE) || ! current_user_can('edit_post', $postId)) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        $saved = self::saved();

        foreach (wp_unslash($_POST['sccc_swatch']) as $slug => $hex) {
            $slug = self::slug((string) $slug);
            $hex = sanitize_hex_color(trim((string) $hex));

            if ($slug === '') {
                continue;
            }

            if ($hex) {
                $saved[$slug] = $hex;
            } else {
                unset($saved[$slug]);
            }
        }

        update_option(self::OPTION, $saved, false);
    }

    /* ---------------------------------------------------------------------
     * Built-in palette (approximate garment colours)
     * ------------------------------------------------------------------- */

    /** @return array<string, string> */
    public static function palette(): array
    {
        static $palette = null;

        return $palette ??= (array) apply_filters('sccc_swatch_palette', [
            // Neutrals
            'black' => '#111111', 'white' => '#ffffff', 'natural' => '#f1e9d6', 'cream' => '#f5efdc', 'ivory' => '#f6f1e1',
            'sand' => '#d6c7a8', 'tan' => '#c8a97e', 'beige' => '#d9c8a9', 'khaki' => '#b9a67c', 'vegas-gold' => '#c9b36d',
            'ash' => '#dcdddf', 'ash-grey' => '#dcdddf', 'ash-gray' => '#dcdddf', 'ice-grey' => '#cfd3d6', 'ice-gray' => '#cfd3d6',
            'sport-grey' => '#a6a8ab', 'sport-gray' => '#a6a8ab', 'athletic-heather' => '#b3b5b8', 'heather-grey' => '#a6a8ab', 'heather-gray' => '#a6a8ab',
            'grey' => '#9a9c9f', 'gray' => '#9a9c9f', 'silver' => '#c4c7ca', 'gravel' => '#8a8c8a', 'graphite-heather' => '#6e7074',
            'dark-heather' => '#4a4c50', 'dark-grey-heather' => '#4a4c50', 'dark-gray-heather' => '#4a4c50', 'dark-grey' => '#4a4c50', 'dark-gray' => '#4a4c50',
            'charcoal' => '#4b4f54', 'tweed' => '#50545a', 'asphalt' => '#55585c', 'pepper' => '#55524f', 'smoke' => '#6f7175',
            // Blues
            'navy' => '#1c2541', 'true-navy' => '#1c2541', 'midnight-navy' => '#161d33', 'royal' => '#1f4fbf', 'royal-blue' => '#1f4fbf', 'true-royal' => '#1f4fbf',
            'blue' => '#2563eb', 'cobalt' => '#1d4ed8', 'metro-blue' => '#2b3f8c', 'indigo-blue' => '#4a6a8a', 'heather-indigo' => '#4f6481',
            'carolina-blue' => '#4b9cd3', 'columbia-blue' => '#9bcbeb', 'light-blue' => '#a4d3f2', 'baby-blue' => '#b5d3ee', 'sky' => '#7ec8ee', 'sky-blue' => '#7ec8ee', 'heather-sapphire' => '#1687c7',
            'sapphire' => '#0d7fc2', 'iris' => '#4b78c2', 'stone-blue' => '#6f93a6', 'steel-blue' => '#5f7f96', 'blue-dusk' => '#2c3a55', 'denim' => '#48607c', 'slate' => '#5b6b7a',
            'aqua' => '#35b6c8', 'turquoise' => '#1fb3c4', 'tropical-blue' => '#0098b4', 'teal' => '#12808a', 'jade-dome' => '#00877a', 'antique-sapphire' => '#0a7ba8',
            // Greens
            'green' => '#16803c', 'irish-green' => '#1fa34f', 'kelly' => '#0f8a4a', 'kelly-green' => '#0f8a4a', 'forest' => '#1f3d2b', 'forest-green' => '#1f3d2b',
            'dark-green' => '#1e4630', 'military-green' => '#5a5f3c', 'army' => '#55583a', 'olive' => '#6b6b3a', 'moss' => '#6f7a45', 'sage' => '#a3b39a',
            'lime' => '#8fd13f', 'electric-green' => '#35c15a', 'safety-green' => '#ccff00', 'safety-yellow' => '#f2ff00', 'neon-green' => '#ccff00', 'neon-yellow' => '#f2ff00', 'mint' => '#b6e3c6', 'mint-green' => '#b6e3c6', 'kiwi' => '#8cc152', 'turf-green' => '#1f7a3d', 'pistachio' => '#b5cf8c',
            // Reds / pinks
            'red' => '#c8102e', 'true-red' => '#c8102e', 'cherry-red' => '#a50f2d', 'cardinal' => '#8a1c2c', 'cardinal-red' => '#8a1c2c', 'garnet' => '#7a1f2b',
            'maroon' => '#800020', 'burgundy' => '#6a1b2d', 'wine' => '#6a1b2d', 'brick' => '#8e3a34', 'crimson' => '#a3122b', 'scarlet' => '#d11f2f', 'paprika' => '#a8332a',
            'pink' => '#f4a6c0', 'light-pink' => '#f7c9d6', 'soft-pink' => '#f6c6d3', 'azalea' => '#f48fb1', 'safety-pink' => '#ff3eb5', 'neon-pink' => '#ff3eb5', 'hot-pink' => '#ff3e96',
            'heliconia' => '#e6217a', 'berry' => '#8e1b5a', 'fuchsia' => '#d81b78', 'coral' => '#f8766d', 'coral-silk' => '#f7737f', 'blossom' => '#f5c1cf', 'mauve' => '#b98a96',
            // Purples
            'purple' => '#4b2a85', 'team-purple' => '#4b2a85', 'violet' => '#8a6fc0', 'lilac' => '#b9a3d6', 'orchid' => '#c7b0de', 'lavender' => '#c3b4e3', 'heather-purple' => '#6b5a99', 'plum' => '#5b2a55', 'blackberry' => '#3a2a3f',
            // Yellows / oranges / browns
            'yellow' => '#f9d71c', 'daisy' => '#fbd84a', 'gold' => '#f2a900', 'old-gold' => '#b98a2e', 'mustard' => '#d5a021', 'banana' => '#f6e38a', 'cornsilk' => '#f3e99a', 'yellow-haze' => '#f2e08a', 'butter' => '#f4e3a1',
            'safety-orange' => '#ff6a00', 'neon-orange' => '#ff6a00', 'orange' => '#f36f21', 'burnt-orange' => '#bf5700', 'texas-orange' => '#b45a2a', 'tangerine' => '#ff8a3d', 'sunset' => '#e9774b', 'peach' => '#f6b99a',
            'brown' => '#4e342e', 'dark-chocolate' => '#3b2a26', 'chocolate' => '#45302a', 'chestnut' => '#7a4a35', 'russet' => '#6b3b2e', 'espresso' => '#3a2b25', 'brown-savana' => '#7d6850', 'mocha' => '#6f5546', 'toast' => '#b07a4e',
            // Plain colour words used for guessing
            'rose' => '#d98a9a', 'rust' => '#a5482a', 'clay' => '#b5694d', 'terracotta' => '#c0603f', 'salmon' => '#f5917b', 'magenta' => '#d0227f', 'raspberry' => '#b3214f',
            'seafoam' => '#9fd9c2', 'emerald' => '#0f8f5f', 'pine' => '#24493a', 'ocean' => '#1f6f8f', 'cyan' => '#22b8cf', 'indigo' => '#3b3f8c', 'periwinkle' => '#8f9be0',
            'lemon' => '#f7e24a', 'amber' => '#f5a524', 'copper' => '#b0683a', 'bronze' => '#8c6a3a', 'coffee' => '#4a3428', 'stone' => '#a8a39a', 'oatmeal' => '#ddd3c0', 'bone' => '#e9e2d2',
            'storm' => '#5d6670', 'steel' => '#7b8791', 'pewter' => '#8b8d8f', 'platinum' => '#d5d7d9', 'onyx' => '#16171a', 'jet' => '#111111', 'snow' => '#ffffff', 'heather' => '#a6a8ab',
        ]);
    }
}

ProductSwatches::register();
