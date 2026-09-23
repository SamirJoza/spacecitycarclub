<?php

/**
 * ============================================================================
 * File path + filename: app/Blocks/BenefitsProof.php
 * ============================================================================
 *
 * Purpose:
 * - Register the combined Benefits + Proof Gutenberg block for Space City Car Club.
 *
 * Why this file exists:
 * - The uploaded mockup treats the benefits content and proof/media collage as
 *   one composed section, not two unrelated sections.
 * - The left column handles editorial value content:
 *   eyebrow, heading, intro, and benefit items.
 * - The right column preserves the agreed fixed rhythm:
 *   image / pod / pod / image
 * - One pod is dynamic:
 *   active PMPro members who also have the sccc_member role.
 * - One pod is manual:
 *   title + number value + optional suffix.
 * - Optional extra proof pods can render below the main collage without
 *   breaking the fixed 2x2 rhythm.
 *
 * Important implementation notes:
 * - We are intentionally using explicit custom spacing fields that map to
 *   Tailwind utility classes, matching the project pattern.
 * - Margin classes are applied to the OUTER section wrapper.
 * - Padding classes are applied to the INNER band/card shell.
 * - The heading supports inline accent markup using [[text]].
 * - The section supports an optional full-width background image behind the
 *   entire block section, including an alignment selector.
 */

namespace App\Blocks;

use Log1x\AcfComposer\Block;
use Log1x\AcfComposer\Builder;
use WP_User_Query;

class BenefitsProof extends Block
{
    /**
     * Block label shown in the inserter.
     *
     * @var string
     */
    public $name = 'Benefits + Proof';

    /**
     * Block description shown in the inserter.
     *
     * @var string
     */
    public $description = 'A two-column block with benefits on the left and a fixed image/proof collage on the right.';

    /**
     * Stable slug for the block.
     *
     * @var string
     */
    public $slug = 'benefits-proof';

    /**
     * Gutenberg category.
     *
     * @var string
     */
    public $category = 'layout';

    /**
     * Dashicon shown in the inserter.
     *
     * @var string|array
     */
    public $icon = 'screenoptions';

    /**
     * Search keywords for the inserter.
     *
     * @var array<int, string>
     */
    public $keywords = ['benefits', 'proof', 'members', 'stats', 'features'];

    /**
     * Blade view used for rendering.
     *
     * @var string
     */
    public $view = 'blocks.benefits-proof';

    /**
     * Default block mode.
     *
     * @var string
     */
    public $mode = 'auto';

    /**
     * Block support settings.
     *
     * Why these supports:
     * - anchor:
     *   gives editors a native HTML anchor option
     * - customClassName:
     *   allows optional extra utility hooks when needed
     *
     * Why spacing support is intentionally NOT enabled:
     * - This project uses explicit custom fields for section spacing
     *   mapped to Tailwind utility classes.
     *
     * @var array<string, mixed>
     */
    public $supports = [
        'anchor' => true,
        'multiple' => true,
        'jsx' => false,
        'align' => false,
        'align_text' => false,
        'align_content' => false,
        'customClassName' => true,
    ];

    /**
     * Data passed into the Blade view.
     *
     * @return array<string, mixed>
     */
    public function with(): array
    {
        $headingRaw = $this->stringField('heading', 'Fuel Your Passion With [[Exclusivity.]]');
        $headingHighlight = $this->sanitizeHighlightStyle((string) $this->fieldValue('heading_highlight'));

        return [
            'eyebrow' => $this->stringField('eyebrow', ''),
            'headingRaw' => $headingRaw,
            'headingHtml' => $this->formatHeadlineMarkup($headingRaw, $headingHighlight),
            'headingHighlight' => $headingHighlight,
            'intro' => $this->stringField('intro', ''),
            'benefits' => $this->buildBenefits(),
            'sectionLayout' => $this->validatedField('section_layout', ['contained', 'full_width'], 'contained'),
            'sectionSpacingClasses' => $this->buildSectionSpacingClasses(),
            'sectionBackgroundImageId' => (int) $this->fieldValue('section_background_image'),
            'sectionBackgroundPosition' => $this->backgroundPositionCss(
                $this->validatedField('section_background_position', array_keys($this->backgroundPositionMap()), 'right_center')
            ),
            'imagePrimaryId' => (int) $this->fieldValue('image_primary'),
            'imageSecondaryId' => (int) $this->fieldValue('image_secondary'),
            'membersPod' => $this->buildMembersPod(),
            'manualPod' => $this->buildManualPod(),
            'extraProofPods' => $this->buildExtraProofPods(),
            'isPreview' => (bool) $this->preview,
        ];
    }

