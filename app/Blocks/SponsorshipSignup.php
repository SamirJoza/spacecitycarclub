<?php

namespace App\Blocks;

use Log1x\AcfComposer\Block;
use Log1x\AcfComposer\Builder;

/**
 * -----------------------------------------------------------------------------
 * File path + filename: app/Blocks/SponsorshipSignup.php
 * -----------------------------------------------------------------------------
 * Purpose:
 * - Register a reusable Gutenberg block for sponsorship tiers plus an optional
 *   Gravity Forms application form.
 * - Keep the sponsorship tier data structured and editor-friendly by using an
 *   ACF repeater rather than freeform InnerBlocks.
 * - Hand the selected tier off to Gravity Forms through a dynamic population
 *   parameter, with a JS fallback handled in the Blade view.
 *
 * Why this file exists:
 * - The sponsorship signup flow needs repeating tier cards that editors can
 *   manage without touching markup.
 * - Gravity Forms remains the authority for lead capture and notifications,
 *   while this block remains the authority for presenting the tier options.
 * - The block now restores the editable section heading/intro above the cards
 *   so the sponsorship page can match the original design more closely.
 *
 * Assumptions:
 * - The theme is already set up to auto-discover ACF Composer blocks inside
 *   app/Blocks.
 * - Gravity Forms is installed if a form ID is supplied.
 * - The target Gravity Forms field used to store the tier is either a hidden,
 *   single line text, or drop down field.
 */
class SponsorshipSignup extends Block
{
    /**
     * The block display name shown in Gutenberg.
     *
     * @var string
     */
    public $name = 'Sponsorship Signup';

    /**
     * The block description shown in Gutenberg.
     *
     * @var string
     */
    public $description = 'Sponsor tier cards with Gravity Forms handoff.';

    /**
     * Gutenberg category for the block.
     *
     * @var string
     */
    public $category = 'widgets';

    /**
     * Dashicon slug for the block picker.
     *
     * @var string|array
     */
    public $icon = 'megaphone';

    /**
     * Helpful search keywords in the block inserter.
     *
     * @var array
     */
    public $keywords = ['sponsor', 'sponsorship', 'tiers', 'gravity forms'];

    /**
     * Preview mode is the safest default for a content-heavy block like this.
     *
     * @var string
     */
    public $mode = 'preview';

    /**
     * Support block anchor so editors can deep-link or scroll-target the block.
     *
     * @var array
     */
    public $supports = [
        'anchor' => true,
    ];

    /**
     * Data passed to the Blade view before rendering.
     *
     * @return array
     */
    public function with(): array
    {
        return [
            'heading' => $this->plainText('heading') ?: 'Sponsorship Tiers',
            'intro' => $this->formattedText('intro') ?: 'Choose the level of engagement that fits your brand goals. From digital presence to on-site dominance.',
            'tiers' => $this->tiers(),
            'form' => $this->formConfig(),
        ];
    }

