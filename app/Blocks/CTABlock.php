<?php

/**
 * File path + filename: app/Blocks/CTABlock.php
 *
 * Purpose:
 * - Register a reusable Gutenberg CTA block.
 * - Allow editors to customize the callout text, text alignment, spacing,
 *   visual theme, optional background image, overlay treatment, and whether
 *   the block shows one or two buttons.
 *
 * Why this file exists:
 * - The user wants a fully generic CTA block that can be reused anywhere.
 * - The block should support Gutenberg's native full-width alignment instead of
 *   a custom full-width toggle.
 * - The block should let editors control top/bottom margin and padding without
 *   touching code.
 * - The block should offer a small set of curated visual themes that remain
 *   readable in both light and dark mode.
 *
 * Editor controls:
 * - Eyebrow
 * - Heading
 * - Body copy
 * - Alignment (left / center)
 * - Theme (3 curated options)
 * - Margin top / bottom
 * - Padding top / bottom
 * - Primary button label + URL
 * - Optional secondary button toggle + label + URL
 * - Optional background image
 * - Theme-safe background overlay color
 * - Background overlay opacity
 *
 * Notes:
 * - This block remains self-contained so it can be placed anywhere.
 * - The front-end markup lives in a Blade view to match the current Sage setup.
 * - The secondary button only renders when enabled and when both label and URL
 *   are provided.
 * - Background images are optional and always render with background-size: cover.
 * - Overlay colors are curated choices instead of a free-form color picker so
 *   editors cannot accidentally create unreadable light/dark theme contrast.
 * - The slug and view are defined explicitly so the acronym-based class name
 *   does not generate an awkward auto slug.
 * - Full-width is now handled by Gutenberg's native block alignment support.
 */

namespace App\Blocks;

use Log1x\AcfComposer\Block;
use Log1x\AcfComposer\Builder;

class CTABlock extends Block
{
    /**
     * File path + filename: app/Blocks/CTABlock.php
     *
     * Block editor label.
     *
     * @var string
     */
    public $name = 'CTA Block';

    /**
     * Stable block slug.
     *
     * @var string
     */
    public $slug = 'cta-block';

    /**
     * Blade view used to render the block.
     *
     * @var string
     */
    public $view = 'blocks.cta-block';

    /**
     * Block editor description.
     *
     * @var string
     */
    public $description = 'Reusable callout block with editable text, layout, spacing, themes, and one or two buttons.';

    /**
     * Gutenberg block supports.
     *
     * Why this exists:
     * - Use the native Gutenberg full-width control instead of a custom toggle.
     * - Keeping this limited to "full" gives editors the intended on/off choice:
     *   normal contained width or full-width presentation.
     *
     * @var array
     */
    public $supports = [
        'align' => ['full'],
    ];

    /**
     * Gutenberg category.
     *
     * @var string
     */
    public $category = 'widgets';

    /**
     * Dashicon slug used in the inserter.
     *
     * @var string
     */
    public $icon = 'megaphone';

    /**
     * Search keywords inside the block inserter.
     *
     * @var array<int, string>
     */
    public $keywords = ['cta', 'callout', 'buttons', 'section', 'action'];

    /**
     * Data passed into the Blade view.
     *
     * Why this exists:
     * - Keeps the Blade file focused on markup and presentation.
     * - Normalizes editor values before they reach the template.
     *
     * @return array<string, mixed>
     */
    public function with(): array
    {
        $primaryLabel = trim((string) get_field('primary_button_label'));
        $primaryUrl = trim((string) get_field('primary_button_url'));

        $showSecondary = (bool) get_field('show_secondary_button');
        $secondaryLabel = trim((string) get_field('secondary_button_label'));
        $secondaryUrl = trim((string) get_field('secondary_button_url'));

        return [
            'eyebrow' => trim((string) get_field('eyebrow')),
            'heading' => trim((string) get_field('heading')) ?: 'Ready to take the next step?',
            'body' => trim((string) get_field('body')),
            'alignment' => $this->alignment(),
            'theme' => $this->theme(),
            'marginTop' => $this->marginTop(),
            'marginBottom' => $this->marginBottom(),
            'paddingTop' => $this->paddingTop(),
            'paddingBottom' => $this->paddingBottom(),
            'backgroundImageUrl' => $this->backgroundImageUrl(),
            'backgroundOverlayColor' => $this->backgroundOverlayColor(),
            'backgroundOverlayOpacity' => $this->backgroundOverlayOpacity(),
            'primaryButton' => [
                'label' => $primaryLabel,
                'url' => $primaryUrl,
                'enabled' => $primaryLabel !== '' && $primaryUrl !== '',
            ],
            'secondaryButton' => [
                'label' => $secondaryLabel,
                'url' => $secondaryUrl,
                'enabled' => $showSecondary && $secondaryLabel !== '' && $secondaryUrl !== '',
            ],
        ];
    }

