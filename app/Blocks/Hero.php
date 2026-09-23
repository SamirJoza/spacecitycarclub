<?php

/**
 * File: app/Blocks/Hero.php
 *
 * Registers the Space City Car Club Hero block fields and prepares the data
 * passed into the Blade view. This file intentionally keeps the Hero block's
 * editor controls close to the block so the Gutenberg editing experience and
 * front-end rendering stay in sync.
 */

namespace App\Blocks;

use Log1x\AcfComposer\Block;
use Log1x\AcfComposer\Builder;

class Hero extends Block
{
    public $name        = 'Hero';
    public $description = 'Hero with optional GSAP 6-slice scroll wall, dual CTAs, configurable height, and token-based headline highlights.';
    public $category    = 'theme-blocks';
    public $icon        = 'slides';
    public $keywords    = ['hero', 'gsap', 'scroll', 'parallax'];
    public $post_types  = ['post', 'page'];
    public $mode        = 'preview';

    public $supports = [
        'align'         => true,
        'align_text'    => true,
        'align_content' => true,
        'anchor'        => true,
        'mode'          => true,
        'multiple'      => true,
        'jsx'           => true,
        'spacing'       => [
            'padding' => true,
            'margin'  => true,
        ],
    ];

    public function with(): array
    {
        $image = get_field('background_image') ?: [];
        $logo  = get_field('logo_image') ?: [];

        // ---------------------------------------------------------------------
        // Height handling
        // ---------------------------------------------------------------------
        // The editor can choose a preset height or provide a custom vh value.
        // The custom value is clamped so the block stays usable and does not
        // accidentally collapse or become taller than a full viewport.
        $heightMode = get_field('height_mode') ?: 'full';
        $customVh   = (int) (get_field('custom_height_vh') ?: 70);
        $customVh   = max(40, min(100, $customVh));

        $minHeight = match ($heightMode) {
            'half'   => '55vh',
            'custom' => $customVh . 'vh',
            default  => '100vh',
        };

        // ---------------------------------------------------------------------
        // Overlay handling
        // ---------------------------------------------------------------------
        // New fields allow the editor to define one shared opacity plus separate
        // overlay colors for dark and light themes. Dark defaults to black and
        // light defaults to white, matching the theme reversal requested for this
        // project.
        //
        // The previous overlay_strength field is kept only as a fallback so old
        // Hero blocks do not visually jump before they are opened and re-saved.
        $legacyOverlayStrength = get_field('overlay_strength') ?: 'medium';
        $legacyOverlayOpacityMap = [
            'subtle' => 20,
            'medium' => 35,
            'strong' => 50,
        ];

        $overlayOpacityRaw = get_field('overlay_opacity');
        $overlayOpacityPercent = is_numeric($overlayOpacityRaw)
            ? (int) $overlayOpacityRaw
            : ($legacyOverlayOpacityMap[$legacyOverlayStrength] ?? 35);
        $overlayOpacityPercent = max(0, min(100, $overlayOpacityPercent));

        $overlayDarkColor  = $this->sanitizeHexColor((string) (get_field('overlay_color_dark') ?: '#000000'), '#000000');
        $overlayLightColor = $this->sanitizeHexColor((string) (get_field('overlay_color_light') ?: '#ffffff'), '#ffffff');

        // Highlight style for [[token]] headline text.
        $highlightStyle = $this->sanitizeHighlightStyle(get_field('highlight_gradient') ?: 'club-blue');

        // Headline rendering with [[tokens]]
        $headlineRaw = (string) (get_field('headline') ?: '');
        $headlineRendered = $this->headlineWithHighlights($headlineRaw, $highlightStyle);

        return [
            // Mode: sliced | standard
            'mode' => get_field('mode') ?: 'sliced',

            // Media
            'bg_url' => $image['url'] ?? '',
            'bg_alt' => $image['alt'] ?? '',
            'logo_url' => $logo['url'] ?? '',

            // Content
            'kicker' => (string) (get_field('kicker') ?: ''),
            'headline_rendered' => $headlineRendered,
            'subhead' => (string) (get_field('subhead') ?: ''),

            'cta_primary_label'   => (string) (get_field('cta_primary_label') ?: ''),
            'cta_primary_url'     => (string) (get_field('cta_primary_url') ?: ''),
            'cta_secondary_label' => (string) (get_field('cta_secondary_label') ?: ''),
            'cta_secondary_url'   => (string) (get_field('cta_secondary_url') ?: ''),

            // Layout + style
            'text_align' => get_field('text_align') ?: 'center',
            'height_mode' => $heightMode,
            'min_height'  => $minHeight,

            // Theme-aware overlay values used by the Blade file as CSS variables.
            'overlay_opacity' => number_format($overlayOpacityPercent / 100, 2, '.', ''),
            'overlay_opacity_percent' => $overlayOpacityPercent,
            'overlay_color_dark' => $overlayDarkColor,
            'overlay_color_light' => $overlayLightColor,
            'overlay_color_dark_rgb' => $this->hexToRgbString($overlayDarkColor),
            'overlay_color_light_rgb' => $this->hexToRgbString($overlayLightColor),

            // Highlight style
            'highlight_gradient' => $highlightStyle,

            // Motion
            'motion_enabled' => (bool) get_field('motion_enabled'),
            'scale_to'       => (float) (get_field('scale_to') ?: 1.35),
            'scroll_len_px'  => (int) (get_field('scroll_len_px') ?: 1100),
        ];
    }

