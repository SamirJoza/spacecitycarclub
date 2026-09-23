<?php

/**
 * ============================================================================
 * File path + filename: app/Blocks/Container.php
 * ============================================================================
 *
 * Purpose:
 * - Provide a simplified, more opinionated section/container Gutenberg block
 *   for Sage + ACF that can act as a general-purpose wrapper, hero section,
 *   CTA band container, or parallax image section.
 *
 * Why this file exists:
 * - The previous Container block had become too settings-heavy for editors.
 * - The user explicitly asked to reduce the UI complexity and move away from
 *   separate Desktop / Tablet / Mobile spacing controls.
 * - This version replaces that granular device-based model with a smaller,
 *   preset-driven control set.
 *
 * Key simplifications:
 * - Removed per-device spacing/alignment controls entirely.
 * - Removed parent-level content alignment because child blocks should control
 *   their own text alignment and layout behavior.
 * - Replaced the old background-only model with a mutually exclusive system:
 *   - no background treatment
 *   - theme-safe background treatment
 *   - background image + optional overlay
 *   - parallax image + optional overlay
 * - Presets now drive spacing and minimum-height behavior.
 *
 * Preset philosophy:
 * - Standard Section:
 *   General-purpose content wrapper with balanced vertical spacing.
 * - Hero / Banner:
 *   Larger vertical space and stronger stage-like presence.
 * - CTA Band:
 *   Centered, punchier promotional band behavior.
 * - Parallax Image:
 *   Always behaves like a large visual stage so background motion is visible.
 *
 * Text color philosophy:
 * - Explicit Gutenberg text color always wins if present.
 * - Otherwise the block uses the chosen text scheme:
 *   - inherit
 *   - auto
 *   - light
 *   - dark
 * - Auto is intentionally conservative:
 *   - background image mode defaults to light text
 *   - parallax image mode defaults to light text
 *   - midnight treatment defaults to light text
 *   - theme-safe treatments inherit the current theme text color
 *
 * Layout model:
 * - OUTER:
 *   background treatment OR image overlay OR parallax image overlay,
 *   preset spacing, text color
 * - INNER:
 *   width preset, vertical flow, InnerBlocks
 *
 * Assumptions:
 * - ACF Composer block discovery is already wired up in the Sage theme.
 * - The theme already loads typography, button styles, and general design
 *   tokens globally.
 */

namespace App\Blocks;

use Log1x\AcfComposer\Block;
use Log1x\AcfComposer\Builder;

class Container extends Block
{
    /**
     * Block name shown in the inserter.
     *
     * @var string
     */
    public $name = 'Container';

    /**
     * Block description shown in the inserter.
     *
     * @var string
     */
    public $description = 'Simplified section wrapper with presets, content width, background treatments, overlay, parallax image support, and InnerBlocks.';

    /**
     * Gutenberg category.
     *
     * @var string
     */
    public $category = 'theme-blocks';

    /**
     * Block icon.
     *
     * @var string|array
     */
    public $icon = 'align-wide';

    /**
     * Search keywords.
     *
     * @var array<int, string>
     */
    public $keywords = ['container', 'section', 'wrapper', 'hero', 'cta', 'parallax'];

    /**
     * Limit usage to posts and pages.
     *
     * @var array<int, string>
     */
    public $post_types = ['post', 'page'];

    /**
     * Keep the preview visible in the editor.
     *
     * @var string
     */
    public $mode = 'preview';

    /**
     * Gutenberg block supports.
     *
     * Why these supports remain:
     * - Anchor support remains useful.
     * - Gutenberg text/background/gradient controls remain available.
     * - JSX remains enabled because this block contains InnerBlocks.
     *
     * Important:
     * - ACF background mode is still treated as the primary editor-friendly
     *   background system for this block.
     * - Parallax mode uses a dedicated child layer so it can move independently
     *   from the section content.
     *
     * @var array<string, mixed>
     */
    public $supports = [
        'align' => ['wide', 'full'],
        'anchor' => true,
        'jsx' => true,
        'color' => [
            'background' => true,
            'text' => true,
            'gradients' => true,
        ],
    ];