    /**
     * Register the editor fields for this block.
     *
     * Why these fields exist:
     * - Keep the CTA fully editor-managed.
     * - Let editors choose whether the layout uses one button or two without
     *   needing code changes.
     * - Let editors control layout and spacing in a predictable way.
     *
     * @return array<string, mixed>
     */
    public function fields(): array
    {
        $fields = Builder::make('cta_block');

        $fields
            ->addText('eyebrow', [
                'label' => 'Eyebrow',
                'default_value' => 'Call to Action',
                'wrapper' => [
                    'width' => '35',
                ],
            ])
            ->addButtonGroup('alignment', [
                'label' => 'Alignment',
                'choices' => [
                    'left' => 'Left',
                    'center' => 'Center',
                ],
                'default_value' => 'center',
                'return_format' => 'value',
                'layout' => 'horizontal',
                'wrapper' => [
                    'width' => '25',
                ],
            ])
            ->addButtonGroup('theme', [
                'label' => 'Theme',
                'choices' => [
                    'cobalt' => 'Cobalt',
                    'slate' => 'Slate',
                    'aurora' => 'Aurora',
                ],
                'default_value' => 'cobalt',
                'return_format' => 'value',
                'layout' => 'horizontal',
                'wrapper' => [
                    'width' => '40',
                ],
            ])
            ->addImage('background_image', [
                'label' => 'Background Image',
                'instructions' => 'Optional. When set, the CTA background image will cover the card area.',
                'return_format' => 'id',
                'preview_size' => 'medium',
                'library' => 'all',
                'wrapper' => [
                    'width' => '35',
                ],
            ])
            ->addButtonGroup('background_overlay_color', [
                'label' => 'Image Overlay Color',
                'instructions' => 'Curated overlay colors keep the CTA readable in light and dark theme.',
                'choices' => $this->backgroundOverlayColorChoices(),
                'default_value' => 'dark',
                'return_format' => 'value',
                'layout' => 'horizontal',
                'wrapper' => [
                    'width' => '40',
                ],
            ])
            ->addNumber('background_overlay_opacity', [
                'label' => 'Image Overlay Opacity',
                'instructions' => 'Enter a value from 0 to 100. 0 is transparent. 100 is solid.',
                'default_value' => 58,
                'min' => 0,
                'max' => 100,
                'step' => 1,
                'append' => '%',
                'wrapper' => [
                    'width' => '25',
                ],
            ])
            ->addText('heading', [
                'label' => 'Heading',
                'default_value' => 'Ready to take the next step?',
                'wrapper' => [
                    'width' => '100',
                ],
            ])
            ->addTextarea('body', [
                'label' => 'Body',
                'rows' => 4,
                'new_lines' => 'br',
                'default_value' => 'Use this callout to highlight an important action, destination, or opportunity for your visitors.',
                'wrapper' => [
                    'width' => '100',
                ],
            ])
            ->addButtonGroup('margin_top', [
                'label' => 'Margin Top',
                'choices' => $this->spacingChoices(),
                'default_value' => 'none',
                'return_format' => 'value',
                'layout' => 'horizontal',
                'wrapper' => [
                    'width' => '25',
                ],
            ])
            ->addButtonGroup('margin_bottom', [
                'label' => 'Margin Bottom',
                'choices' => $this->spacingChoices(),
                'default_value' => 'none',
                'return_format' => 'value',
                'layout' => 'horizontal',
                'wrapper' => [
                    'width' => '25',
                ],
            ])
            ->addButtonGroup('padding_top', [
                'label' => 'Padding Top',
                'choices' => $this->spacingChoices(),
                'default_value' => 'md',
                'return_format' => 'value',
                'layout' => 'horizontal',
                'wrapper' => [
                    'width' => '25',
                ],
            ])
            ->addButtonGroup('padding_bottom', [
                'label' => 'Padding Bottom',
                'choices' => $this->spacingChoices(),
                'default_value' => 'md',
                'return_format' => 'value',
                'layout' => 'horizontal',
                'wrapper' => [
                    'width' => '25',
                ],
            ])
            ->addText('primary_button_label', [
                'label' => 'Primary Button Label',
                'default_value' => 'Get Started',
                'wrapper' => [
                    'width' => '30',
                ],
            ])
            ->addText('primary_button_url', [
                'placeholder' => 'Full URL (https://)',
                'label' => 'Primary Button URL',
                'wrapper' => [
                    'width' => '45',
                ],
            ])
            ->addTrueFalse('show_secondary_button', [
                'label' => 'Show Secondary Button',
                'ui' => 1,
                'default_value' => 1,
                'wrapper' => [
                    'width' => '25',
                ],
            ])
            ->addText('secondary_button_label', [
                'label' => 'Secondary Button Label',
                'default_value' => 'Learn More',
                'conditional_logic' => [
                    [
                        [
                            'field' => 'show_secondary_button',
                            'operator' => '==',
                            'value' => '1',
                        ],
                    ],
                ],
                'wrapper' => [
                    'width' => '30',
                ],
            ])
            ->addText('secondary_button_url', [
                'label' => 'Secondary Button URL',
                'placeholder' => 'Full URL (https://)',
                'conditional_logic' => [
                    [
                        [
                            'field' => 'show_secondary_button',
                            'operator' => '==',
                            'value' => '1',
                        ],
                    ],
                ],
                'wrapper' => [
                    'width' => '45',
                ],
            ]);

        return $fields->build();
    }

