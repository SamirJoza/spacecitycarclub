<?php

namespace App\Blocks;

use Log1x\AcfComposer\Block;
use Log1x\AcfComposer\Builder;

/**
 * -----------------------------------------------------------------------------
 * File path + filename: app/Blocks/TrustBlock.php
 * -----------------------------------------------------------------------------
 * Purpose:
 * - Register a reusable Gutenberg block for sponsor-facing trust stats.
 * - Support up to four cards with either manual values or auto-generated values.
 * - Allow editorial manual overrides so sponsor-facing copy can stay polished,
 *   even when the raw underlying count is less marketable.
 *
 * Why this file exists:
 * - The sponsors page needs a dedicated "Why Space City?" trust/proof block.
 * - The cards should be flexible enough to support members, events, and manual
 *   marketing stats such as social reach.
 * - Members should be optional, not hardcoded into the block.
 *
 * Data sources in v1:
 * - Manual: editor enters the display value directly.
 * - Members: counts users assigned to a selected WordPress role.
 * - Events: counts published WooCommerce products of a selected product type.
 *
 * Important assumptions:
 * - "Members" are counted by WordPress role, defaulting to `sccc_member`.
 * - "Events" are counted from WooCommerce products using the custom product
 *   type slug `event` by default. If your install uses a different product
 *   type slug, the editor can change it per card.
 * - If an editor enters a manual override value, that override always wins.
 */
class TrustBlock extends Block
{
    /**
     * The block name shown in Gutenberg.
     *
     * @var string
     */
    public $name = 'Trust Block';

    /**
     * Short description shown in Gutenberg.
     *
     * @var string
     */
    public $description = 'Flexible sponsor trust/proof-point cards with optional auto counts.';

    /**
     * Gutenberg block category.
     *
     * @var string
     */
    public $category = 'widgets';

    /**
     * Dashicon slug for the inserter.
     *
     * @var string|array
     */
    public $icon = 'chart-bar';

    /**
     * Helpful search keywords.
     *
     * @var array
     */
    public $keywords = ['trust', 'stats', 'sponsors', 'proof', 'members', 'events'];

    /**
     * Default editor mode.
     *
     * @var string
     */
    public $mode = 'preview';

    /**
     * Block supports.
     *
     * @var array
     */
    public $supports = [
        'anchor' => true,
    ];

    /**
     * Data passed into the Blade view.
     *
     * @return array
     */
    public function with(): array
    {
        $cards = $this->cards();

        return [
            'heading' => $this->plainText('heading') ?: 'Why Space City?',
            'intro' => $this->formattedText('intro'),
            'cards' => $cards,
            'gridClass' => $this->gridClass(count($cards)),
        ];
    }