    /**
     * Register the simplified ACF fields for the block.
     *
     * Why this field structure exists:
     * - It keeps the editing UI compact and readable.
     * - It prioritizes smart presets over manual device-by-device tuning.
     * - Background treatment, static image background, and parallax image
     *   background are mutually exclusive so editors do not accidentally stack
     *   conflicting design systems.
     *
     * @return array<string, mixed>
     */
    public function fields(): array
    {
        $f = Builder::make('container');

        $f
            ->addTab('General', ['placement' => 'top'])

            ->addRadio('container_size', [
                'label' => 'Container Size',
                'choices' => [
                    'bg-default-width' => 'Default',
                    'bg-full-width' => 'Full-width',
                ],
                'default_value' => 'bg-default-width',
                'layout' => 'horizontal',
            ])

            ->addSelect('section_preset', [
                'label' => 'Section Preset',
                'instructions' => 'Choose a smart preset instead of manually tuning spacing for each device.',
                'choices' => [
                    'standard' => 'Standard Section',
                    'hero_banner' => 'Hero / Banner',
                    'cta_band' => 'CTA Band',
                ],
                'default_value' => 'standard',
                'required' => 1,
                'wrapper' => [
                    'width' => '50',
                ],
            ])

            ->addSelect('container_tag', [
                'label' => 'Container Tag',
                'choices' => [
                    'section' => 'section',
                    'div' => 'div',
                ],
                'default_value' => 'section',
                'required' => 1,
                'wrapper' => [
                    'width' => '50',
                ],
            ])

            ->addSelect('content_width_preset', [
                'label' => 'Content Width',
                'instructions' => 'Controls the maximum width of the inner content shell.',
                'choices' => [
                    'narrow' => 'Narrow',
                    'standard' => 'Standard',
                    'wide' => 'Wide',
                    'full' => 'Full Width',
                ],
                'default_value' => 'standard',
                'required' => 1,
                'wrapper' => [
                    'width' => '50',
                ],
            ])

            ->addSelect('text_scheme', [
                'label' => 'Text Scheme',
                'instructions' => 'Use this when you need better readability on light or dark backgrounds.',
                'choices' => [
                    'inherit' => 'Inherit Theme Text',
                    'auto' => 'Auto',
                    'light' => 'Light Text',
                    'dark' => 'Dark Text',
                ],
                'default_value' => 'auto',
                'required' => 1,
                'wrapper' => [
                    'width' => '50',
                ],
            ])

            ->addTab('Background')

            ->addRadio('background_mode', [
                'label' => 'Background Mode',
                'instructions' => 'Choose one background system. Theme treatments, static image backgrounds, and parallax image backgrounds are intentionally mutually exclusive.',
                'choices' => [
                    'none' => 'None',
                    'treatment' => 'Theme Treatment',
                    'image' => 'Image + Overlay',
                    'parallax' => 'Parallax Image',
                ],
                'default_value' => 'none',
                'layout' => 'horizontal',
            ])

            ->addSelect('background_treatment', [
                'label' => 'Background Treatment',
                'instructions' => 'Theme-safe treatments designed to remain readable in light and dark mode.',
                'choices' => [
                    'surface' => 'Surface Panel',
                    'soft_glow' => 'Soft Club Glow',
                    'glass' => 'Glass Panel',
                    'midnight' => 'Midnight Neon',
                ],
                'default_value' => 'surface',
                'required' => 0,
            ])->conditional('background_mode', '==', 'treatment')

            ->addImage('background_image', [
                'label' => 'Background Image',
                'return_format' => 'id',
                'preview_size' => 'medium',
            ])->conditional('background_mode', '==', 'image')

            ->addSelect('background_position', [
                'label' => 'Background Position',
                'instructions' => 'Controls the focal point of the background image.',
                'choices' => [
                    'center center' => 'Center',
                    'top center' => 'Top Center',
                    'bottom center' => 'Bottom Center',
                    'left center' => 'Left Center',
                    'right center' => 'Right Center',
                ],
                'default_value' => 'center center',
                'required' => 0,
                'wrapper' => [
                    'width' => '50',
                ],
            ])->conditional('background_mode', '==', 'image')

            ->addTrueFalse('use_overlay', [
                'label' => 'Use Background Overlay?',
                'instructions' => 'Overlay is only available for image backgrounds.',
                'ui' => 1,
                'wrapper' => [
                    'width' => '50',
                ],
            ])->conditional('background_mode', '==', 'image')

            ->addColorPicker('overlay_color', [
                'label' => 'Overlay Color',
                'default_value' => '#0b1020',
                'wrapper' => [
                    'width' => '50',
                ],
            ])->conditional('use_overlay', '==', 1)

            ->addNumber('overlay_opacity', [
                'label' => 'Overlay Opacity (%)',
                'default_value' => 45,
                'min' => 0,
                'max' => 100,
                'step' => 1,
                'wrapper' => [
                    'width' => '50',
                ],
            ])->conditional('use_overlay', '==', 1)

            ->addImage('parallax_background_image', [
                'label' => 'Parallax Background Image',
                'return_format' => 'id',
                'preview_size' => 'medium',
            ])->conditional('background_mode', '==', 'parallax')

            ->addSelect('parallax_background_position', [
                'label' => 'Parallax Background Position',
                'instructions' => 'Controls the focal point of the parallax background image. The image size is intentionally locked to cover.',
                'choices' => [
                    'center center' => 'Center',
                    'top center' => 'Top Center',
                    'bottom center' => 'Bottom Center',
                    'left center' => 'Left Center',
                    'right center' => 'Right Center',
                ],
                'default_value' => 'center center',
                'required' => 0,
                'wrapper' => [
                    'width' => '50',
                ],
            ])->conditional('background_mode', '==', 'parallax')

            ->addNumber('parallax_speed', [
                'label' => 'Parallax Speed',
                'instructions' => 'Controls the vertical movement amount. 0 disables movement; 30 is balanced; 100 is strongest.',
                'default_value' => 30,
                'min' => 0,
                'max' => 100,
                'step' => 1,
                'wrapper' => [
                    'width' => '50',
                ],
            ])->conditional('background_mode', '==', 'parallax')

            ->addTrueFalse('parallax_monochrome', [
                'label' => 'Make Parallax Image Monochrome?',
                'instructions' => 'Applies a black-and-white grayscale treatment to the parallax background image only. Content and overlay remain unaffected.',
                'ui' => 1,
                'default_value' => 0,
                'wrapper' => [
                    'width' => '50',
                ],
            ])->conditional('background_mode', '==', 'parallax')

            ->addTrueFalse('parallax_use_overlay', [
                'label' => 'Use Parallax Overlay?',
                'instructions' => 'Optional tint above the parallax image and below the content.',
                'ui' => 1,
                'wrapper' => [
                    'width' => '50',
                ],
            ])->conditional('background_mode', '==', 'parallax')

            ->addColorPicker('parallax_overlay_color', [
                'label' => 'Parallax Overlay Color',
                'default_value' => '#0b1020',
                'wrapper' => [
                    'width' => '50',
                ],
            ])->conditional('parallax_use_overlay', '==', 1)

            ->addNumber('parallax_overlay_opacity', [
                'label' => 'Parallax Overlay Opacity (%)',
                'default_value' => 45,
                'min' => 0,
                'max' => 100,
                'step' => 1,
                'wrapper' => [
                    'width' => '50',
                ],
            ])->conditional('parallax_use_overlay', '==', 1);

        return $f->build();
    }

