<?php

/**
 * ============================================================================
 * File path + filename: app/Blocks/Testimonial.php
 * ============================================================================
 *
 * Purpose:
 * - Register the reusable Testimonial Gutenberg block for Space City Car Club.
 *
 * Why this file exists:
 * - The project needs a flexible testimonial block that can either:
 *   1) pull content from the Testimonial CPT, or
 *   2) accept a fully manual testimonial entry inside the block.
 * - The block must also support:
 *   - contained or full-width section mode
 *   - explicit custom section spacing fields on the OUTER section wrapper
 *   - light and dark theme output
 *   - block-contained styling that does not leak into other blocks
 *   - schema-ready output handled in the Blade template
 *
 * Editorial model:
 * - Source Mode = CPT or Manual
 * - CPT mode pulls:
 *   - title                 => person's name
 *   - featured image        => profile image
 *   - testimonial_text      => quote body
 *   - testimonial_meta_line => supporting line
 * - Manual mode allows all of the above to be entered directly in the block
 *
 * Important implementation note:
 * - We are intentionally NOT relying on Gutenberg spacing controls here.
 * - This project previously used explicit custom fields for section spacing,
 *   and that is what this block now does as well.
 * - Spacing tokens are mapped to Tailwind utility classes and applied to the
 *   parent section wrapper.
 */

namespace App\Blocks;

use App\Support\CPT\TestimonialPostType;
use Log1x\AcfComposer\Block;
use Log1x\AcfComposer\Builder;

class Testimonial extends Block
{
    /**
     * Block label shown in the inserter.
     *
     * @var string
     */
    public $name = 'Testimonial';

    /**
     * Block description shown in the inserter.
     *
     * @var string
     */
    public $description = 'A testimonial section with CPT selection or manual entry, plus contained/full-width layout control and custom Tailwind spacing fields.';

    /**
     * Stable slug for the block.
     *
     * @var string
     */
    public $slug = 'testimonial';

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
    public $icon = 'format-quote';

    /**
     * Search keywords for the inserter.
     *
     * @var array<int, string>
     */
    public $keywords = ['testimonial', 'quote', 'member', 'review'];

    /**
     * Blade view used for rendering.
     *
     * @var string
     */
    public $view = 'blocks.testimonial';

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
     *   allows optional custom utility hooks when needed
     *
     * Why spacing support is intentionally NOT enabled:
     * - This project uses explicit custom fields for section spacing.
     * - The user asked for spacing applied to the parent section wrapper.
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
        $sourceMode = $this->stringField('source_mode', 'cpt');
        $sectionLayout = $this->stringField('section_layout', 'contained');
        $showQuoteIcon = $this->boolField('show_quote_icon', true);

        $testimonial = $sourceMode === 'manual'
            ? $this->manualTestimonial()
            : $this->cptTestimonial();

        /**
         * Preview fallback.
         *
         * Why this exists:
         * - Gives editors a meaningful visual even before all fields are filled.
         */
        if (
            trim((string) ($testimonial['quote'] ?? '')) === '' &&
            trim((string) ($testimonial['name'] ?? '')) === ''
        ) {
            $testimonial = [
                'quote' => '“Space City Car Club has been an incredible way to connect with people who genuinely love cars, community, and showing up for one another.”',
                'name' => 'Sample Member Name',
                'metaLine' => 'Platinum Member since 2021',
                'imageId' => 0,
            ];
        }