    /**
     * ACF field group for the block.
     *
     * Why these fields exist:
     * - Content tab restores the section heading/intro above the cards.
     * - Tiers tab keeps the actual sponsorship packages structured.
     * - Gravity Form tab provides the connection points for the form embed and
     *   the tier handoff.
     *
     * @return array
     */
    public function fields(): array
    {
        $fields = Builder::make('sponsorship_signup');

        $fields
            ->addTab('content', [
                'label' => 'Content',
            ])
                ->addText('heading', [
                    'label' => 'Section Heading',
                    'default_value' => 'Sponsorship Tiers',
                ])
                ->addTextarea('intro', [
                    'label' => 'Section Intro',
                    'rows' => 2,
                    'new_lines' => 'br',
                    'default_value' => 'Choose the level of engagement that fits your brand goals. From digital presence to on-site dominance.',
                ])

            ->addTab('tiers', [
                'label' => 'Tiers',
            ])
                ->addRepeater('tiers', [
                    'label' => 'Sponsorship Tiers',
                    'layout' => 'block',
                    'button_label' => 'Add Tier',
                    'min' => 1,
                ])
                    ->addText('name', [
                        'label' => 'Tier Name',
                        'required' => 1,
                    ])
                    ->addText('slug', [
                        'label' => 'Tier Slug',
                        'required' => 1,
                        'instructions' => 'Used for Gravity Forms values and the query string. Example: gold',
                    ])
                    ->addText('badge', [
                        'label' => 'Featured Badge',
                        'instructions' => 'Optional. Example: Most Popular',
                        'default_value' => 'Most Popular',
                    ])
                    ->addText('price', [
                        'label' => 'Price',
                        'instructions' => 'Enter digits only or with grouping (e.g. 1000, 1,000, 1.000, 2000+). Non-digits are stripped when saving. The front end shows a $ prefix and locale-appropriate thousands separators.',
                    ])
                    ->addText('price_suffix', [
                        'label' => 'Price Suffix',
                        'default_value' => '/year',
                        'instructions' => 'Optional. Example: /year',
                    ])
                    ->addTextarea('description', [
                        'label' => 'Short Description',
                        'rows' => 3,
                        'new_lines' => 'br',
                    ])
                    ->addTrueFalse('is_featured', [
                        'label' => 'Featured Tier',
                        'ui' => 1,
                        'default_value' => 0,
                    ])
                    ->addText('button_label', [
                        'label' => 'Button Label',
                        'default_value' => 'Apply Now',
                    ])
                    ->addRepeater('benefits', [
                        'label' => 'Benefits',
                        'layout' => 'table',
                        'button_label' => 'Add Benefit',
                        'min' => 1,
                    ])
                        ->addText('benefit', [
                            'label' => 'Benefit',
                            'required' => 1,
                        ])
                    ->endRepeater()
                ->endRepeater()

            ->addTab('gravity_form', [
                'label' => 'Gravity Form',
            ])
                ->addText('form_anchor_id', [
                    'label' => 'Form Anchor ID',
                    'default_value' => 'contact',
                    'instructions' => 'Used by tier buttons to scroll to the form section.',
                ])
                ->addText('form_heading', [
                    'label' => 'Form Heading',
                    'default_value' => 'Partner Application',
                ])
                ->addTextarea('form_intro', [
                    'label' => 'Form Intro',
                    'rows' => 2,
                    'new_lines' => 'br',
                    'default_value' => 'Fill out the form below and our sponsorship director will get in touch with you shortly.',
                ])
                ->addNumber('gravity_form_id', [
                    'label' => 'Gravity Form ID',
                    'instructions' => 'Leave empty if you will place the form elsewhere on the page.',
                    'min' => 1,
                ])
                ->addNumber('gravity_form_tier_field_id', [
                    'label' => 'Gravity Forms Tier Field ID',
                    'instructions' => 'The field ID of the hidden/text/dropdown field that stores the selected sponsorship tier.',
                    'min' => 1,
                ])
                ->addText('gravity_form_parameter_name', [
                    'label' => 'Dynamic Population Parameter',
                    'default_value' => 'sccc_sponsor_tier',
                    'instructions' => 'Must match the Gravity Forms field parameter name exactly.',
                ])
                ->addTrueFalse('gravity_form_show_title', [
                    'label' => 'Show Gravity Form Title',
                    'ui' => 1,
                    'default_value' => 0,
                ])
                ->addTrueFalse('gravity_form_show_description', [
                    'label' => 'Show Gravity Form Description',
                    'ui' => 1,
                    'default_value' => 0,
                ]);

        return $fields->build();
    }

    /**
     * Normalize tier data for the view.
     *
     * Why this normalization exists:
     * - Keeps the Blade template simple and predictable.
     * - Ensures every tier has a safe slug and button label.
     * - Builds the query-string URL once in PHP for graceful no-JS fallback.
     * - Adds a semantic variant class so CSS styling follows the tier identity
     *   instead of the editor's card order.
     * - Normalizes stored price strings on save (see {@see self::filterAcfTierPriceOnSave}).
     * - Formats prices for display with a $ prefix and locale-aware thousands separators
     *   (see {@see self::formatPriceForDisplay}).
     *
     * @return array
     */
    public function tiers(): array
    {
        $tiers = get_field('tiers') ?: [];

        if (! is_array($tiers)) {
            return [];
        }

        $parameterName = $this->gravityFormParameterName();
        $anchorId = $this->anchorId((string) get_field('form_anchor_id'), 'contact');
        $baseUrl = $this->currentPageBaseUrl();

        return array_values(array_filter(array_map(function ($tier) use ($parameterName, $anchorId, $baseUrl) {
            $name = trim(wp_strip_all_tags((string) ($tier['name'] ?? '')));

            if ($name === '') {
                return null;
            }

            $slug = sanitize_title((string) ($tier['slug'] ?? ''));

            if ($slug === '') {
                $slug = sanitize_title($name);
            }

            $variant = $this->resolveTierVariant($slug, $name);

            $url = add_query_arg(
                [
                    $parameterName => $slug,
                ],
                $baseUrl
            ) . '#' . $anchorId;

            $benefits = array_values(array_filter(array_map(function ($benefit) {
                $value = trim(wp_strip_all_tags((string) ($benefit['benefit'] ?? '')));

                return $value !== '' ? $value : null;
            }, $tier['benefits'] ?? [])));

            $badge = trim(wp_strip_all_tags((string) ($tier['badge'] ?? '')));
            $isFeatured = ! empty($tier['is_featured']);

            if ($isFeatured && $badge === '') {
                $badge = 'Most Popular';
            }

            $rawPrice = trim(wp_strip_all_tags((string) ($tier['price'] ?? '')));
            $canonicalPrice = self::normalizePriceForStorage($rawPrice);

            return [
                'name' => $name,
                'slug' => $slug,
                'variant' => $variant,
                'variant_class' => 'is-' . $variant,
                'badge' => $badge,
                'price' => $canonicalPrice,
                'formatted_price' => self::formatPriceForDisplay($rawPrice),
                'price_suffix' => trim(wp_strip_all_tags((string) ($tier['price_suffix'] ?? ''))),
                'description' => wp_kses_post((string) ($tier['description'] ?? '')),
                'is_featured' => $isFeatured,
                'button_label' => trim(wp_strip_all_tags((string) ($tier['button_label'] ?? ''))) ?: 'Apply Now',
                'benefits' => $benefits,
                'url' => esc_url($url),
            ];
        }, $tiers)));
    }