    public function fields(): array
    {
        $fields = Builder::make('hero');

        $fields
            ->addTab('Content')

            ->addSelect('mode', [
                'label' => 'Hero Mode',
                'choices' => [
                    'sliced'   => 'Sliced (GSAP scroll wall)',
                    'standard' => 'Standard (single image)',
                ],
                'default_value' => 'sliced',
                'ui' => 1,
            ])

            ->addImage('background_image', [
                'label'         => 'Background Image',
                'return_format' => 'array',
                'preview_size'  => 'large',
                'required'      => 1,
            ])

            ->addText('kicker', [
                'label' => 'Kicker (small label)',
            ])

            ->addSelect('highlight_gradient', [
                'label' => 'Headline Highlight Style',
                'choices' => [
                    'club-blue'         => 'Club Blue',
                    'signal-red'        => 'Signal Red',
                    'space-city'        => 'Space City',
                    'club-blue-solid'   => 'Club Blue Solid',
                    'signal-red-solid'  => 'Signal Red Solid',
                    'deep-purple-solid' => 'Deep Purple Solid',
                ],
                'default_value' => 'club-blue',
                'ui' => 1,
                'instructions' => 'Use [[double brackets]] in the headline to apply the selected highlight style. You may also use <strong>bold text</strong>.',
            ])

            ->addTextarea('headline', [
                'label' => 'Headline',
                'rows'  => 2,
                'instructions' => 'Wrap the word/phrase to highlight in [[double brackets]]. Example: Welcome to [[Space City]]. You may also use <strong>bold text</strong>.',
            ])

            ->addTextarea('subhead', [
                'label' => 'Subhead',
                'rows'  => 2,
            ])

            ->addImage('logo_image', [
                'label'         => 'Logo Image (optional)',
                'return_format' => 'array',
                'preview_size'  => 'medium',
                'required'      => 0,
            ])

            ->addText('cta_primary_label', [
                'label' => 'Primary CTA Label',
            ])
            ->addText('cta_primary_url', [
                'label' => 'Primary CTA URL',
            ])
            ->addText('cta_secondary_label', [
                'label' => 'Secondary CTA Label (optional)',
            ])
            ->addText('cta_secondary_url', [
                'label' => 'Secondary CTA URL (optional)',
            ])

            ->addTab('Layout')

            ->addSelect('text_align', [
                'label'   => 'Text Alignment',
                'choices' => [
                    'left'   => 'Left',
                    'center' => 'Center',
                    'right'  => 'Right',
                ],
                'default_value' => 'center',
                'ui' => 1,
            ])

            ->addRange('overlay_opacity', [
                'label' => 'Overlay Opacity',
                'instructions' => 'Controls the overlay strength for both themes. Dark theme uses the dark overlay color; light theme uses the light overlay color.',
                'min' => 0,
                'max' => 100,
                'step' => 1,
                'default_value' => 35,
                'append' => '%',
            ])

            ->addColorPicker('overlay_color_dark', [
                'label' => 'Overlay Color — Dark Theme',
                'instructions' => 'Default is black. This is used when the site is in dark mode.',
                'default_value' => '#000000',
            ])

            ->addColorPicker('overlay_color_light', [
                'label' => 'Overlay Color — Light Theme',
                'instructions' => 'Default is white. This is used when the site is in light mode.',
                'default_value' => '#ffffff',
            ])

            ->addSelect('height_mode', [
                'label' => 'Hero Height',
                'choices' => [
                    'full'   => 'Full (100vh)',
                    'half'   => 'Half (55vh)',
                    'custom' => 'Custom (vh)',
                ],
                'default_value' => 'full',
                'ui' => 1,
            ])

            ->addRange('custom_height_vh', [
                'label' => 'Custom Height (vh)',
                'min' => 40,
                'max' => 100,
                'step' => 1,
                'default_value' => 70,
            ])
            ->conditional('height_mode', '==', 'custom')

            ->addTab('Motion')

            ->addTrueFalse('motion_enabled', [
                'label' => 'Enable GSAP Motion (scroll scrub)',
                'ui' => 1,
                'default_value' => 1,
            ])

            ->addRange('scale_to', [
                'label' => 'Zoom (Scale) on Scroll',
                'min'   => 1.0,
                'max'   => 2.0,
                'step'  => 0.05,
                'default_value' => 1.35,
            ])

            ->addRange('scroll_len_px', [
                'label' => 'Scroll Length (px)',
                'min'   => 600,
                'max'   => 2000,
                'step'  => 50,
                'default_value' => 1100,
                'instructions' => 'How long the scroll-scrub animation lasts.',
            ]);

        return $fields->build();
    }