    /**
     * Build the Blade context for the block.
     *
     * @return array<string, mixed>
     */
    public function with(): array
    {
        $b = $this->blockArray();

        $blockId = $b['id'] ?? uniqid('container-');
        $tag = get_field('container_tag') ?: 'section';
        $containerSize = get_field('container_size') ?: 'bg-default-width';
        $sectionPreset = get_field('section_preset') ?: 'standard';
        $contentWidthPreset = get_field('content_width_preset') ?: 'standard';
        $backgroundMode = get_field('background_mode') ?: 'none';
        $backgroundTreatment = get_field('background_treatment') ?: 'surface';
        $textScheme = get_field('text_scheme') ?: 'auto';

        $gbGradient = $this->gbGradient();
        $gbText = $this->gbTextColor();
        $gbBackground = $this->gbBackgroundColor();
        $hasBackgroundImage = $this->hasBackgroundImage();
        $hasParallaxBackground = $this->hasParallaxBackgroundImage();
        $hasImageLikeBackground = $hasBackgroundImage || $hasParallaxBackground;

        $backgroundStyle = $this->backgroundStyle($gbGradient, $gbBackground, $backgroundMode);
        $overlayStyle = $this->overlayStyle($backgroundMode);
        $hasOverlay = $overlayStyle !== '';
        $parallaxBackgroundStyle = $this->parallaxBackgroundStyle($backgroundMode);
        $parallaxSpeed = $this->parallaxSpeed();
        $parallaxMonochrome = $this->parallaxMonochrome();

        $resolvedTextColor = $this->resolvedTextColor(
            $gbText,
            $textScheme,
            $gbGradient !== '',
            $hasImageLikeBackground,
            $sectionPreset,
            $backgroundMode,
            $backgroundTreatment
        );

        $textStyle = $resolvedTextColor !== ''
            ? 'color:' . esc_attr($resolvedTextColor) . ';'
            : '';

        $presetOuterStyle = $this->presetOuterStyle($sectionPreset, $backgroundMode);
        $contentWidthStyle = $this->contentWidthStyle($contentWidthPreset);

        $fullWidthAppend = '';
        if ($containerSize === 'bg-full-width' || $backgroundMode === 'parallax') {
            $fullWidthAppend = 'width:100vw!important;max-width:none!important;margin-left:calc(50% - 50vw)!important;margin-right:calc(50% - 50vw)!important;padding-left:0!important;padding-right:0!important;';
        }

        return [
            'block' => $this,
            'block_id' => $blockId,
            'tag' => $tag,
            'container_size' => $containerSize,
            'section_preset' => $sectionPreset,
            'background_mode' => $backgroundMode,
            'background_treatment' => $backgroundTreatment,
            'has_overlay' => $hasOverlay,
            'has_parallax_background' => $hasParallaxBackground,
            'parallax_monochrome' => $parallaxMonochrome,

            'background_style' => $backgroundStyle,
            'overlay_style' => $overlayStyle,
            'parallax_background_style' => $parallaxBackgroundStyle,
            'parallax_speed' => $parallaxSpeed,
            'text_style' => $textStyle,
            'preset_outer_style' => $presetOuterStyle,
            'content_width_style' => $contentWidthStyle,
            'editor_fullwidth_append' => $fullWidthAppend,
        ];
    }