    /**
     * Define the ACF field group for the block.
     *
     * @return array<string, mixed>
     */
    public function fields(): array
    {
        $fields = Builder::make('benefits_proof_block');
        $spacingChoices = $this->spacingChoices();

        $fields
            /**
             * Content tab
             *
             * Why this exists:
             * - Keeps the left-column editorial content grouped together.
             */
            ->addTab('tab_content', [
                'label' => 'Content',
                'placement' => 'top',
            ])

            ->addText('eyebrow', [
                'label' => 'Eyebrow',
                'instructions' => 'Optional small label above the heading. Leave blank to hide it.',
                'default_value' => '',
                'placeholder' => 'Member Benefits',
            ])

            ->addText('heading', [
                'label' => 'Heading',
                'instructions' => 'Use [[text]] anywhere in the headline to apply the selected highlight style. Example: Fuel Your Passion With [[Exclusivity.]] You may also use <strong>bold text</strong>.',
                'default_value' => 'Fuel Your Passion With [[Exclusivity.]]',
                'placeholder' => 'Fuel Your Passion With [[Exclusivity.]]',
            ])

            ->addSelect('heading_highlight', [
                'label' => 'Heading Highlight Style',
                'instructions' => 'Controls the visual style applied to text wrapped in [[double brackets]].',
                'choices' => [
                    'club-blue' => 'Club Blue',
                    'signal-red' => 'Signal Red',
                    'space-city' => 'Space City',
                    'club-blue-solid' => 'Club Blue Solid',
                    'signal-red-solid' => 'Signal Red Solid',
                    'deep-purple-solid' => 'Deep Purple Solid',
                ],
                'default_value' => 'club-blue',
                'allow_null' => 0,
                'ui' => 1,
            ])

            ->addTextarea('intro', [
                'label' => 'Intro Text',
                'rows' => 4,
                'new_lines' => 'br',
                'placeholder' => 'Add the short supporting intro text for the section.',
            ])

            ->addRepeater('benefits', [
                'label' => 'Benefit Items',
                'instructions' => 'These render in the left-column benefit grid.',
                'layout' => 'block',
                'button_label' => 'Add Benefit',
                'min' => 1,
                'max' => 8,
            ])
                ->addSelect('icon', [
                    'label' => 'Icon',
                    'choices' => $this->benefitIconChoices(),
                    'default_value' => 'calendar',
                    'allow_null' => 0,
                    'ui' => 1,
                    'wrapper' => [
                        'width' => '33',
                    ],
                ])
                ->addText('title', [
                    'label' => 'Title',
                    'required' => 1,
                    'wrapper' => [
                        'width' => '67',
                    ],
                ])
                ->addTextarea('description', [
                    'label' => 'Description',
                    'rows' => 3,
                    'new_lines' => 'br',
                    'required' => 1,
                ])
            ->endRepeater()

            /**
             * Section background tab
             *
             * Why this exists:
             * - The original section treatment reads like a darker section with
             *   an optional full-width background image behind the whole block.
             */
            ->addTab('tab_section_background', [
                'label' => 'Section Background',
                'placement' => 'top',
            ])

            ->addImage('section_background_image', [
                'label' => 'Section Background Image',
                'instructions' => 'Optional. This image spans the full section behind the entire block.',
                'return_format' => 'id',
                'preview_size' => 'large',
                'library' => 'all',
            ])

            ->addSelect('section_background_position', [
                'label' => 'Background Image Alignment',
                'instructions' => 'Controls where the background image is anchored within the full section.',
                'choices' => $this->backgroundPositionChoices(),
                'default_value' => 'right_center',
                'allow_null' => 0,
                'ui' => 1,
            ])

            /**
             * Media & proof tab
             *
             * Why this exists:
             * - Holds the right-column assets while preserving the fixed rhythm.
             */
            ->addTab('tab_proof', [
                'label' => 'Proof / Media',
                'placement' => 'top',
            ])

            ->addImage('image_primary', [
                'label' => 'Primary Image',
                'instructions' => 'Renders in the top-left slot of the right-column collage.',
                'return_format' => 'id',
                'preview_size' => 'large',
                'library' => 'all',
                'wrapper' => [
                    'width' => '50',
                ],
            ])

            ->addImage('image_secondary', [
                'label' => 'Secondary Image',
                'instructions' => 'Renders in the bottom-right slot of the right-column collage.',
                'return_format' => 'id',
                'preview_size' => 'large',
                'library' => 'all',
                'wrapper' => [
                    'width' => '50',
                ],
            ])

            ->addText('members_pod_title', [
                'label' => 'Dynamic Members Pod Title',
                'instructions' => 'The value is calculated automatically. Example: Active Members',
                'default_value' => 'Active Members',
                'placeholder' => 'Active Members',
                'wrapper' => [
                    'width' => '50',
                ],
            ])

            ->addText('manual_pod_title', [
                'label' => 'Manual Pod Title',
                'instructions' => 'Example: Annual Events',
                'default_value' => 'Annual Events',
                'placeholder' => 'Annual Events',
                'wrapper' => [
                    'width' => '50',
                ],
            ])

            ->addNumber('manual_pod_value', [
                'label' => 'Manual Pod Value',
                'instructions' => 'Numeric value only. Large numbers will be shortened to K notation automatically.',
                'default_value' => 50,
                'min' => 0,
                'step' => 1,
                'wrapper' => [
                    'width' => '50',
                ],
            ])

            ->addText('manual_pod_suffix', [
                'label' => 'Manual Pod Suffix',
                'instructions' => 'Optional. Example: +, %, yrs',
                'default_value' => '+',
                'placeholder' => '+',
                'wrapper' => [
                    'width' => '50',
                ],
            ])

            ->addRepeater('extra_proof_pods', [
                'label' => 'Extra Proof Pods',
                'instructions' => 'Optional. These render below the main collage so the fixed image/pod/pod/image layout stays intact.',
                'layout' => 'row',
                'button_label' => 'Add Extra Proof Pod',
                'min' => 0,
                'max' => 6,
            ])
                ->addText('title', [
                    'label' => 'Title',
                    'required' => 1,
                    'wrapper' => [
                        'width' => '40',
                    ],
                ])
                ->addNumber('value', [
                    'label' => 'Value',
                    'required' => 1,
                    'min' => 0,
                    'step' => 1,
                    'wrapper' => [
                        'width' => '30',
                    ],
                ])
                ->addText('suffix', [
                    'label' => 'Suffix',
                    'instructions' => 'Optional. Example: +, %, yrs',
                    'wrapper' => [
                        'width' => '30',
                    ],
                ])
            ->endRepeater()

            /**
             * Layout tab
             *
             * Why this exists:
             * - Width mode is a design decision, not raw content.
             */
            ->addTab('tab_layout', [
                'label' => 'Layout',
                'placement' => 'top',
            ])

            ->addButtonGroup('section_layout', [
                'label' => 'Section Layout',
                'instructions' => 'Choose whether the section is contained or stretches full width.',
                'choices' => [
                    'contained' => 'Contained',
                    'full_width' => 'Full Width',
                ],
                'default_value' => 'contained',
                'layout' => 'horizontal',
                'return_format' => 'value',
            ])

            /**
             * Section spacing tab
             *
             * Why these are select fields:
             * - Matches the pattern previously used in the project
             * - Keeps spacing choices controlled and predictable
             * - Maps directly to Tailwind utility classes
             */
            ->addTab('tab_spacing', [
                'label' => 'Section Spacing',
                'placement' => 'top',
            ])

            ->addSelect('section_margin_top', [
                'label' => 'Margin Top',
                'instructions' => 'Applied to the outer section wrapper.',
                'choices' => $spacingChoices,
                'default_value' => 'none',
                'allow_null' => 0,
                'ui' => 1,
                'wrapper' => [
                    'width' => '50',
                ],
            ])

            ->addSelect('section_margin_bottom', [
                'label' => 'Margin Bottom',
                'instructions' => 'Applied to the outer section wrapper.',
                'choices' => $spacingChoices,
                'default_value' => 'none',
                'allow_null' => 0,
                'ui' => 1,
                'wrapper' => [
                    'width' => '50',
                ],
            ])

            ->addSelect('section_padding_top', [
                'label' => 'Padding Top',
                'instructions' => 'Applied to the inner section band.',
                'choices' => $spacingChoices,
                'default_value' => 'xl',
                'allow_null' => 0,
                'ui' => 1,
                'wrapper' => [
                    'width' => '50',
                ],
            ])

            ->addSelect('section_padding_bottom', [
                'label' => 'Padding Bottom',
                'instructions' => 'Applied to the inner section band.',
                'choices' => $spacingChoices,
                'default_value' => 'xl',
                'allow_null' => 0,
                'ui' => 1,
                'wrapper' => [
                    'width' => '50',
                ],
            ])

            ->addSelect('section_padding_left', [
                'label' => 'Padding Left',
                'instructions' => 'Applied to the inner section band.',
                'choices' => $spacingChoices,
                'default_value' => 'xs',
                'allow_null' => 0,
                'ui' => 1,
                'wrapper' => [
                    'width' => '50',
                ],
            ])

            ->addSelect('section_padding_right', [
                'label' => 'Padding Right',
                'instructions' => 'Applied to the inner section band.',
                'choices' => $spacingChoices,
                'default_value' => 'xs',
                'allow_null' => 0,
                'ui' => 1,
                'wrapper' => [
                    'width' => '50',
                ],
            ]);

        return $fields->build();
    }