    /**
     * Define the ACF fields for the block.
     *
     * Why these fields exist:
     * - Content tab controls the section heading/copy.
     * - Cards tab provides up to four flexible trust cards.
     * - Each card can pull from a source or use manual editorial values.
     * - Editors now get a curated icon list instead of a free-text icon field.
     * - Source-specific settings are hidden until that source type is selected.
     *
     * @return array
     */
    public function fields(): array
    {
        $fields = Builder::make('trust_block');

        $fields
            ->addTab('content', [
                'label' => 'Content',
            ])
                ->addText('heading', [
                    'label' => 'Heading',
                    'required' => 1,
                    'default_value' => 'Why Space City?',
                ])
                ->addTextarea('intro', [
                    'label' => 'Intro',
                    'rows' => 2,
                    'new_lines' => 'br',
                    'default_value' => 'Join a growing community of enthusiasts.',
                ])

            ->addTab('cards', [
                'label' => 'Cards',
            ])
                ->addRepeater('cards', [
                    'label' => 'Trust Cards',
                    'layout' => 'block',
                    'button_label' => 'Add Card',
                    'min' => 1,
                    'max' => 4,
                ])
                    ->addText('title', [
                        'label' => 'Card Title',
                        'required' => 1,
                        'instructions' => 'Example: Active Members, Social Reach, Monthly Meets',
                    ])

                    ->addSelect('source_type', [
                        'label' => 'Value Source',
                        'required' => 1,
                        'choices' => [
                            'manual' => 'Manual',
                            'members_role' => 'Members (WordPress role count)',
                            'event_products' => 'Events (WooCommerce event products)',
                        ],
                        'default_value' => 'manual',
                        'ui' => 1,
                    ])

                    ->addText('manual_value', [
                        'label' => 'Override Display Value',
                        'instructions' => 'Optional override for any card type. Example: 10k+, 500+, 1,250',
                    ])

                    ->addSelect('value_format', [
                        'label' => 'Auto Value Format',
                        'choices' => [
                            'rounded_plus' => 'Rounded plus (10+, 100+, 1k+)',
                            'exact' => 'Exact number',
                        ],
                        'default_value' => 'rounded_plus',
                        'ui' => 1,
                    ])
                        ->conditional('source_type', '==', 'members_role')
                        ->or('source_type', '==', 'event_products')

                    ->addText('prefix', [
                        'label' => 'Value Prefix',
                        'instructions' => 'Optional. Example: >',
                    ])

                    ->addText('suffix', [
                        'label' => 'Value Suffix',
                        'instructions' => 'Optional. Example: /yr',
                    ])

                    ->addSelect('icon', [
                        'label' => 'Icon',
                        'choices' => [
                            '' => 'Auto from source',
                            'groups' => 'Members / Groups',
                            'diversity_3' => 'Community',
                            'event_available' => 'Events',
                            'calendar_month' => 'Calendar',
                            'share' => 'Social Reach',
                            'trending_up' => 'Growth',
                            'emoji_events' => 'Awards / Wins',
                            'handshake' => 'Partnerships',
                            'storefront' => 'Business Network',
                            'public' => 'Public Reach',
                            'location_on' => 'Local Presence',
                            'volunteer_activism' => 'Give Back',
                            'directions_car' => 'Cars',
                            'groups_3' => 'Audience',
                        ],
                        'default_value' => '',
                        'ui' => 1,
                    ])

                    ->addSelect('accent_style', [
                        'label' => 'Accent Style',
                        'choices' => [
                            'auto' => 'Auto from source',
                            'primary' => 'Primary Blue',
                            'cyan' => 'Cyan',
                            'purple' => 'Purple',
                            'emerald' => 'Emerald',
                            'amber' => 'Amber',
                        ],
                        'default_value' => 'auto',
                        'ui' => 1,
                    ])

                    ->addText('role_key', [
                        'label' => 'Member Role Key',
                        'instructions' => 'Used only when source is Members. Default: sccc_member',
                        'default_value' => 'sccc_member',
                    ])
                        ->conditional('source_type', '==', 'members_role')

                    ->addText('event_product_type', [
                        'label' => 'Event Product Type Slug',
                        'instructions' => 'Used only when source is Events. Default: event',
                        'default_value' => 'event',
                    ])
                        ->conditional('source_type', '==', 'event_products')
                ->endRepeater();

        return $fields->build();
    }

    /**
     * Normalize and prepare cards for rendering.
     *
     * Why this exists:
     * - Keeps the Blade template simple.
     * - Centralizes source resolution, formatting, overrides, icon fallbacks,
     *   and accent fallbacks.
     * - Enforces the up-to-4-card rule even if field constraints change later.
     *
     * @return array
     */
    protected function cards(): array
    {
        $cards = get_field('cards') ?: [];

        if (! is_array($cards)) {
            return [];
        }

        $cards = array_slice($cards, 0, 4);

        return array_values(array_filter(array_map(function ($card) {
            $title = trim(wp_strip_all_tags((string) ($card['title'] ?? '')));

            if ($title === '') {
                return null;
            }

            $sourceType = sanitize_key((string) ($card['source_type'] ?? 'manual'));
            $manualValue = trim((string) ($card['manual_value'] ?? ''));
            $valueFormat = sanitize_key((string) ($card['value_format'] ?? 'rounded_plus'));
            $prefix = trim(wp_strip_all_tags((string) ($card['prefix'] ?? '')));
            $suffix = trim(wp_strip_all_tags((string) ($card['suffix'] ?? '')));
            $icon = trim(wp_strip_all_tags((string) ($card['icon'] ?? '')));
            $accent = sanitize_key((string) ($card['accent_style'] ?? 'auto'));
            $roleKey = sanitize_key((string) ($card['role_key'] ?? 'sccc_member'));
            $eventProductType = sanitize_key((string) ($card['event_product_type'] ?? 'event'));

            $resolved = $this->resolveCardValue(
                $sourceType,
                $manualValue,
                $valueFormat,
                $roleKey,
                $eventProductType
            );

            $resolvedAccent = $accent !== 'auto' && $accent !== ''
                ? $accent
                : $this->defaultAccentForSource($sourceType);

            $resolvedIcon = $icon !== ''
                ? $icon
                : $this->defaultIconForSource($sourceType);

            return [
                'title' => $title,
                'display_value' => $resolved['display'],
                'raw_value' => $resolved['raw'],
                'can_animate' => $resolved['animate'],
                'value_format' => $resolved['format'],
                'prefix' => $prefix,
                'suffix' => $suffix,
                'icon' => $resolvedIcon,
                'accent_class' => 'is-' . $resolvedAccent,
            ];
        }, $cards)));
    }