    public function assets(array $block): void
    {
        // no-op (JS/CSS is instance-scoped in Blade for now)
    }

    /**
     * Convert [[tokens]] into controlled highlight spans.
     *
     * This intentionally allows only <strong> from editor-entered headline text.
     * Everything else is stripped before the highlight spans are injected.
     */
    private function headlineWithHighlights(string $headline, string $highlightStyle = 'club-blue'): string
    {
        $headline = trim($headline);
        if ($headline === '') {
            return '';
        }

        $highlightStyle = $this->sanitizeHighlightStyle($highlightStyle);
        $class = 'text-gradient text-gradient--' . $highlightStyle;

        $safeHeadline = wp_kses($headline, [
            'strong' => [],
        ]);

        return preg_replace_callback('/\[\[(.+?)\]\]/', function ($matches) use ($class) {
            return '<span class="' . esc_attr($class) . '">' . $matches[1] . '</span>';
        }, $safeHeadline);
    }

    /**
     * Allow only the current editor options.
     */
    private function sanitizeHighlightStyle(string $highlightStyle): string
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
     * Sanitize a color picker value and keep a safe fallback.
     *
     * ACF's Color Picker normally returns a hex color string. This helper keeps
     * the front end safe if an empty or malformed value is ever returned.
     */
    private function sanitizeHexColor(string $color, string $fallback): string
    {
        $color = trim($color);
        $sanitized = sanitize_hex_color($color);

        return $sanitized ?: $fallback;
    }

    /**
     * Convert a sanitized hex color into an RGB triplet for CSS rgb() alpha use.
     *
     * Blade outputs this as a CSS custom property so the overlay can use modern
     * rgb(var(--color) / var(--opacity)) syntax without needing rgba strings.
     */
    private function hexToRgbString(string $hex): string
    {
        $hex = ltrim($hex, '#');

        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        if (strlen($hex) !== 6) {
            return '0 0 0';
        }

        return hexdec(substr($hex, 0, 2)) . ' '
            . hexdec(substr($hex, 2, 2)) . ' '
            . hexdec(substr($hex, 4, 2));
    }
}