    /**
     * Build the benefit items array for the view.
     *
     * @return array<int, array<string, string>>
     */
    protected function buildBenefits(): array
    {
        $rows = $this->fieldValue('benefits');

        if (! is_array($rows) || empty($rows)) {
            return [
                [
                    'icon' => 'calendar',
                    'title' => 'Exclusive Events',
                    'description' => 'Private track days, sunset canyon runs, and rooftop networking nights.',
                ],
                [
                    'icon' => 'tag',
                    'title' => 'Early Merch Access',
                    'description' => 'Skip the public wait. Access limited-run apparel and club parts first.',
                ],
                [
                    'icon' => 'briefcase',
                    'title' => 'Business Directory',
                    'description' => 'Promote your business or find trusted services within the club network.',
                ],
                [
                    'icon' => 'community',
                    'title' => 'Partner Discounts',
                    'description' => 'Special member pricing with detailers, tuners, and tire shops.',
                ],
            ];
        }

        $items = [];

        foreach ($rows as $row) {
            $icon = is_array($row) ? trim((string) ($row['icon'] ?? 'calendar')) : 'calendar';
            $title = is_array($row) ? trim((string) ($row['title'] ?? '')) : '';
            $description = is_array($row) ? trim((string) ($row['description'] ?? '')) : '';

            if ($title === '' || $description === '') {
                continue;
            }

            $items[] = [
                'icon' => array_key_exists($icon, $this->benefitIconChoices()) ? $icon : 'calendar',
                'title' => $title,
                'description' => $description,
            ];
        }

        return $items;
    }

