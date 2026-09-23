<?php

/**
 * app/Blocks/SectionHeader.php
 *
 * Registers the reusable Section Header Gutenberg block for the Space City Car Club theme.
 *
 * Why this file exists:
 * - Provides one consistent editor-controlled heading pattern for page sections.
 * - Keeps headline highlights controlled through ACF instead of free-form styling.
 * - Adds small layout controls that solve common Gutenberg spacing/alignment issues
 *   without requiring editors to manually tune every block instance.
 */

namespace App\Blocks;

use Log1x\AcfComposer\Block;
use Log1x\AcfComposer\Builder;

class SectionHeader extends Block
{
    public $name        = 'Section Header';
    public $description = 'Reusable section heading: eyebrow + headline + optional highlight + description.';
    public $category    = 'theme-blocks';
    public $icon        = 'heading';
    public $keywords    = ['section', 'header', 'heading', 'eyebrow', 'title'];
    public $post_types  = ['post', 'page'];
    public $mode        = 'preview';

    /**
     * Native block supports.
     *
     * Why this exists:
     * - Gutenberg still controls anchor, align wide/full, and manual spacing when needed.
     * - The custom fields below provide safer defaults for common spacing/alignment use.
     */
    public $supports = [
        'anchor'   => true,
        'align'    => ['wide', 'full'],
        'spacing'  => ['padding' => true, 'margin' => true],
        'color'    => ['text' => true],
        'jsx'      => true,
        'multiple' => true,
    ];

    /**
     * Data passed to resources/views/blocks/section-header.blade.php.
     */
    public function with(): array
    {
        $headline = (string) (get_field('headline') ?: '');
        $highlight = $this->sanitizeHighlightStyle((string) (get_field('highlight_style') ?: 'club-blue'));
        $showAccentRule = get_field('show_accent_rule');

        return [
            'eyebrow'          => (string) (get_field('eyebrow') ?: ''),
            'headline'         => $headline,
            'description'      => (string) (get_field('description') ?: ''),
            'align'            => (string) (get_field('align') ?: ''),
            'width'            => (string) (get_field('width') ?: 'normal'),
            'spacing_after'    => $this->sanitizeSpacingAfter((string) (get_field('spacing_after') ?: 'medium')),
            'show_accent_rule' => $this->shouldShowAccentRule($showAccentRule),
            'highlight_style'  => $highlight,
            'highlighted'      => $this->applyHighlightSyntax($headline, $highlight),
        ];
    }