    /**
     * ACF: normalize tier price values on save (digits + optional trailing "+").
     *
     * @param mixed $value
     * @param int|string $post_id
     * @param array<string, mixed> $field
     * @param mixed $original
     * @return mixed
     */
    public static function filterAcfTierPriceOnSave($value, $post_id, $field, $original)
    {
        if (! is_array($field) || ! self::fieldIsSponsorshipSignupTierPrice($field)) {
            return $value;
        }

        return self::normalizePriceForStorage(is_scalar($value) ? (string) $value : '');
    }

    /**
     * @param array<string, mixed> $field
     */
    protected static function fieldIsSponsorshipSignupTierPrice(array $field): bool
    {
        if (($field['name'] ?? '') !== 'price') {
            return false;
        }

        $parentKey = $field['parent'] ?? '';
        $immediate = $parentKey && function_exists('acf_get_field') ? acf_get_field($parentKey) : null;

        return is_array($immediate)
            && ($immediate['type'] ?? '') === 'repeater'
            && ($immediate['name'] ?? '') === 'tiers';
    }

    /**
     * Strip formatting for storage: keep digits and optional trailing "+".
     */
    public static function normalizePriceForStorage(string $raw): string
    {
        $raw = trim(wp_strip_all_tags($raw));

        if ($raw === '') {
            return '';
        }

        $hasPlus = str_ends_with($raw, '+');
        if ($hasPlus) {
            $raw = rtrim(substr($raw, 0, -1));
        }

        $digits = preg_replace('/\D+/', '', $raw) ?? '';

        if ($digits === '') {
            return '';
        }

        $normalized = (string) (int) $digits;

        return $hasPlus ? $normalized . '+' : $normalized;
    }

    /**
     * Front-end display: always "$" prefix, locale-aware thousands, optional "+".
     */
    public static function formatPriceForDisplay(string $raw): string
    {
        $raw = trim(wp_strip_all_tags($raw));

        if ($raw === '') {
            return '';
        }

        $canonical = self::normalizePriceForStorage($raw);

        if ($canonical === '') {
            $fallback = trim(preg_replace('/^[\$\p{Z}\s]+/u', '', $raw) ?? '');

            return $fallback !== '' ? $fallback : '';
        }

        $hasPlus = str_ends_with($canonical, '+');
        $numPart = $hasPlus ? substr($canonical, 0, -1) : $canonical;

        if (! ctype_digit($numPart)) {
            return $raw;
        }

        $amount = (int) $numPart;
        $grouped = self::formatGroupedInteger($amount);

        return '$' . $grouped . ($hasPlus ? '+' : '');
    }

    protected static function formatGroupedInteger(int $amount): string
    {
        if (class_exists(\NumberFormatter::class) && extension_loaded('intl')) {
            $formatter = new \NumberFormatter(get_locale(), \NumberFormatter::DECIMAL);
            $formatter->setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS, 0);
            $formatter->setAttribute(\NumberFormatter::MIN_FRACTION_DIGITS, 0);
            $formatter->setAttribute(\NumberFormatter::GROUPING_USED, 1);
            $formatted = $formatter->format($amount);

            if (is_string($formatted) && $formatted !== '') {
                return $formatted;
            }
        }

