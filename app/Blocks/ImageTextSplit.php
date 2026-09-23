<?php

/**
 * ============================================================================
 * File path + filename: app/Blocks/ImageTextSplit.php
 * ============================================================================
 *
 * Purpose:
 * - Register a flexible ACF Composer Gutenberg block for a split layout with
 *   editable media on one side and nested Gutenberg blocks on the other.
 *
 * Why this file exists:
 * - The user wants a reusable section based on the "Origin Story" layout:
 *   text on one side, image on the other, with the ability to switch the
 *   image between left and right.
 * - The text area must support nested Gutenberg blocks so editors can use
 *   headings, paragraphs, buttons, lists, and similar content blocks.
 *
 * Editorial goals:
 * - Keep the text side flexible with one InnerBlocks area.
 * - Prefill the text area with a starter Heading + Paragraph template.
 * - Do not lock the template, so editors may remove or replace those starter
 *   blocks if they want a different setup.
 * - Add section-level controls for full-width presentation, container width,
 *   spacing, background treatments, headline underline spacing, and optional
 *   call-to-action links without changing the core split layout pattern.
 *
 * Image handling:
 * - The main image field returns the full ACF image array.
 * - Alt text is read from the image array that WordPress stores on the
 *   attachment, so no separate alt field is needed.
 * - The optional background image is only used by the parallax and scroller
 *   background treatments.
 *
 * Styling goals:
 * - Match the reference layout closely:
 *   - full-width section shell
 *   - inner content constrained to a selectable Tailwind-style container width
 *   - two-column desktop layout
 *   - text block on one side
 *   - image card on the other
 *   - blue/neon gradient underline effect under the opening heading
 * - Keep all block-specific CSS inside the Blade view.
 *
 * Important InnerBlocks note:
 * - This block still defaults to `preview` mode so the nested InnerBlocks
 *   content remains visible and editable in the canvas.
 * - The ACF edit/preview toolbar toggle is enabled so editors can open the
 *   block field editor directly inside the block when they need to adjust
 *   layout, spacing, background, and CTA settings.
 *
 * Assumptions:
 * - ACF Composer block discovery is already working in the Sage 11 theme.
 * - The Material Symbols font and theme typography are already loaded by
 *   the theme globally.
 * - The Masthead-style "scroller" request means a slow animated background pan
 *   using the selected background image, not a separate carousel/gallery.
 * - Background image controls are conditional, so parallax-only and
 *   scroller-only settings do not clutter the editor unless that treatment is
 *   selected.
 */

namespace App\Blocks;

use Log1x\AcfComposer\Block;
use Log1x\AcfComposer\Builder;

class ImageTextSplit extends Block
{
    /**
     * Block name shown in the inserter.
     *
     * @var string
     */
    public $name = 'Image / Text Split';

    /**
     * Block description shown in the inserter.
     *
     * @var string
     */
    public $description = 'A flexible split section with an image on one side and nested text blocks on the other.';

    /**
     * Stable slug for the block.
     *
     * @var string
     */
    public $slug = 'image-text-split';

    /**
     * Gutenberg category.
     *
     * Why layout:
     * - This is a full page/content section block.
     *
     * @var string
     */
    public $category = 'layout';

    /**
     * Dashicon shown in the inserter.
     *
     * @var string|array
     */
    public $icon = 'align-pull-right';

    /**
     * Search keywords for the block inserter.
     *
     * @var array<int, string>
     */
    public $keywords = ['split', 'content', 'image', 'origin story', 'about'];

    /**
     * Blade view used to render the block.
     *
     * @var string
     */
    public $view = 'blocks.image-text-split';

    /**
     * Default editor mode.
     *
     * Why preview:
     * - ACF preview mode keeps the render template visible by default.
     * - This keeps the InnerBlocks content editable in the canvas first.
     * - Editors can still use the block toolbar toggle to switch into the
     *   ACF field editor inside the block because `supports['mode']` is true.
     *
     * @var string
     */
    public $mode = 'preview';

