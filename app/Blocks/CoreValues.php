<?php

/**
 * ============================================================================
 * File path + filename: app/Blocks/CoreValues.php
 * ============================================================================
 *
 * Purpose:
 * - Register a flexible ACF Composer Gutenberg block for the "Core Values"
 *   section used on page layouts.
 *
 * Why this file exists:
 * - The user wants a reusable page block that matches the provided Core Values
 *   section visually, while keeping the content fully editable in Gutenberg.
 * - Editors must be able to control:
 *   - section heading
 *   - section subheading
 *   - section spacing
 *   - number of cards
 *   - each card's heading, icon, and body text
 *
 * Editor experience:
 * - The card count is controlled naturally by an ACF Repeater.
 * - Each card uses a curated Material Symbols Select field for convenience.
 * - A custom icon override field is included for edge cases where the curated
 *   list is not enough.
 * - Section margin and padding are controlled by simple dropdowns so editors
 *   do not need to rely on Tailwind utility classes inside Gutenberg Advanced.
 *
 * Technical notes:
 * - The responsive card layout is handled in the Blade view using Tailwind’s
 *   mobile-first breakpoint classes:
 *   - 1 column on mobile
 *   - 2 columns on md
 *   - 3 columns on xl
 * - Styles specific to this block live in the Blade view so they stay
 *   contained and do not add bulk to the global stylesheet.
 * - Section spacing values are validated against a curated spacing scale before
 *   being printed as CSS variables on the outer section.
 *
 * Assumptions:
 * - ACF Composer block discovery is already working in the Sage 11 theme.
 * - The Material Symbols font is already loaded globally in the theme.
 */

namespace App\Blocks;

use Log1x\AcfComposer\Block;
use Log1x\AcfComposer\Builder;

class CoreValues extends Block
{
    /**
     * Block label shown in the inserter.
     *
     * @var string
     */
    public $name = 'Core Values';

    /**
     * Block description shown in the inserter.
     *
     * @var string
     */
    public $description = 'A flexible Core Values section with editable heading, subheading, spacing, and repeatable value cards.';

    /**
     * Stable slug for the block.
     *
     * @var string
     */
    public $slug = 'core-values';

    /**
     * Gutenberg category.
     *
     * Why layout:
     * - This is a full content section, not a sidebar utility block.
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
     * Search keywords for the block inserter.
     *
     * @var array<int, string>
     */
    public $keywords = ['values', 'cards', 'about', 'culture', 'principles'];

    /**
     * Blade view used to render the block.
     *
     * @var string
     */
    public $view = 'blocks.core-values';

    /**
     * Default block mode.
     *
     * Why auto:
     * - Editors see a useful preview by default.
     * - Clicking the block still gives a natural editing workflow.
     *
     * @var string
     */
    public $mode = 'auto';

    /**
     * Block support settings.
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
     * Data passed into the Blade view.
     *
     * @return array<string, mixed>
     */
    public function with(): array
    {
        return [
            'heading' => $this->stringField('heading', 'Core Values'),
            'subheading' => $this->stringField('subheading', 'The principles that drive every meet, cruise, and conversation.'),
            'marginTop' => $this->spacingField('margin_top', '1.5rem'),
            'marginBottom' => $this->spacingField('margin_bottom', '1.5rem'),
            'paddingTop' => $this->spacingField('padding_top', '3rem'),
            'paddingBottom' => $this->spacingField('padding_bottom', '3rem'),
            'cards' => $this->cards(),
            'isPreview' => (bool) $this->preview,
        ];
    }

    /**
     * The ACF field group for this block.
     *
     * @return array<string, mixed>
     */
    public function fields(): array
    {
        $fields = Builder::make('core_values');

        $fields
            ->addText('heading', [
                'label' => 'Section Heading',
                'default_value' => 'Core Values',
                'required' => 0,
            ])
            ->addTextarea('subheading', [
                'label' => 'Section Subheading',
                'default_value' => 'The principles that drive every meet, cruise, and conversation.',
                'rows' => 3,
                'new_lines' => 'br',
                'required' => 0,
            ])
            ->addSelect('margin_top', [
                'label' => 'Margin Top',
                'instructions' => 'Controls the space above the Core Values section.',
                'choices' => $this->spacingChoices(),
                'default_value' => '1.5rem',
                'ui' => 1,
                'allow_null' => 0,
                'required' => 0,
                'wrapper' => [
                    'width' => '25',
                ],
            ])
            ->addSelect('margin_bottom', [
                'label' => 'Margin Bottom',
                'instructions' => 'Controls the space below the Core Values section.',
                'choices' => $this->spacingChoices(),
                'default_value' => '1.5rem',
                'ui' => 1,
                'allow_null' => 0,
                'required' => 0,
                'wrapper' => [
                    'width' => '25',
                ],
            ])
            ->addSelect('padding_top', [
                'label' => 'Padding Top',
                'instructions' => 'Controls the inner space at the top of the Core Values section.',
                'choices' => $this->spacingChoices(),
                'default_value' => '3rem',
                'ui' => 1,
                'allow_null' => 0,
                'required' => 0,
                'wrapper' => [
                    'width' => '25',
                ],
            ])
            ->addSelect('padding_bottom', [
                'label' => 'Padding Bottom',
                'instructions' => 'Controls the inner space at the bottom of the Core Values section.',
                'choices' => $this->spacingChoices(),
                'default_value' => '3rem',
                'ui' => 1,
                'allow_null' => 0,
                'required' => 0,
                'wrapper' => [
                    'width' => '25',
                ],
            ])
            ->addRepeater('cards', [
                'label' => 'Value Cards',
                'instructions' => 'Add, remove, and reorder Core Values cards.',
                'layout' => 'block',
                'button_label' => 'Add Value Card',
                'collapsed' => 'title',
                'min' => 1,
                'required' => 1,
            ])
                ->addText('title', [
                    'label' => 'Card Heading',
                    'required' => 1,
                    'wrapper' => [
                        'width' => '50',
                    ],
                ])
                ->addSelect('icon', [
                    'label' => 'Card Icon',
                    'instructions' => 'Choose a Material Symbol from the curated list.',
                    'choices' => $this->iconChoices(),
                    'default_value' => 'diversity_3',
                    'ui' => 1,
                    'allow_null' => 0,
                    'required' => 1,
                    'wrapper' => [
                        'width' => '50',
                    ],
                ])
                ->addText('custom_icon', [
                    'label' => 'Custom Material Symbol Override',
                    'instructions' => 'Optional. Leave empty to use the selected icon above.',
                    'required' => 0,
                    'wrapper' => [
                        'width' => '50',
                    ],
                ])
                ->addTextarea('description', [
                    'label' => 'Card Description',
                    'rows' => 4,
                    'new_lines' => 'br',
                    'required' => 1,
                ])
            ->endRepeater();

        return $fields->build();
    }