    /**
     * ACF field definitions for the block.
     *
     * Why these controls exist:
     * - Editors can adjust copy, highlight style, width, alignment, and spacing without
     *   touching utility classes manually.
     * - The spacing field provides a reliable bottom rhythm even when Gutenberg spacing
     *   controls are not set on the block instance.
     */
    public function fields(): array
    {
        $fields = Builder::make('section_header');

        $fields
            ->addText('eyebrow', [
                'label'        => 'Eyebrow (optional)',
                'instructions' => 'Short label above the headline (e.g. “Monthly Feature”).',
            ])
            ->addTextarea('headline', [
                'label'        => 'Headline',
                'instructions' => 'Use [[double brackets]] to highlight a phrase. Example: Welcome to [[Space City]]. You may also use <strong>bold text</strong>.',
                'rows'         => 2,
                'required'     => 1,
                'new_lines'    => 'br',
            ])
            ->addTextarea('description', [
                'label'        => 'Description (optional)',
                'instructions' => 'Optional supporting text. Line breaks entered here will be preserved on the front end.',
                'rows'         => 3,
                'new_lines'    => '',
            ])
            ->addSelect('highlight_style', [
                'label'         => 'Highlight Style',
                'choices'       => [
                    'club-blue'         => 'Club Blue',
                    'signal-red'        => 'Signal Red',
                    'space-city'        => 'Space City',
                    'club-blue-solid'   => 'Club Blue Solid',
                    'signal-red-solid'  => 'Signal Red Solid',
                    'deep-purple-solid' => 'Deep Purple Solid',
                ],
                'default_value' => 'club-blue',
                'ui'            => 1,
                'instructions'  => 'Use [[double brackets]] in the headline to apply the selected highlight style. You may also use <strong>bold text</strong>.',
            ])
            ->addSelect('align', [
                'label'         => 'Alignment',
                'instructions'  => 'Controls both text alignment and where the header sits inside the available space.',
                'choices'       => [
                    ''       => 'Inherit',
                    'left'   => 'Left',
                    'center' => 'Center',
                    'right'  => 'Right',
                ],
                'default_value' => '',
                'ui'            => 1,
            ])
            ->addSelect('width', [
                'label'         => 'Max Width',
                'choices'       => [
                    'narrow' => 'Narrow',
                    'normal' => 'Normal',
                    'wide'   => 'Wide',
                ],
                'default_value' => 'normal',
                'ui'            => 1,
            ])
            ->addSelect('spacing_after', [
                'label'         => 'Spacing After',
                'instructions'  => 'Adds consistent space below the section header before the next block.',
                'choices'       => [
                    'none'   => 'None',
                    'small'  => 'Small',
                    'medium' => 'Medium',
                    'large'  => 'Large',
                    'xl'     => 'Extra Large',
                ],
                'default_value' => 'medium',
                'ui'            => 1,
            ])
            ->addTrueFalse('show_accent_rule', [
                'label'         => 'Show Accent Rule',
                'instructions'  => 'Adds a small neon gradient rule below the header copy to visually finish the block.',
                'default_value' => 1,
                'ui'            => 1,
            ]);

        return $fields->build();
    }

    /**
     * Converts [[highlight]] to a controlled highlight span.
     *
     * Why this exists:
     * - Editors can mark only the words that need visual emphasis.
     * - The selected style stays controlled by the ACF select field.
     * - <strong> is intentionally supported for bold emphasis.
     * - ACF-generated <br> tags from the headline textarea are preserved.
     */
    private function applyHighlightSyntax(string $text, string $highlightStyle = 'club-blue'): string
    {
        if ($text === '') {
            return '';
        }

        $highlightStyle = $this->sanitizeHighlightStyle($highlightStyle);

        $brToken = '___SCCC_SECTION_HEADER_BR___';

        $safe = preg_replace('/<br\s*\/?>/i', $brToken, $text);
        $safe = is_string($safe) ? $safe : $text;

        $safe = wp_kses($safe, [
            'strong' => [],
        ]);

        $safe = (string) preg_replace(
            '/\[\[(.+?)\]\]/s',
            '<span class="sccc-text-gradient sccc-text-gradient--' . $highlightStyle . '" data-sccc-highlight>$1</span>',
            $safe
        );

        $safe = str_replace($brToken, '<br>', $safe);

        return wp_kses($safe, [
            'br' => [],
            'span' => [
                'class' => true,
                'data-sccc-highlight' => true,
            ],
            'strong' => [],
        ]);
    }

    /**
     * Restrict headline highlight styles to the current editor choices.
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
     * Keep the accent rule enabled for existing block instances by default.
     *
     * Why this exists:
     * - Newly added ACF true/false fields may return null or an empty value before
     *   an editor re-saves an older block.
     * - Only an explicit false/0 value should hide the accent rule.
     */
    private function shouldShowAccentRule($value): bool
    {
        return ! in_array($value, [false, 0, '0'], true);
    }

    /**
     * Restrict spacing choices to known CSS modifiers used by the Blade template.
     */
    private function sanitizeSpacingAfter(string $spacingAfter): string
    {
        $spacingAfter = strtolower(trim($spacingAfter));

        $allowed = [
            'none',
            'small',
            'medium',
            'large',
            'xl',
        ];

        return in_array($spacingAfter, $allowed, true) ? $spacingAfter : 'medium';
    }
}