        return self::formatGroupedIntegerFallback($amount);
    }

    protected static function formatGroupedIntegerFallback(int $amount): string
    {
        $lang = strtolower(substr(str_replace('_', '-', get_locale()), 0, 2));

        $europeanThousandsDot = [
            'de', 'nl', 'it', 'fr', 'es', 'pt', 'at', 'pl', 'cs', 'sk', 'hu', 'ro', 'sl', 'hr', 'bg', 'el', 'lt', 'lv', 'et',
        ];

        if (in_array($lang, $europeanThousandsDot, true)) {
            return number_format($amount, 0, ',', '.');
        }

        return number_format($amount, 0, '.', ',');
    }

    /**
     * Determine the semantic styling variant for a tier.
     *
     * Why this exists:
     * - The styling should follow the meaning of the tier, not the card order.
     * - This allows editors to reorder cards without breaking the visual system.
     *
     * @param string $slug
     * @param string $name
     * @return string
     */
    protected function resolveTierVariant(string $slug, string $name): string
    {
        $haystack = strtolower(trim($slug . ' ' . $name));

        return match (true) {
            str_contains($haystack, 'platinum') => 'platinum',
            str_contains($haystack, 'gold') => 'gold',
            str_contains($haystack, 'silver') => 'silver',
            str_contains($haystack, 'bronze') => 'bronze',
            default => 'default',
        };
    }

    /**
     * Build the Gravity Forms config payload for the view.
     *
     * Why this exists:
     * - Keeps all form-related concerns in one place.
     * - Allows the Blade view to stay focused on markup.
     * - Avoids repeated logic for parameter names, anchors, and shortcode output.
     *
     * @return array
     */
    protected function formConfig(): array
    {
        $formId = absint(get_field('gravity_form_id'));
        $tierFieldId = absint(get_field('gravity_form_tier_field_id'));
        $showTitle = ! empty(get_field('gravity_form_show_title'));
        $showDescription = ! empty(get_field('gravity_form_show_description'));
        $anchorId = $this->anchorId((string) get_field('form_anchor_id'), 'contact');

        return [
            'id' => $formId,
            'tier_field_id' => $tierFieldId,
            'parameter_name' => $this->gravityFormParameterName(),
            'anchor_id' => $anchorId,
            'heading' => $this->plainText('form_heading') ?: 'Partner Application',
            'intro' => $this->formattedText('form_intro'),
            'html' => $this->gravityFormHtml($formId, $showTitle, $showDescription),
        ];
    }

    /**
     * Render the Gravity Forms shortcode output if available.
     *
     * Why this uses the shortcode:
     * - It is the safest embed method here without assuming anything about
     *   custom wrappers or theme-side GF helper functions.
     *
     * @param int  $formId
     * @param bool $showTitle
     * @param bool $showDescription
     * @return string
     */
    protected function gravityFormHtml(int $formId, bool $showTitle, bool $showDescription): string
    {
        if ($formId < 1 || ! shortcode_exists('gravityform')) {
            return '';
        }

        $shortcode = sprintf(
            '[gravityform id="%d" title="%s" description="%s" ajax="true"]',
            $formId,
            $showTitle ? 'true' : 'false',
            $showDescription ? 'true' : 'false'
        );

        return do_shortcode($shortcode);
    }

    /**
     * Return a sanitized plain-text field value.
     *
     * @param string $field
     * @return string
     */
    protected function plainText(string $field): string
    {
        return trim(wp_strip_all_tags((string) get_field($field)));
    }

    /**
     * Return a safe HTML string for textarea-like content.
     *
     * Note:
     * - We intentionally allow safe markup because textarea fields can be set
     *   to return line breaks as <br>.
     *
     * @param string $field
     * @return string
     */
    protected function formattedText(string $field): string
    {
        return wp_kses_post((string) get_field($field));
    }

    /**
     * Sanitize and normalize the Gravity Forms population parameter.
     *
     * Why this exists:
     * - Gravity Forms expects a stable parameter name.
     * - We want a safe prefixed fallback if the editor leaves it blank.
     *
     * @return string
     */
    protected function gravityFormParameterName(): string
    {
        $parameter = sanitize_key((string) get_field('gravity_form_parameter_name'));

        return $parameter !== '' ? $parameter : 'sccc_sponsor_tier';
    }

    /**
     * Normalize an anchor ID so buttons can target it safely.
     *
     * @param string $value
     * @param string $fallback
     * @return string
     */
    protected function anchorId(string $value, string $fallback = 'contact'): string
    {
        $anchor = sanitize_title($value);

        return $anchor !== '' ? $anchor : $fallback;
    }

    /**
     * Return a clean page URL to use as the base for tier links.
     *
     * Why this exists:
     * - Buttons should degrade gracefully without JavaScript.
     * - We strip our own tier parameter so repeated clicks do not stack values.
     *
     * @return string
     */
    protected function currentPageBaseUrl(): string
    {
        $url = get_permalink();

        if (! $url) {
            return home_url('/');
        }

        return remove_query_arg($this->gravityFormParameterName(), $url);
    }
}

add_action('acf/init', static function (): void {
    if (! function_exists('acf_get_field')) {
        return;
    }

    add_filter('acf/update_value', [SponsorshipSignup::class, 'filterAcfTierPriceOnSave'], 10, 4);
});