    /**
     * Resolve a card's display/animation value.
     *
     * Rules:
     * - Manual override always wins.
     * - Manual values remain fully editorial when non-numeric.
     * - Numeric-like manual values can still animate.
     * - Auto values can be exact or rounded-plus.
     * - Raw numeric values participate in the counter animation.
     *
     * @param string $sourceType
     * @param string $manualValue
     * @param string $valueFormat
     * @param string $roleKey
     * @param string $eventProductType
     * @return array{display:string,raw:?int,animate:bool,format:string}
     */
    protected function resolveCardValue(
        string $sourceType,
        string $manualValue,
        string $valueFormat,
        string $roleKey,
        string $eventProductType
    ): array {
        if ($manualValue !== '') {
            $parsedManual = $this->parseAnimatableManualValue($manualValue);

            if ($parsedManual !== null) {
                return [
                    'display' => $manualValue,
                    'raw' => $parsedManual['raw'],
                    'animate' => true,
                    'format' => $parsedManual['format'],
                ];
            }

            return [
                'display' => $manualValue,
                'raw' => null,
                'animate' => false,
                'format' => 'manual',
            ];
        }

        $rawNumber = match ($sourceType) {
            'members_role' => $this->membersCountByRole($roleKey),
            'event_products' => $this->eventProductCount($eventProductType),
            default => null,
        };

        if ($rawNumber === null) {
            return [
                'display' => '',
                'raw' => null,
                'animate' => false,
                'format' => $valueFormat,
            ];
        }

        return [
            'display' => $valueFormat === 'exact'
                ? number_format_i18n($rawNumber)
                : $this->formatRoundedPlus($rawNumber),
            'raw' => $rawNumber,
            'animate' => true,
            'format' => $valueFormat,
        ];
    }

    /**
     * Parse manual values that still look numeric enough to animate.
     *
     * Supported examples:
     * - 500
     * - 500+
     * - 1,200
     * - 10k
     * - 10k+
     *
     * Why this exists:
     * - Sponsor-facing cards like Reach are often entered manually as display
     *   values, but they should still be able to animate if the value is really
     *   just a formatted number.
     *
     * @param string $manualValue
     * @return array{raw:int,format:string}|null
     */
    protected function parseAnimatableManualValue(string $manualValue): ?array
    {
        $value = trim($manualValue);

        if ($value === '') {
            return null;
        }

        $normalized = str_replace([',', ' '], '', $value);

        if (preg_match('/^(\d+)$/', $normalized, $matches)) {
            return [
                'raw' => (int) $matches[1],
                'format' => 'exact',
            ];
        }

        if (preg_match('/^(\d+)\+$/', $normalized, $matches)) {
            return [
                'raw' => (int) $matches[1],
                'format' => 'rounded_plus',
            ];
        }

        if (preg_match('/^(\d+(?:\.\d+)?)k\+?$/i', $normalized, $matches)) {
            return [
                'raw' => (int) round(((float) $matches[1]) * 1000),
                'format' => 'rounded_plus',
            ];
        }

        return null;
    }