    /* -------------------------------------------------------------------------
     | Helpers
     * ---------------------------------------------------------------------- */

    /**
     * Convert a value into a safe array.
     *
     * @param  mixed  $value
     * @return array<string, mixed>
     */
    private function arr($value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_object($value)) {
            return json_decode(json_encode($value), true) ?: [];
        }

        return [];
    }

    /**
     * Return the block object as an array.
     *
     * @return array<string, mixed>
     */
    private function blockArray(): array
    {
        return $this->arr($this->block);
    }

    /**
     * Read Gutenberg gradient from the block style object.
     *
     * @return string
     */
    private function gbGradient(): string
    {
        $b = $this->blockArray();
        $color = $this->arr($b['style']['color'] ?? []);

        return isset($color['gradient']) ? (string) $color['gradient'] : '';
    }

    /**
     * Read Gutenberg custom text color from the block style object.
     *
     * @return string
     */
    private function gbTextColor(): string
    {
        $b = $this->blockArray();
        $color = $this->arr($b['style']['color'] ?? []);

        return isset($color['text']) ? (string) $color['text'] : '';
    }

    /**
     * Read Gutenberg custom background color from the block style object.
     *
     * @return string
     */
    private function gbBackgroundColor(): string
    {
        $b = $this->blockArray();
        $color = $this->arr($b['style']['color'] ?? []);

        return isset($color['background']) ? (string) $color['background'] : '';
    }

    /**
     * Determine whether a background image is assigned.
     *
     * Why this checks background mode:
     * - Image fields may still contain old saved values.
     * - The image should only affect output when Image + Overlay mode is active.
     *
     * @return bool
     */
    private function hasBackgroundImage(): bool
    {
        if ((get_field('background_mode') ?: 'none') !== 'image') {
            return false;
        }

        return (int) get_field('background_image') > 0;
    }

    /**
     * Determine whether a parallax background image is assigned.
     *
     * Why this checks background mode:
     * - Parallax fields may contain saved values after switching modes.
     * - The parallax layer should only render when Parallax Image mode is active.
     *
     * @return bool
     */
    private function hasParallaxBackgroundImage(): bool
    {
        if ((get_field('background_mode') ?: 'none') !== 'parallax') {
            return false;
        }

        return (int) get_field('parallax_background_image') > 0;
    }

    /**
     * Build the background CSS for the outer wrapper.
     *
     * Why this helper exists:
     * - It applies Gutenberg custom background color/gradient when image-like
     *   modes are not active.
     * - It applies the ACF background image only when Image + Overlay mode is
     *   active.
     * - It keeps theme treatments, static image backgrounds, and parallax
     *   backgrounds mutually exclusive.
     *
     * @param  string  $gbGradient
     * @param  string  $gbBackground
     * @param  string  $backgroundMode
     * @return string
     */
    private function backgroundStyle(string $gbGradient, string $gbBackground, string $backgroundMode): string
    {
        $css = [];

        if (! in_array($backgroundMode, ['image', 'parallax'], true) && $gbBackground !== '') {
            $css[] = 'background-color:' . esc_attr($gbBackground);
        }

        $imgId = $backgroundMode === 'image' ? (int) get_field('background_image') : 0;
        $imgUrl = $imgId ? wp_get_attachment_image_url($imgId, 'full') : '';
        $bgPosition = trim((string) (get_field('background_position') ?: 'center center'));

        $layers = [];

        if (! in_array($backgroundMode, ['image', 'parallax'], true) && $gbGradient !== '') {
            $layers[] = $gbGradient;
        }

        if ($imgUrl) {
            $layers[] = 'url(' . esc_url($imgUrl) . ')';
        }

        if (! empty($layers)) {
            $css[] = 'background-image:' . implode(',', $layers);
        }

        if ($imgUrl) {
            $css[] = 'background-repeat:no-repeat';
            $css[] = 'background-position:' . esc_attr($bgPosition);
            $css[] = 'background-size:cover';
        }

        return implode(';', $css);
    }

    /**
     * Build the optional overlay CSS.
     *
     * Why this checks background mode:
     * - Overlay is intentionally tied to static image and parallax image
     *   backgrounds only.
     * - Theme treatments already include their own balanced color behavior.
     *
     * @param  string  $backgroundMode
     * @return string
     */
    private function overlayStyle(string $backgroundMode): string
    {
        if ($backgroundMode === 'image') {
            if (! get_field('use_overlay')) {
                return '';
            }

            $color = trim((string) (get_field('overlay_color') ?: '#0b1020'));
            $opacity = get_field('overlay_opacity');
        } elseif ($backgroundMode === 'parallax') {
            if (! get_field('parallax_use_overlay')) {
                return '';
            }

            $color = trim((string) (get_field('parallax_overlay_color') ?: '#0b1020'));
            $opacity = get_field('parallax_overlay_opacity');
        } else {
            return '';
        }

        $opacity = is_numeric($opacity) ? (float) $opacity : 45.0;
        $opacity = max(0, min(100, $opacity));

        return 'background-color:' . esc_attr($color) . ';opacity:' . ($opacity / 100) . ';';
    }

    /**
     * Build the inline style for the parallax background layer.
     *
     * Why this is separate from backgroundStyle():
     * - Static image mode uses the wrapper background-image property.
     * - Parallax mode needs a real child layer so JavaScript can move it
     *   independently from the content.
     *
     * @param  string  $backgroundMode
     * @return string
     */
    private function parallaxBackgroundStyle(string $backgroundMode): string
    {
        if ($backgroundMode !== 'parallax') {
            return '';
        }

        $imgId = (int) get_field('parallax_background_image');
        $imgUrl = $imgId ? wp_get_attachment_image_url($imgId, 'full') : '';

        if (! $imgUrl) {
            return '';
        }

        $bgPosition = trim((string) (get_field('parallax_background_position') ?: 'center center'));

        return implode(';', [
            'background-image:url(' . esc_url($imgUrl) . ')',
            'background-position:' . esc_attr($bgPosition),
            'background-size:cover',
        ]);
    }

    /**
     * Return the editor-controlled parallax speed as a safe number.
     *
     * Why this is capped:
     * - Very high movement values can make text feel unstable.
     * - Keeping the field to 0-100 gives editors an understandable range.
     *
     * @return float
     */
    private function parallaxSpeed(): float
    {
        $speed = get_field('parallax_speed');
        $speed = is_numeric($speed) ? (float) $speed : 30.0;

        return max(0, min(100, $speed));
    }

    /**
     * Determine whether the parallax image should render in monochrome.
     *
     * Why this checks background mode:
     * - The field may have a saved value after switching away from parallax.
     * - The grayscale filter should only ever affect the dedicated parallax
     *   background layer.
     *
     * @return bool
     */
    private function parallaxMonochrome(): bool
    {
        if ((get_field('background_mode') ?: 'none') !== 'parallax') {
            return false;
        }

        return (bool) get_field('parallax_monochrome');
    }

    /**
     * Resolve the final text color for the section.
     *
     * Priority:
     * 1) Explicit Gutenberg text color
     * 2) Manual text scheme
     * 3) Auto mode for image / parallax / midnight / gradient / hero / CTA situations
     * 4) Empty string = inherit theme text
     *
     * @param  string  $gbText
     * @param  string  $scheme
     * @param  bool    $hasGradient
     * @param  bool    $hasImage
     * @param  string  $preset
     * @param  string  $backgroundMode
     * @param  string  $backgroundTreatment
     * @return string
     */
    private function resolvedTextColor(
        string $gbText,
        string $scheme,
        bool $hasGradient,
        bool $hasImage,
        string $preset,
        string $backgroundMode,
        string $backgroundTreatment
    ): string {
        if ($gbText !== '') {
            return $gbText;
        }

        switch ($scheme) {
            case 'light':
                return '#ffffff';

            case 'dark':
                return '#111722';

            case 'auto':
                if (in_array($backgroundMode, ['image', 'parallax'], true) && $hasImage) {
                    return '#ffffff';
                }

                if ($backgroundMode === 'treatment' && $backgroundTreatment === 'midnight') {
                    return '#ffffff';
                }

                if ($hasGradient || in_array($preset, ['hero_banner', 'cta_band'], true)) {
                    return '#ffffff';
                }

                return '';

            case 'inherit':
            default:
                return '';
        }
    }

    /**
     * Return the outer spacing / min-height CSS for the selected preset.
     *
     * Why this helper exists:
     * - Presets replace the old manual device-by-device spacing model.
     * - Each preset provides a sensible, responsive default.
     * - Parallax mode intentionally enforces larger vertical space so the
     *   background movement is visible regardless of the section preset.
     *
     * @param  string  $preset
     * @param  string  $backgroundMode
     * @return string
     */
    private function presetOuterStyle(string $preset, string $backgroundMode): string
    {
        if ($backgroundMode === 'parallax') {
            return 'padding-top:clamp(6rem,12vw,10rem);padding-bottom:clamp(6rem,12vw,10rem);min-height:clamp(34rem,72vh,54rem);';
        }

        switch ($preset) {
            case 'hero_banner':
                return 'padding-top:clamp(5rem,10vw,8rem);padding-bottom:clamp(5rem,10vw,8rem);min-height:clamp(30rem,65vh,46rem);';

            case 'cta_band':
                return 'padding-top:clamp(4rem,8vw,6rem);padding-bottom:clamp(4rem,8vw,6rem);min-height:clamp(18rem,38vh,26rem);';

            case 'standard':
            default:
                return 'padding-top:clamp(3rem,6vw,5rem);padding-bottom:clamp(3rem,6vw,5rem);';
        }
    }

    /**
     * Return the width CSS for the inner content shell.
     *
     * @param  string  $preset
     * @return string
     */
    private function contentWidthStyle(string $preset): string
    {
        switch ($preset) {
            case 'narrow':
                return 'width:100%;max-width:48rem;margin-left:auto;margin-right:auto;';

            case 'wide':
                return 'width:100%;max-width:90rem;margin-left:auto;margin-right:auto;';

            case 'full':
                return 'width:100%;max-width:none;';

            case 'standard':
            default:
                return 'width:100%;max-width:75rem;margin-left:auto;margin-right:auto;';
        }
    }
}