    /**
     * Normalize a text field into a trimmed string with fallback.
     *
     * @param  string  $key
     * @param  string  $default
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
     * Normalize and validate a spacing field against the curated spacing scale.
     *
     * @param  string  $key
     * @param  string  $default
     * @return string
     */
    protected function spacingField(string $key, string $default = '0rem'): string
    {
        $value = function_exists('get_field') ? get_field($key) : null;

        if (! is_string($value)) {
            return $default;
        }

        $value = trim($value);
        $choices = $this->spacingChoices();

        return array_key_exists($value, $choices) ? $value : $default;
    }

    /**
     * Curated spacing scale for section margin and padding controls.
     *
     * Values intentionally use CSS lengths instead of Tailwind classes so the
     * block remains reliable when spacing is changed inside Gutenberg.
     *
     * @return array<string, string>
     */
    protected function spacingChoices(): array
    {
        return [
            '0rem' => 'None — 0px',
            '0.5rem' => 'XS — 8px',
            '1rem' => 'Small — 16px',
            '1.5rem' => 'Medium — 24px',
            '2rem' => 'Large — 32px',
            '3rem' => 'XL — 48px',
            '4rem' => '2XL — 64px',
            '5rem' => '3XL — 80px',
            '6rem' => '4XL — 96px',
        ];
    }

    /**
     * Build a clean array of repeater card rows for the view.
     *
     * @return array<int, array<string, string>>
     */
    protected function cards(): array
    {
        $rows = function_exists('get_field') ? get_field('cards') : null;

        if (! is_array($rows)) {
            return [];
        }

        $cards = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $title = trim((string) ($row['title'] ?? ''));
            $description = trim((string) ($row['description'] ?? ''));
            $selectedIcon = trim((string) ($row['icon'] ?? 'diversity_3'));
            $customIcon = trim((string) ($row['custom_icon'] ?? ''));

            if ($title === '' && $description === '') {
                continue;
            }

            $cards[] = [
                'title' => $title,
                'description' => $description,
                'icon' => $customIcon !== '' ? $customIcon : $selectedIcon,
            ];
        }

        return $cards;
    }

    /**
     * Curated Material Symbols list for Core Values cards.
     *
     * Why this list exists:
     * - Editors should not have to memorize icon names.
     * - The list is broad enough to cover most values / principles / culture
     *   messaging without needing the custom override often.
     *
     * @return array<string, string>
     */
    protected function iconChoices(): array
    {
        return [
            'diversity_3' => 'Diversity 3 — Community / Inclusion',
            'groups' => 'Groups — Community / Togetherness',
            'handshake' => 'Handshake — Respect / Partnership',
            'security' => 'Security — Safety / Responsibility',
            'volunteer_activism' => 'Volunteer Activism — Giving Back / Charity',
            'favorite' => 'Favorite — Care / Passion',
            'forum' => 'Forum — Conversation / Voice',
            'support' => 'Support — Member Support',
            'verified' => 'Verified — Integrity / Standards',
            'workspace_premium' => 'Workspace Premium — Excellence',
            'emoji_events' => 'Emoji Events — Recognition / Achievement',
            'military_tech' => 'Military Tech — Honor / Badge',
            'shield' => 'Shield — Protection / Safety',
            'public' => 'Public — Openness / Community Presence',
            'balance' => 'Balance — Fairness',
            'diversity_1' => 'Diversity 1 — Inclusion',
            'family_restroom' => 'Family Restroom — Family Friendly',
            'school' => 'School — Learning / Mentorship',
            'build' => 'Build — Effort / Craft',
            'engineering' => 'Engineering — Technical Mindset',
            'precision_manufacturing' => 'Precision Manufacturing — Skill / Craftsmanship',
            'speed' => 'Speed — Performance',
            'route' => 'Route — Journey / Direction',
            'flag' => 'Flag — Principle / Mission',
            'lightbulb' => 'Lightbulb — Vision / Ideas',
            'auto_awesome' => 'Auto Awesome — Inspiration',
            'rocket_launch' => 'Rocket Launch — Progress / Ambition',
            'campaign' => 'Campaign — Outreach / Mission',
            'people' => 'People — Community',
            'favorite_border' => 'Favorite Border — Care / Passion',
        ];
    }
}