    /**
     * Build the dynamic members pod.
     *
     * Why this exists:
     * - This pod should always reflect live membership data rather than manual entry.
     *
     * @return array<string, string>
     */
    protected function buildMembersPod(): array
    {
        $title = $this->stringField('members_pod_title', 'Active Members');
        $count = $this->countActiveClubMembers();

        return [
            'title' => $title,
            'value' => $this->formatMembersBadgeValue($count),
        ];
    }

    /**
     * Build the main manual proof pod.
     *
     * @return array<string, string>
     */
    protected function buildManualPod(): array
    {
        $title = $this->stringField('manual_pod_title', 'Annual Events');
        $value = $this->fieldValue('manual_pod_value');
        $suffix = $this->stringField('manual_pod_suffix', '+');

        $number = is_numeric($value) ? max(0, (int) $value) : 50;

        return [
            'title' => $title,
            'value' => $this->formatStatValue($number, $suffix),
        ];
    }

    /**
     * Build optional proof pods below the fixed collage.
     *
     * @return array<int, array<string, string>>
     */
    protected function buildExtraProofPods(): array
    {
        $rows = $this->fieldValue('extra_proof_pods');

        if (! is_array($rows) || empty($rows)) {
            return [];
        }

        $pods = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $title = trim((string) ($row['title'] ?? ''));
            $value = $row['value'] ?? null;
            $suffix = trim((string) ($row['suffix'] ?? ''));

            if ($title === '' || ! is_numeric($value)) {
                continue;
            }

            $pods[] = [
                'title' => $title,
                'value' => $this->formatStatValue(max(0, (int) $value), $suffix),
            ];
        }