    /**
     * Validate the selected text alignment.
     */
    protected function alignment(): string
    {
        $alignment = trim((string) get_field('alignment'));

        return in_array($alignment, ['left', 'center'], true) ? $alignment : 'center';
    }

    /**
     * Validate the selected visual theme.
     */
    protected function theme(): string
    {
        $theme = trim((string) get_field('theme'));

        return in_array($theme, ['cobalt', 'slate', 'aurora'], true) ? $theme : 'cobalt';
    }

    /**
     * Validated top margin choice.
     */
    protected function marginTop(): string
    {
        return $this->validatedSpacingChoice((string) get_field('margin_top'), 'none');
    }

    /**
     * Validated bottom margin choice.
     */
    protected function marginBottom(): string
    {
        return $this->validatedSpacingChoice((string) get_field('margin_bottom'), 'none');
    }

    /**
     * Validated top padding choice.
     */
    protected function paddingTop(): string
    {
        return $this->validatedSpacingChoice((string) get_field('padding_top'), 'md');
    }

    /**
     * Validated bottom padding choice.
     */
    protected function paddingBottom(): string
    {
        return $this->validatedSpacingChoice((string) get_field('padding_bottom'), 'md');
    }

    /**
     * Resolve the optional CTA background image URL.
     *
     * Why this exists:
     * - The ACF image field returns an attachment ID so WordPress can generate
     *   the correct URL for the requested image size.
     * - Returning null keeps the Blade template from rendering empty background
     *   image markup when no image has been selected.
     */
    protected function backgroundImageUrl(): ?string
    {
        $imageId = absint(get_field('background_image'));

        if ($imageId === 0) {
            return null;
        }

        $imageUrl = wp_get_attachment_image_url($imageId, 'full');

        return $imageUrl !== false ? $imageUrl : null;
    }

    /**
     * Validate the selected background overlay color.
     *
     * Why this exists:
     * - Overlay colors are intentionally curated instead of open-ended.
     * - This protects contrast and readability across light and dark theme.
     */
    protected function backgroundOverlayColor(): string
    {
        $color = trim((string) get_field('background_overlay_color'));
        $allowed = array_keys($this->backgroundOverlayColorChoices());

        return in_array($color, $allowed, true) ? $color : 'dark';
    }

    /**
     * Normalize the overlay opacity field into a CSS-friendly decimal value.
     *
     * Why this exists:
     * - Editors think in percentages.
     * - CSS opacity expects a decimal between 0 and 1.
     */
    protected function backgroundOverlayOpacity(): float
    {
        $opacity = get_field('background_overlay_opacity');

        if ($opacity === null || $opacity === '') {
            return 0.58;
        }

        $opacity = max(0, min(100, (int) $opacity));

        return round($opacity / 100, 2);
    }

    /**
     * Theme-safe overlay choices for background images.
     *
     * @return array<string, string>
     */
    protected function backgroundOverlayColorChoices(): array
    {
        return [
            'dark' => 'Dark',
            'blue' => 'Blue',
            'accent' => 'Accent',
            'light' => 'Light',
        ];
    }

    /**
     * Editor choices for margin and padding spacing.
     *
     * @return array<string, string>
     */
    protected function spacingChoices(): array
    {
        return [
            'none' => 'None',
            'sm' => 'S',
            'md' => 'M',
            'lg' => 'L',
            'xl' => 'XL',
        ];
    }

    /**
     * Validate spacing values coming from the editor.
     */
    protected function validatedSpacingChoice(string $value, string $fallback = 'md'): string
    {
        $allowed = array_keys($this->spacingChoices());

        return in_array($value, $allowed, true) ? $value : $fallback;
    }
}