    /**
     * Block support settings.
     *
     * Important:
     * - `jsx` must be enabled for ACF Blocks InnerBlocks support to work
     *   correctly in the editor.
     * - `mode` stays enabled so editors can toggle between the rendered preview
     *   and the ACF field editor inside the block.
     *
     * @var array<string, mixed>
     */
    public $supports = [
        'align' => false,
        'align_text' => false,
        'align_content' => false,
        'anchor' => true,
        'multiple' => true,
        'jsx' => true,
        'mode' => true,
    ];

    /**
     * Data passed into the Blade view.
     *
     * @return array<string, mixed>
     */
    public function with(): array
    {
        return [
            'image' => $this->imageField(),
            'imagePosition' => $this->stringField('image_position', 'right'),
            'containerWidth' => $this->stringField('container_width', 'standard'),
            'sectionPaddingTop' => $this->stringField('section_padding_top', 'm'),
            'sectionPaddingBottom' => $this->stringField('section_padding_bottom', 'm'),
            'sectionMarginTop' => $this->stringField('section_margin_top', 's'),
            'sectionMarginBottom' => $this->stringField('section_margin_bottom', 's'),
            'backgroundTreatment' => $this->backgroundTreatment(),
            'backgroundImage' => $this->backgroundImageField(),
            'parallaxSpeed' => $this->numberField('parallax_speed', 4, 1, 10),
            'scrollerSpeed' => $this->numberField('scroller_speed', 4, 1, 10),
            'scrollerDirection' => $this->stringField('scroller_direction', 'top_to_bottom'),
            'backgroundOverlayColor' => $this->colorField('background_overlay_color', '#020617'),
            'backgroundOverlayOpacity' => $this->numberField('background_overlay_opacity', 55, 0, 90),
            'headlineMarginBottom' => $this->stringField('headline_margin_bottom', 'm'),
            'ctaCount' => $this->stringField('cta_count', 'none'),
            'primaryCta' => $this->linkField('primary_cta'),
            'secondaryCta' => $this->linkField('secondary_cta'),
            'allowedBlocks' => $this->allowedBlocks(),
            'innerBlocksTemplate' => $this->innerBlocksTemplate(),
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
        $fields = Builder::make('image_text_split');

        $fields
            ->addImage('image', [
                'label' => 'Section Image',
                'instructions' => 'Choose the image shown on the media side of the split layout.',
                'return_format' => 'array',
                'preview_size' => 'large',
                'library' => 'all',
                'required' => 0,
                'wrapper' => [
                    'width' => '70',
                ],
            ])
            ->addRadio('image_position', [
                'label' => 'Image Position',
                'instructions' => 'Choose whether the image appears on the left or right on larger screens.',
                'choices' => [
                    'left' => 'Image Left',
                    'right' => 'Image Right',
                ],
                'default_value' => 'right',
                'layout' => 'horizontal',
                'required' => 1,
                'wrapper' => [
                    'width' => '30',
                ],
            ])
            ->addRadio('container_width', [
                'label' => 'Container Width',
                'instructions' => 'The section remains full width, but the inner content is constrained to this Tailwind-style container width.',
                'choices' => [
                    'narrow' => 'Narrow',
                    'standard' => 'Standard',
                    'wide' => 'Wide',
                ],
                'default_value' => 'standard',
                'layout' => 'horizontal',
                'required' => 1,
                'wrapper' => [
                    'width' => '50',
                ],
            ])
            ->addField('background_treatment', 'select', [
                'label' => 'Background Treatment',
                'instructions' => 'Choose a simple theme background or an image-based background treatment. Image-only controls appear only when Parallax Image or Scroller Image is selected.',
                'choices' => [
                    'transparent' => 'Transparent',
                    'surface' => 'Surface Panel',
                    'gradient' => 'Subtle Neon Gradient',
                    'dark_panel' => 'Dark Cinematic Panel',
                    'parallax' => 'Parallax Image',
                    'scroller' => 'Scroller Image',
                ],
                'default_value' => 'transparent',
                'ui' => 1,
                'return_format' => 'value',
                'required' => 1,
                'wrapper' => [
                    'width' => '50',
                ],
            ])
            ->addImage('background_image', [
                'label' => 'Background Image',
                'instructions' => 'Used when Background Treatment is set to Parallax Image or Scroller Image.',
                'return_format' => 'array',
                'preview_size' => 'large',
                'library' => 'all',
                'required' => 0,
                'wrapper' => [
                    'width' => '50',
                ],
            ])
                ->conditional('background_treatment', '==', 'parallax')
                    ->or('background_treatment', '==', 'scroller')
            ->addField('parallax_speed', 'range', [
                'label' => 'Parallax Speed',
                'instructions' => 'Controls how strongly the background image moves while scrolling. Lower is calmer, higher is more noticeable.',
                'min' => 1,
                'max' => 10,
                'step' => 1,
                'default_value' => 4,
                'required' => 1,
                'wrapper' => [
                    'width' => '50',
                ],
            ])
                ->conditional('background_treatment', '==', 'parallax')
            ->addField('scroller_speed', 'range', [
                'label' => 'Scroller Speed',
                'instructions' => 'Controls the animated background pan speed. Lower is slower, higher is faster.',
                'min' => 1,
                'max' => 10,
                'step' => 1,
                'default_value' => 4,
                'required' => 1,
                'wrapper' => [
                    'width' => '50',
                ],
            ])
                ->conditional('background_treatment', '==', 'scroller')
            ->addField('scroller_direction', 'select', [
                'label' => 'Scroller Direction',
                'instructions' => 'Choose the direction the background image pans.',
                'choices' => [
                    'top_to_bottom' => 'Top to Bottom',
                    'bottom_to_top' => 'Bottom to Top',
                ],
                'default_value' => 'top_to_bottom',
                'ui' => 1,
                'return_format' => 'value',
                'required' => 1,
                'wrapper' => [
                    'width' => '50',
                ],
            ])
                ->conditional('background_treatment', '==', 'scroller')
            ->addField('background_overlay_color', 'color_picker', [
                'label' => 'Background Overlay Color',
                'instructions' => 'Optional color overlay for parallax/scroller backgrounds. Useful when the image is too bright.',
                'default_value' => '#020617',
                'required' => 0,
                'wrapper' => [
                    'width' => '50',
                ],
            ])
                ->conditional('background_treatment', '==', 'parallax')
                    ->or('background_treatment', '==', 'scroller')
            ->addField('background_overlay_opacity', 'range', [
                'label' => 'Background Overlay Opacity',
                'instructions' => 'Controls how strong the color overlay is. Use 0 for no overlay.',
                'min' => 0,
                'max' => 90,
                'step' => 5,
                'default_value' => 55,
                'required' => 1,
                'wrapper' => [
                    'width' => '50',
                ],
            ])
                ->conditional('background_treatment', '==', 'parallax')
                    ->or('background_treatment', '==', 'scroller')
            ->addRadio('section_padding_top', [
                'label' => 'Section Padding Top',
                'instructions' => 'Controls the top padding on the full-width section.',
                'choices' => $this->spacingChoices(),
                'default_value' => 'm',
                'layout' => 'horizontal',
                'required' => 1,
                'wrapper' => [
                    'width' => '25',
                ],
            ])
            ->addRadio('section_padding_bottom', [
                'label' => 'Section Padding Bottom',
                'instructions' => 'Controls the bottom padding on the full-width section.',
                'choices' => $this->spacingChoices(),
                'default_value' => 'm',
                'layout' => 'horizontal',
                'required' => 1,
                'wrapper' => [
                    'width' => '25',
                ],
            ])
            ->addRadio('section_margin_top', [
                'label' => 'Section Margin Top',
                'instructions' => 'Controls the top margin outside the full-width section.',
                'choices' => $this->spacingChoices(),
                'default_value' => 's',
                'layout' => 'horizontal',
                'required' => 1,
                'wrapper' => [
                    'width' => '25',
                ],
            ])
            ->addRadio('section_margin_bottom', [
                'label' => 'Section Margin Bottom',
                'instructions' => 'Controls the bottom margin outside the full-width section.',
                'choices' => $this->spacingChoices(),
                'default_value' => 's',
                'layout' => 'horizontal',
                'required' => 1,
                'wrapper' => [
                    'width' => '25',
                ],
            ])
            ->addRadio('headline_margin_bottom', [
                'label' => 'Headline Margin Bottom',
                'instructions' => 'Controls the margin below the opening headline and its gradient underline.',
                'choices' => [
                    'none' => 'None',
                    's' => 'Small',
                    'm' => 'Medium',
                    'l' => 'Large',
                    'xl' => 'Extra Large',
                ],
                'default_value' => 'm',
                'layout' => 'horizontal',
                'required' => 1,
                'wrapper' => [
                    'width' => '100',
                ],
            ])
            ->addRadio('cta_count', [
                'label' => 'CTA Buttons',
                'instructions' => 'Choose whether this section should show no CTA, one CTA, or two CTAs below the text content.',
                'choices' => [
                    'none' => 'No CTAs',
                    'one' => 'One CTA',
                    'two' => 'Two CTAs',
                ],
                'default_value' => 'none',
                'layout' => 'horizontal',
                'required' => 1,
                'wrapper' => [
                    'width' => '100',
                ],
            ])
            ->addField('primary_cta', 'link', [
                'label' => 'Primary CTA',
                'instructions' => 'Primary CTA uses the neon border button style and appears under the text content.',
                'return_format' => 'array',
                'required' => 0,
                'wrapper' => [
                    'width' => '50',
                ],
            ])
            ->addField('secondary_cta', 'link', [
                'label' => 'Secondary CTA',
                'instructions' => 'Secondary CTA uses a quieter supporting style and appears under the text content.',
                'return_format' => 'array',
                'required' => 0,
                'wrapper' => [
                    'width' => '50',
                ],
            ]);

        return $fields->build();
    }

    /**
     * Return a safe background treatment value.
     *
     * Why this helper exists:
     * - Earlier iterations used default, muted, and dark values.
     * - The editor now uses the cleaner non-image treatments from the Event
     *   Impact block: transparent, surface, gradient, and dark_panel.
     * - Aliases keep older saved block instances from breaking.
     *
     * @return string
     */
    protected function backgroundTreatment(): string
    {
        $value = function_exists('get_field') ? get_field('background_treatment') : null;

        if (! is_string($value)) {
            return 'transparent';
        }

        $value = trim($value);

        $aliases = [
            'default' => 'transparent',
            'muted' => 'surface',
            'dark' => 'dark_panel',
        ];

        if (isset($aliases[$value])) {
            return $aliases[$value];
        }

        $allowed = [
            'transparent',
            'surface',
            'gradient',
            'dark_panel',
            'parallax',
            'scroller',
        ];

        return in_array($value, $allowed, true) ? $value : 'transparent';
    }

    /**
     * Shared spacing choices for section padding and margin fields.
     *
     * Why keywords:
     * - The editor chooses simple semantic spacing options instead of raw CSS
     *   values, keeping the block easier to manage over time.
     *
     * @return array<string, string>
     */
    protected function spacingChoices(): array
    {
        return [
            'none' => 'None',
            's' => 'Small',
            'm' => 'Medium',
            'l' => 'Large',
            'xl' => 'Extra Large',
            'xxl' => 'XXL',
        ];
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
     * Normalize a numeric field into a clamped float.
     *
     * Why this helper exists:
     * - ACF range fields may return strings depending on context.
     * - CSS motion values should stay inside the expected editor range.
     *
     * @param  string  $key
     * @param  float|int  $default
     * @param  float|int  $min
     * @param  float|int  $max
     * @return float
     */
    protected function numberField(string $key, float|int $default, float|int $min, float|int $max): float
    {
        $value = function_exists('get_field') ? get_field($key) : null;

        if (! is_numeric($value)) {
            return (float) $default;
        }

        $value = (float) $value;

        if ($value < $min) {
            return (float) $min;
        }

        if ($value > $max) {
            return (float) $max;
        }

        return $value;
    }

    /**
     * Normalize a color picker field into a safe hex color.
     *
     * @param  string  $key
     * @param  string  $default
     * @return string
     */
    protected function colorField(string $key, string $default = '#020617'): string
    {
        $value = function_exists('get_field') ? get_field($key) : null;

        if (! is_string($value)) {
            return $default;
        }

        $value = trim($value);

        return preg_match('/^#[0-9A-Fa-f]{6}$/', $value) === 1 ? $value : $default;
    }

    /**
     * Return the main image field only if it is a valid image array.
     *
     * Why this helper exists:
     * - Keeps view logic simple.
     * - Ensures we only pass structured image data into the template.
     *
     * @return array<string, mixed>|null
     */
    protected function imageField(): ?array
    {
        $image = function_exists('get_field') ? get_field('image') : null;

        if (! is_array($image) || empty($image['ID'])) {
            return null;
        }

        return $image;
    }

    /**
     * Return the optional background image field only when it is valid.
     *
     * Why this is separate from the main image helper:
     * - The main image belongs to the split layout.
     * - The background image belongs to the section treatment only.
     *
     * @return array<string, mixed>|null
     */
    protected function backgroundImageField(): ?array
    {
        $image = function_exists('get_field') ? get_field('background_image') : null;

        if (! is_array($image) || empty($image['ID'])) {
            return null;
        }

        return $image;
    }

    /**
     * Normalize an ACF link field into the small shape the view needs.
     *
     * Why this helper exists:
     * - Keeps link validation in PHP instead of spreading checks through Blade.
     * - Prevents empty CTA buttons from rendering when the editor has not
     *   supplied a valid link target.
     *
     * @param  string  $key
     * @return array{title: string, url: string, target: string}|null
     */
    protected function linkField(string $key): ?array
    {
        $link = function_exists('get_field') ? get_field($key) : null;

        if (! is_array($link)) {
            return null;
        }

        $url = trim((string) ($link['url'] ?? ''));
        $title = trim((string) ($link['title'] ?? ''));
        $target = trim((string) ($link['target'] ?? ''));

        if ($url === '' || $title === '') {
            return null;
        }

        return [
            'title' => $title,
            'url' => $url,
            'target' => $target !== '' ? $target : '_self',
        ];
    }

    /**
     * Allowed blocks for the InnerBlocks content area.
     *
     * Why this list exists:
     * - The text side should stay flexible.
     * - We allow a curated set of useful content blocks rather than opening
     *   the area to every block type.
     *
     * @return array<int, string>
     */
    protected function allowedBlocks(): array
    {
        return [
            'core/heading',
            'core/paragraph',
            'core/list',
            'core/buttons',
            'core/group',
            'core/spacer',
            'core/separator',
            'core/quote',
        ];
    }

    /**
     * Starter template for the InnerBlocks area.
     *
     * Why this template exists:
     * - Gives editors a sensible starting point.
     * - Because templateLock is intentionally omitted, the starter blocks remain
     *   fully removable and replaceable.
     *
     * @return array<int, array<int|string, mixed>>
     */
    protected function innerBlocksTemplate(): array
    {
        return [
            [
                'core/heading',
                [
                    'level' => 2,
                    'placeholder' => 'Add section heading',
                ],
            ],
            [
                'core/paragraph',
                [
                    'placeholder' => 'Add supporting text for this section...',
                ],
            ],
        ];
    }
}