        return $pods;
    }

    /**
     * Count active PMPro members who also have the sccc_member role.
     *
     * Why this is role + PMPro based:
     * - The project rule is:
     *   Member = WordPress user with an active PMPro membership level.
     * - This block also needs to represent actual SCCC club members, so the
     *   sccc_member role is included as a club-specific gate.
     *
     * @return int
     */
    protected function countActiveClubMembers(): int
    {
        if (! class_exists(WP_User_Query::class)) {
            return 0;
        }

        $queryArgs = [
            'role' => 'sccc_member',
            'fields' => 'ID',
            'number' => -1,
        ];

        $query = new WP_User_Query($queryArgs);
        $userIds = $query->get_results();

        if (! is_array($userIds) || empty($userIds)) {
            return 0;
        }

        $active = 0;

        foreach ($userIds as $userId) {
            if ($this->userHasActivePmproMembership((int) $userId)) {
                $active++;
            }
        }

        return $active;
    }

    /**
     * Check whether a user has an active PMPro membership.
     *
     * @param int $userId
     * @return bool
     */
    protected function userHasActivePmproMembership(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }

        if (function_exists('pmpro_hasMembershipLevel')) {
            return (bool) pmpro_hasMembershipLevel(null, $userId);
        }

        return true;
    }

    /**
     * Format the dynamic members badge value.
     *
     * Rules agreed in project:
     * - 0–9   => 10+
     * - 10–19 => 10+
     * - 20–29 => 20+
     * - etc.
     * - Large values use K notation, e.g. 1000 => 1K+
     *
     * @param int $count
     * @return string
     */
    protected function formatMembersBadgeValue(int $count): string
    {
        $bucket = max(10, (int) floor(max(0, $count) / 10) * 10);

        if ($count > 0 && $count < 10) {
            $bucket = 10;
        }

        if ($bucket >= 1000) {
            return $this->abbreviateThousands($bucket, true);
        }

        return number_format_i18n($bucket) . '+';
    }

    /**
     * Format a generic proof/stat value.
     *
     * Why this exists:
     * - Manual stats should support K notation for large values.
     * - Optional suffixes like +, %, or yrs are appended afterward.
     *
     * @param int    $value
     * @param string $suffix
     * @return string
     */
    protected function formatStatValue(int $value, string $suffix = ''): string
    {
        $formatted = $value >= 1000
            ? $this->abbreviateThousands($value, false)
            : number_format_i18n($value);

        return $formatted . $suffix;
    }

    /**
     * Abbreviate thousands using K notation.
     *
     * Examples:
     * - 1000 => 1K
     * - 1540 => 1.5K
     * - 2000 => 2K
     *
     * @param int  $value
     * @param bool $appendPlus
     * @return string
     */
    protected function abbreviateThousands(int $value, bool $appendPlus = false): string
    {
        $abbreviated = floor(($value / 1000) * 10) / 10;
        $display = (fmod($abbreviated, 1.0) === 0.0)
            ? (string) (int) $abbreviated
            : number_format($abbreviated, 1);

        return $display . 'K' . ($appendPlus ? '+' : '');
    }

    /**
     * Format heading text that supports [[highlighted text]] markup.
     *
     * Why this exists:
     * - The user requested headline color accents without introducing another
     *   separate field or partial headline setting.
     *
     * Example:
     * - Fuel Your Passion With [[Exclusivity.]]
     *
     * @param string $heading
     * @param string $highlightStyle
     * @return string
     */
    protected function formatHeadlineMarkup(string $heading, string $highlightStyle = 'club-blue'): string
    {
        $heading = trim($heading);

        if ($heading === '') {
            return '';
        }

        $highlightStyle = $this->sanitizeHighlightStyle($highlightStyle);

        $safeHeading = wp_kses($heading, [
            'strong' => [],
        ]);

        $parts = preg_split('/(\[\[.*?\]\])/', $safeHeading, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

        if (! is_array($parts) || empty($parts)) {
            return $safeHeading;
        }

        $html = '';

        foreach ($parts as $part) {
            if (preg_match('/^\[\[(.*?)\]\]$/s', $part, $matches)) {
                $highlight = trim((string) ($matches[1] ?? ''));

                if ($highlight !== '') {
                    $html .= '<span class="sccc-benefits-proof-block__heading-accent sccc-benefits-proof-block__heading-accent--' . esc_attr($highlightStyle) . '" data-sccc-highlight>' . $highlight . '</span>';
                }

                continue;
            }

            $html .= $part;
        }

        return wp_kses($html, [
            'span' => [
                'class' => true,
                'data-sccc-highlight' => true,
            ],
            'strong' => [],
        ]);
    }

    /**
     * Restrict heading highlight styles to the current editor choices.
     *
     * @param string $highlightStyle
     * @return string
     */
    protected function sanitizeHighlightStyle(string $highlightStyle): string
    {
        $highlightStyle = strtolower(trim($highlightStyle));

        $allowed = [
            'club-blue',
            'signal-red',
            'space-city',
            'club-blue-solid',
            'signal-red-solid',
            'deep-purple-solid',
        ];

        return in_array($highlightStyle, $allowed, true) ? $highlightStyle : 'club-blue';
    }

    /**
     * Resolve a field value safely.
     *
     * @param string $key
     * @return mixed
     */
    protected function fieldValue(string $key)
    {
        return function_exists('get_field') ? get_field($key) : null;
    }

    /**
     * Resolve a string field with fallback.
     *
     * @param string $key
     * @param string $default
     * @return string
     */
    protected function stringField(string $key, string $default = ''): string
    {
        $value = $this->fieldValue($key);

        if (! is_string($value)) {
            return $default;
        }

        $value = trim($value);

        return $value !== '' ? $value : $default;
    }

    /**
     * Resolve a validated field against an allow-list.
     *
     * @param string $key
     * @param array<int,string> $allowed
     * @param string $default
     * @return string
     */
    protected function validatedField(string $key, array $allowed, string $default): string
    {
        $value = $this->stringField($key, $default);

        return in_array($value, $allowed, true) ? $value : $default;
    }

    /**
     * Build the Tailwind spacing class string for this block.
     *
     * Why this exists:
     * - Keeps spacing logic centralized.
     * - Mirrors the project pattern already used elsewhere.
     *
     * @return string
     */
    protected function buildSectionSpacingClasses(): string
    {
        $classes = [
            $this->spacingClass('mt', $this->spacingTokenField('section_margin_top', 'none')),
            $this->spacingClass('mb', $this->spacingTokenField('section_margin_bottom', 'none')),
            $this->spacingClass('pt', $this->spacingTokenField('section_padding_top', 'xl')),
            $this->spacingClass('pb', $this->spacingTokenField('section_padding_bottom', 'xl')),
            $this->spacingClass('pl', $this->spacingTokenField('section_padding_left', 'xs')),
            $this->spacingClass('pr', $this->spacingTokenField('section_padding_right', 'xs')),
        ];

        return trim(implode(' ', array_filter($classes)));
    }

    /**
     * Resolve a spacing token field with fallback.
     *
     * @param string $key
     * @param string $default
     * @return string
     */
    protected function spacingTokenField(string $key, string $default = 'none'): string
    {
        $value = $this->fieldValue($key);

        if (! is_string($value)) {
            return $default;
        }

        $value = trim($value);

        return array_key_exists($value, $this->spacingScaleMap()) ? $value : $default;
    }

    /**
     * Map a logical spacing prefix and token to a Tailwind class.
     *
     * @param string $prefix
     * @param string $token
     * @return string
     */
    protected function spacingClass(string $prefix, string $token): string
    {
        $scale = $this->spacingScaleMap()[$token] ?? '0';

        return sprintf('%s-%s', $prefix, $scale);
    }

    /**
     * Shared spacing scale map.
     *
     * @return array<string, string>
     */
    protected function spacingScaleMap(): array
    {
        return [
            'none' => '0',
            'xs' => '4',
            's' => '6',
            'm' => '8',
            'l' => '12',
            'xl' => '16',
            '2xl' => '20',
        ];
    }

    /**
     * Human-readable spacing choices for ACF select fields.
     *
     * @return array<string, string>
     */
    protected function spacingChoices(): array
    {
        return [
            'none' => 'None',
            'xs' => 'XS',
            's' => 'S',
            'm' => 'M',
            'l' => 'L',
            'xl' => 'XL',
            '2xl' => '2XL',
        ];
    }

    /**
     * Available icon choices for benefit items.
     *
     * @return array<string, string>
     */
    protected function benefitIconChoices(): array
    {
        return [
            'calendar' => 'Calendar / Events',
            'tag' => 'Tag / Discounts',
            'briefcase' => 'Briefcase / Business',
            'community' => 'Community',
            'shield' => 'Shield / Trust',
            'car' => 'Car',
            'star' => 'Star',
            'megaphone' => 'Megaphone / Announcements',
        ];
    }

    /**
     * Human-readable choices for section background image alignment.
     *
     * @return array<string, string>
     */
    protected function backgroundPositionChoices(): array
    {
        return [
            'left_top' => 'Left Top',
            'left_center' => 'Left Center',
            'left_bottom' => 'Left Bottom',
            'center_top' => 'Center Top',
            'center_center' => 'Center Center',
            'center_bottom' => 'Center Bottom',
            'right_top' => 'Right Top',
            'right_center' => 'Right Center',
            'right_bottom' => 'Right Bottom',
        ];
    }

    /**
     * Map a background alignment token to a safe CSS object-position value.
     *
     * @param string $token
     * @return string
     */
    protected function backgroundPositionCss(string $token): string
    {
        return $this->backgroundPositionMap()[$token] ?? 'right center';
    }

    /**
     * Safe allow-list map for background image alignment.
     *
     * @return array<string, string>
     */
    protected function backgroundPositionMap(): array
    {
        return [
            'left_top' => 'left top',
            'left_center' => 'left center',
            'left_bottom' => 'left bottom',
            'center_top' => 'center top',
            'center_center' => 'center center',
            'center_bottom' => 'center bottom',
            'right_top' => 'right top',
            'right_center' => 'right center',
            'right_bottom' => 'right bottom',
        ];
    }
}