        return [
            'sourceMode' => $sourceMode,
            'sectionLayout' => in_array($sectionLayout, ['contained', 'full_width'], true) ? $sectionLayout : 'contained',
            'showQuoteIcon' => $showQuoteIcon,
            'quote' => (string) ($testimonial['quote'] ?? ''),
            'name' => (string) ($testimonial['name'] ?? ''),
            'metaLine' => (string) ($testimonial['metaLine'] ?? ''),
            'imageId' => (int) ($testimonial['imageId'] ?? 0),
            'sectionSpacingClasses' => $this->buildSectionSpacingClasses(),
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
        $fields = Builder::make('testimonial_block');

        $spacingChoices = $this->spacingChoices();

        $fields
            /**
             * Source tab
             *
             * Why:
             * - Keeps the editor flow clear:
             *   choose whether the block uses a CPT record or manual entry first.
             */
            ->addTab('tab_source', [
                'label' => 'Source',
                'placement' => 'top',
            ])

            ->addButtonGroup('source_mode', [
                'label' => 'Source Mode',
                'instructions' => 'Choose whether this block pulls a testimonial from the Testimonial CPT or uses a one-off manual entry.',
                'choices' => [
                    'cpt' => 'Select Testimonial',
                    'manual' => 'Manual Entry',
                ],
                'default_value' => 'cpt',
                'layout' => 'horizontal',
                'return_format' => 'value',
            ])

            ->addPostObject('testimonial_post', [
                'label' => 'Select Testimonial',
                'instructions' => 'Choose an existing testimonial from the Testimonial CPT.',
                'post_type' => [TestimonialPostType::POST_TYPE],
                'return_format' => 'id',
                'ui' => 1,
                'allow_null' => 1,
                'required' => 1,
                'conditional_logic' => [
                    [
                        [
                            'field' => 'source_mode',
                            'operator' => '==',
                            'value' => 'cpt',
                        ],
                    ],
                ],
            ])

            /**
             * Manual entry tab
             *
             * Why:
             * - Supports one-off testimonials without forcing everything into the CPT.
             */
            ->addTab('tab_manual', [
                'label' => 'Manual Entry',
                'placement' => 'top',
            ])

            ->addImage('manual_profile_image', [
                'label' => 'Profile Image',
                'instructions' => 'Optional profile image for manual testimonial entries.',
                'return_format' => 'id',
                'preview_size' => 'medium',
                'library' => 'all',
                'required' => 0,
                'conditional_logic' => [
                    [
                        [
                            'field' => 'source_mode',
                            'operator' => '==',
                            'value' => 'manual',
                        ],
                    ],
                ],
            ])

            ->addText('manual_name', [
                'label' => 'Name',
                'required' => 0,
                'placeholder' => 'Member Name',
                'wrapper' => [
                    'width' => '50',
                ],
                'conditional_logic' => [
                    [
                        [
                            'field' => 'source_mode',
                            'operator' => '==',
                            'value' => 'manual',
                        ],
                    ],
                ],
            ])

            ->addText('manual_meta_line', [
                'label' => 'Member Line',
                'instructions' => 'Example: Platinum Member since 2021',
                'required' => 0,
                'placeholder' => 'Platinum Member since 2021',
                'wrapper' => [
                    'width' => '50',
                ],
                'conditional_logic' => [
                    [
                        [
                            'field' => 'source_mode',
                            'operator' => '==',
                            'value' => 'manual',
                        ],
                    ],
                ],
            ])

            ->addTextarea('manual_quote', [
                'label' => 'Testimonial Text',
                'rows' => 6,
                'new_lines' => 'br',
                'required' => 0,
                'maxlength' => 1500,
                'conditional_logic' => [
                    [
                        [
                            'field' => 'source_mode',
                            'operator' => '==',
                            'value' => 'manual',
                        ],
                    ],
                ],
            ])

            /**
             * Layout tab
             *
             * Why:
             * - Width mode is a design decision, not a generic content value.
             * - Editors can switch between contained and full-width treatments
             *   without breaking the internal testimonial composition.
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

            ->addTrueFalse('show_quote_icon', [
                'label' => 'Show Quote Icon',
                'default_value' => 1,
                'ui' => 1,
                'ui_on_text' => 'Show',
                'ui_off_text' => 'Hide',
            ])

            /**
             * Section spacing tab
             *
             * Why these are select fields:
             * - Matches the pattern previously used in the project
             * - Keeps spacing choices controlled and predictable
             * - Maps directly to Tailwind utility classes on the OUTER section
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
                'instructions' => 'Applied to the outer section wrapper.',
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
                'instructions' => 'Applied to the outer section wrapper.',
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
                'instructions' => 'Applied to the outer section wrapper.',
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
                'instructions' => 'Applied to the outer section wrapper.',
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
     * Build testimonial data from the selected CPT record.
     *
     * @return array<string, mixed>
     */
    protected function cptTestimonial(): array
    {
        $postId = function_exists('get_field') ? (int) get_field('testimonial_post') : 0;

        if ($postId <= 0 || get_post_type($postId) !== TestimonialPostType::POST_TYPE) {
            return [
                'quote' => '',
                'name' => '',
                'metaLine' => '',
                'imageId' => 0,
            ];
        }

        return [
            'quote' => (string) get_post_meta($postId, 'testimonial_text', true),
            'name' => trim((string) get_the_title($postId)),
            'metaLine' => (string) get_post_meta($postId, 'testimonial_meta_line', true),
            'imageId' => (int) get_post_thumbnail_id($postId),
        ];
    }

    /**
     * Build testimonial data from manual block fields.
     *
     * @return array<string, mixed>
     */
    protected function manualTestimonial(): array
    {
        return [
            'quote' => (string) (function_exists('get_field') ? get_field('manual_quote') : ''),
            'name' => $this->stringField('manual_name'),
            'metaLine' => $this->stringField('manual_meta_line'),
            'imageId' => function_exists('get_field') ? (int) get_field('manual_profile_image') : 0,
        ];
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
        $value = function_exists('get_field') ? get_field($key) : null;

        if (! is_string($value)) {
            return $default;
        }

        $value = trim($value);

        return $value !== '' ? $value : $default;
    }

    /**
     * Resolve a boolean field with fallback.
     *
     * @param string $key
     * @param bool $default
     * @return bool
     */
    protected function boolField(string $key, bool $default = false): bool
    {
        $value = function_exists('get_field') ? get_field($key) : null;

        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (bool) ((int) $value);
        }

        if (is_string($value)) {
            return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
        }

        return $default;
    }

    /**
     * Build the Tailwind spacing class string for the OUTER section wrapper.
     *
     * Why this exists:
     * - Keeps the spacing logic centralized
     * - Ensures the spacing fields affect the parent section, not the inner band
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
        $value = function_exists('get_field') ? get_field($key) : null;

        if (! is_string($value)) {
            return $default;
        }

        $value = trim($value);

        return array_key_exists($value, $this->spacingScaleMap()) ? $value : $default;
    }

    /**
     * Map a logical spacing prefix and token to a Tailwind class.
     *
     * Examples:
     * - mt + xl => mt-16
     * - pl + xs => pl-4
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
     * Why these values:
     * - Uses a simple, predictable token ladder
     * - Maps to standard Tailwind utilities
     * - Matches the token-style selector pattern discussed for the project
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
}