    /**
     * Count users assigned to a specific WordPress role.
     *
     * Why this uses count_users():
     * - WordPress provides a built-in role count helper.
     * - This is lighter and clearer than a custom user query for this use case.
     *
     * @param string $roleKey
     * @return int|null
     */
    protected function membersCountByRole(string $roleKey): ?int
    {
        if ($roleKey === '') {
            return null;
        }

        $counts = count_users();

        if (! isset($counts['avail_roles']) || ! is_array($counts['avail_roles'])) {
            return null;
        }

        return isset($counts['avail_roles'][$roleKey])
            ? (int) $counts['avail_roles'][$roleKey]
            : 0;
    }

    /**
     * Count WooCommerce event products by custom product type.
     *
     * Why this uses wc_get_products():
     * - WooCommerce recommends wc_get_products/WC_Product_Query instead of
     *   custom WP_Query or direct database access.
     * - This keeps the theme aligned with WooCommerce's supported query layer.
     *
     * Assumption:
     * - The active events plugin exposes event items as a WooCommerce product
     *   type, defaulting here to the slug `event`.
     *
     * @param string $productType
     * @return int|null
     */
    protected function eventProductCount(string $productType): ?int
    {
        if ($productType === '' || ! function_exists('wc_get_products')) {
            return null;
        }

        $productIds = wc_get_products([
            'status' => 'publish',
            'type' => $productType,
            'limit' => -1,
            'return' => 'ids',
        ]);

        if (! is_array($productIds)) {
            return null;
        }

        return count($productIds);
    }

    /**
     * Format a raw number into sponsor-friendly rounded-plus display text.
     *
     * Rules:
     * - 0-9 stays exact.
     * - 10-99 rounds down to nearest 10 and adds +.
     * - 100-999 rounds down to nearest 100 and adds +.
     * - 1,000+ rounds down to nearest 1,000 and uses k+ notation.
     *
     * Examples:
     * - 17 -> 10+
     * - 143 -> 100+
     * - 1860 -> 1k+
     * - 10250 -> 10k+
     *
     * @param int $number
     * @return string
     */
    protected function formatRoundedPlus(int $number): string
    {
        if ($number < 10) {
            return (string) max(0, $number);
        }

        if ($number < 100) {
            return (string) (floor($number / 10) * 10) . '+';
        }

        if ($number < 1000) {
            return (string) (floor($number / 100) * 100) . '+';
        }

        return (string) floor($number / 1000) . 'k+';
    }

    /**
     * Return a smart default accent based on source type.
     *
     * Why this exists:
     * - Keeps the block visually coherent out of the box.
     * - Matches icon color to card color automatically when the editor does not
     *   explicitly choose an accent.
     *
     * @param string $sourceType
     * @return string
     */
    protected function defaultAccentForSource(string $sourceType): string
    {
        return match ($sourceType) {
            'members_role' => 'primary',
            'event_products' => 'cyan',
            default => 'purple',
        };
    }

    /**
     * Return a smart default Material Symbol icon based on source type.
     *
     * @param string $sourceType
     * @return string
     */
    protected function defaultIconForSource(string $sourceType): string
    {
        return match ($sourceType) {
            'members_role' => 'groups',
            'event_products' => 'event_available',
            default => 'share',
        };
    }

    /**
     * Determine the desktop grid modifier from the card count.
     *
     * Requirements:
     * - 3 cards => 3 columns on desktop.
     * - 4 cards => 4 columns on desktop.
     * - Tablet => 2 columns.
     * - Mobile => stacked.
     *
     * @param int $count
     * @return string
     */
    protected function gridClass(int $count): string
    {
        return match (true) {
            $count >= 4 => 'sccc-trust__grid--4',
            $count === 3 => 'sccc-trust__grid--3',
            $count === 2 => 'sccc-trust__grid--2',
            default => 'sccc-trust__grid--1',
        };
    }

    /**
     * Sanitize a plain text field value.
     *
     * @param string $field
     * @return string
     */
    protected function plainText(string $field): string
    {
        return trim(wp_strip_all_tags((string) get_field($field)));
    }

    /**
     * Return safe formatted HTML for textareas that may contain <br>.
     *
     * @param string $field
     * @return string
     */
    protected function formattedText(string $field): string
    {
        return wp_kses_post((string) get_field($field));
    }
}