<?php

namespace App\Blocks;

use Log1x\AcfComposer\Block;
use Log1x\AcfComposer\Builder;

/**
 * File path + filename: app/Blocks/Masthead.php
 * -----------------------------------------------------------------------------
 * Purpose:
 * - Register a Gutenberg block for internal landing-page style mastheads,
 *   specifically intended for the business directory page hero.
 *
 * Why this file exists:
 * - The project already has a generic Hero block, and this new block should not
 *   replace or rename that existing block type.
 * - This block provides a more specific editorial pattern for internal pages:
 *   eyebrow, headline, supporting text, actions, and a background image.
 *
 * Notes:
 * - The editor-facing block name is intentionally simple: "Masthead".
 * - The Blade view is expected at: resources/views/blocks/masthead.blade.php
 * - Headline supports [[double bracket]] highlight syntax.
 * - The headline field intentionally uses ACF's formatted output so textarea
 *   line breaks configured as "Automatically add <br>" are preserved.
 * - We then protect those <br> tags during processing, add our controlled
 *   highlight span markup, and sanitize the final result with a very small
 *   allowlist.
 * - Background motion uses editor-friendly keywords. Editors choose labels like
 *   Static, Subtle Parallax, Image Pan, Vertical, Horizontal, Top, Bottom,
 *   Left, Right, Small, Medium, and Large. Numeric CSS/JS values are mapped in
 *   the Blade view so the admin stays simple.
 */
class Masthead extends Block
{
    public $name = 'Masthead';
    public $description = 'Editorial masthead block for the business directory and other internal landing pages.';
    public $category = 'theme-blocks';
    public $icon = 'storefront';
    public $keywords = ['masthead', 'directory', 'business', 'internal', 'intro'];
    public $post_types = ['page'];
    public $mode = 'preview';

    public $supports = [
        'anchor'   => true,
        'align'    => ['wide', 'full'],
        'spacing'  => [
            'padding' => true,
            'margin'  => true,
        ],
        'color'    => [
            'background' => true,
            'text'       => true,
            'gradients'  => true,
        ],
        'jsx'      => true,
        'multiple' => true,
    ];

    /**
     * Provide normalized data to the Blade view.
     *
     * Why headline is loaded as formatted output:
     * - The ACF textarea field is already configured to convert new lines into
     *   <br> tags.
     * - That behavior only applies when the field is returned as a formatted
     *   value.
     * - We therefore keep the formatted value and preserve the generated <br>
     *   tags during headline processing instead of stripping them out and trying
     *   to rebuild them later.
     *
     * Why motion values are normalized here:
     * - The editor uses keyword-style options for easier content entry.
     * - Blade receives predictable strings and can map those keywords into the
     *   CSS/JS values needed for the actual image treatment.
     * - This keeps saved block data human-readable and avoids exposing editors
     *   to raw percentages or transform values.
     *
     * @return array<string, mixed>
     */
    public function with(): array
    {
        $headline = (string) (get_field('headline') ?: '');
        $image    = get_field('background_image');
        $style    = (string) (get_field('headline_highlight') ?: 'club-blue');

        return [
            'badge'                => (string) (get_field('badge') ?: ''),
            'eyebrow'              => (string) (get_field('eyebrow') ?: ''),
            'headline'             => $headline,
            'headline_rendered'    => $this->applyHighlightSyntax($headline, $style),
            'headline_highlight'   => $style,
            'description'          => (string) (get_field('description') ?: ''),
            'content_align'        => (string) (get_field('content_align') ?: 'left'),

            'primary_link'         => $this->normalizeLink(get_field('primary_link')),
            'secondary_link'       => $this->normalizeLink(get_field('secondary_link')),

            'background_image_url' => is_array($image) ? (string) ($image['url'] ?? '') : '',
            'background_image_alt' => is_array($image) ? (string) ($image['alt'] ?? '') : '',
            'background_focus'     => $this->normalizeImageFocus(get_field('background_focus')),
            'height_preset'        => (string) (get_field('height_preset') ?: 'md'),
            'overlay_strength'     => (string) (get_field('overlay_strength') ?: 'medium'),

            'background_motion'    => $this->normalizeBackgroundMotion(get_field('background_motion')),
            'parallax_strength'    => $this->normalizeStrength(get_field('parallax_strength')),
            'pan_direction'        => $this->normalizePanDirection(get_field('pan_direction')),
            'pan_vertical_start'   => $this->normalizeVerticalKeyword(get_field('pan_vertical_start')),
            'pan_vertical_end'     => $this->normalizeVerticalKeyword(get_field('pan_vertical_end'), 'bottom'),
            'pan_horizontal_start' => $this->normalizeHorizontalKeyword(get_field('pan_horizontal_start')),
            'pan_horizontal_end'   => $this->normalizeHorizontalKeyword(get_field('pan_horizontal_end'), 'right'),
            'pan_range'            => $this->normalizeStrength(get_field('pan_range'), 'large'),
        ];
    }

    /**
     * Define the block's ACF fields.
     *
     * Why headline uses `new_lines => 'br'`:
     * - Editors can press Enter in the textarea and get a rendered line break
     *   on the front end.
     * - This is the intended ACF field behavior for the masthead headline.
     *
     * Why the motion controls use keyword choices:
     * - Editors should not need to understand `object-position` percentages or
     *   transform math to adjust a masthead image.
     * - The block stores simple keywords and maps them in the Blade template.
     * - Conditional controls keep the Media tab cleaner by only showing options
     *   that apply to the selected background motion mode.
     */
    public function fields(): array
    {
        $fields = Builder::make('masthead');

        $fields
            ->addTab('Content', ['placement' => 'top'])
            ->addText('badge', [
                'label'        => 'Badge / Pill (optional)',
                'instructions' => 'Short status or context label. Example: Connect & Support',
            ])
            ->addText('eyebrow', [
                'label'        => 'Eyebrow (optional)',
                'instructions' => 'Small line above the headline.',
            ])
            ->addTextarea('headline', [
                'label'        => 'Headline',
                'instructions' => 'Use [[double brackets]] to highlight a word or phrase. Example: Member [[Business]] Directory',
                'rows'         => 2,
                'required'     => 1,
                'new_lines'    => 'br',
            ])
            ->addSelect('headline_highlight', [
                'label'         => 'Headline Highlight Style',
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
            ->addTextarea('description', [
                'label'        => 'Description (optional)',
                'instructions' => 'Supporting copy below the headline.',
                'rows'         => 3,
                'new_lines'    => 'br',
            ])
            ->addSelect('content_align', [
                'label'         => 'Content Alignment',
                'choices'       => [
                    'left'   => 'Left',
                    'center' => 'Center',
                    'right'  => 'Right',
                ],
                'default_value' => 'left',
                'ui'            => 1,
            ])

            ->addTab('Actions', ['placement' => 'top'])
            ->addLink('primary_link', [
                'label'         => 'Primary Button (optional)',
                'return_format' => 'array',
            ])
            ->addLink('secondary_link', [
                'label'         => 'Secondary Button (optional)',
                'return_format' => 'array',
            ])

            ->addTab('Media', ['placement' => 'top'])
            ->addImage('background_image', [
                'label'         => 'Background Image (optional)',
                'instructions'  => 'Wide landscape image recommended. Square and portrait images can also work well with Image Pan.',
                'return_format' => 'array',
                'preview_size'  => 'large',
                'library'       => 'all',
            ])
            ->addSelect('background_focus', [
                'label'         => 'Static Image Position',
                'choices'       => [
                    'top'    => 'Top',
                    'center' => 'Center',
                    'bottom' => 'Bottom',
                    'left'   => 'Left',
                    'right'  => 'Right',
                ],
                'default_value' => 'center',
                'ui'            => 1,
                'instructions'  => 'Used when Background Motion is Static, and as the fallback position if motion is reduced.',
            ])
            ->addSelect('height_preset', [
                'label'         => 'Height',
                'choices'       => [
                    'sm' => 'Compact',
                    'md' => 'Standard',
                    'lg' => 'Tall',
                ],
                'default_value' => 'md',
                'ui'            => 1,
            ])
            ->addSelect('overlay_strength', [
                'label'         => 'Overlay Strength',
                'choices'       => [
                    'soft'   => 'Soft',
                    'medium' => 'Medium',
                    'strong' => 'Strong',
                ],
                'default_value' => 'medium',
                'ui'            => 1,
            ])
            ->addSelect('background_motion', [
                'label'         => 'Background Motion',
                'choices'       => [
                    'static'   => 'Static',
                    'parallax' => 'Subtle Parallax',
                    'pan'      => 'Image Pan',
                ],
                'default_value' => 'static',
                'ui'            => 1,
                'instructions'  => 'Static is safest. Subtle Parallax adds atmosphere. Image Pan shifts the crop while the masthead scrolls through view.',
            ])
            ->addSelect('parallax_strength', [
                'label'         => 'Parallax Strength',
                'choices'       => [
                    'small'  => 'Small',
                    'medium' => 'Medium',
                    'large'  => 'Large',
                ],
                'default_value' => 'medium',
                'ui'            => 1,
                'instructions'  => 'Small is recommended for text-heavy mastheads. Large should be used sparingly.',
            ])
                ->conditional('background_motion', '==', 'parallax')
            ->addSelect('pan_direction', [
                'label'         => 'Pan Direction',
                'choices'       => [
                    'vertical'   => 'Vertical',
                    'horizontal' => 'Horizontal',
                ],
                'default_value' => 'vertical',
                'ui'            => 1,
                'instructions'  => 'Vertical works best for square or portrait images. Horizontal works best for wide scene or lineup images.',
            ])
                ->conditional('background_motion', '==', 'pan')
            ->addSelect('pan_vertical_start', [
                'label'         => 'Vertical Pan Start',
                'choices'       => [
                    'top'    => 'Top',
                    'center' => 'Center',
                    'bottom' => 'Bottom',
                ],
                'default_value' => 'top',
                'ui'            => 1,
            ])
                ->conditional('background_motion', '==', 'pan')
                ->conditional('pan_direction', '==', 'vertical')
            ->addSelect('pan_vertical_end', [
                'label'         => 'Vertical Pan End',
                'choices'       => [
                    'top'    => 'Top',
                    'center' => 'Center',
                    'bottom' => 'Bottom',
                ],
                'default_value' => 'bottom',
                'ui'            => 1,
            ])
                ->conditional('background_motion', '==', 'pan')
                ->conditional('pan_direction', '==', 'vertical')
            ->addSelect('pan_horizontal_start', [
                'label'         => 'Horizontal Pan Start',
                'choices'       => [
                    'left'   => 'Left',
                    'center' => 'Center',
                    'right'  => 'Right',
                ],
                'default_value' => 'left',
                'ui'            => 1,
            ])
                ->conditional('background_motion', '==', 'pan')
                ->conditional('pan_direction', '==', 'horizontal')
            ->addSelect('pan_horizontal_end', [
                'label'         => 'Horizontal Pan End',
                'choices'       => [
                    'left'   => 'Left',
                    'center' => 'Center',
                    'right'  => 'Right',
                ],
                'default_value' => 'right',
                'ui'            => 1,
            ])
                ->conditional('background_motion', '==', 'pan')
                ->conditional('pan_direction', '==', 'horizontal')
            ->addSelect('pan_range', [
                'label'         => 'Pan Range',
                'choices'       => [
                    'small'  => 'Small',
                    'medium' => 'Medium',
                    'large'  => 'Large',
                ],
                'default_value' => 'large',
                'ui'            => 1,
                'instructions'  => 'Large reaches the selected end keyword. Small and Medium keep the crop movement more restrained.',
            ])
                ->conditional('background_motion', '==', 'pan');

        return $fields->build();
    }

    /**
     * Normalize ACF link arrays into a predictable structure.
     *
     * Why this helper exists:
     * - Blade should not have to defensively inspect many ACF edge cases.
     * - This keeps link rendering simple and predictable in the view.
     *
     * @param  mixed  $link
     * @return array<string, string>|null
     */
    protected function normalizeLink(mixed $link): ?array
    {
        if (!is_array($link) || empty($link['url'])) {
            return null;
        }

        return [
            'url'    => (string) ($link['url'] ?? ''),
            'title'  => (string) ($link['title'] ?? ''),
            'target' => (string) ($link['target'] ?? ''),
        ];
    }

    /**
     * Normalize static image focus keywords.
     *
     * Why this helper exists:
     * - Existing saved block instances may have left/center/right values.
     * - New instances may use top/center/bottom.
     * - Blade can safely map this normalized keyword into CSS object-position.
     *
     * @param  mixed  $focus
     * @return string
     */
    protected function normalizeImageFocus(mixed $focus): string
    {
        $focus = is_string($focus) ? strtolower(trim($focus)) : 'center';

        return in_array($focus, ['top', 'center', 'bottom', 'left', 'right'], true)
            ? $focus
            : 'center';
    }

    /**
     * Normalize the background motion mode.
     *
     * Why this helper exists:
     * - The motion mode controls which front-end behavior runs.
     * - Unknown or older saved values should fall back to static so the masthead
     *   remains readable and stable.
     *
     * @param  mixed  $motion
     * @return string
     */
    protected function normalizeBackgroundMotion(mixed $motion): string
    {
        $motion = is_string($motion) ? strtolower(trim($motion)) : 'static';

        return in_array($motion, ['static', 'parallax', 'pan'], true)
            ? $motion
            : 'static';
    }

    /**
     * Normalize repeated Small/Medium/Large strength keywords.
     *
     * Why this helper exists:
     * - Both parallax strength and pan range use the same editor language.
     * - Keeping the allowed keywords centralized reduces mismatches between
     *   saved field data and the front-end mapping.
     *
     * @param  mixed  $strength
     * @return string
     */
    protected function normalizeStrength(mixed $strength, string $fallback = 'medium'): string
    {
        $fallback = in_array($fallback, ['small', 'medium', 'large'], true)
            ? $fallback
            : 'medium';

        $strength = is_string($strength) ? strtolower(trim($strength)) : $fallback;

        return in_array($strength, ['small', 'medium', 'large'], true)
            ? $strength
            : $fallback;
    }

    /**
     * Normalize the pan direction keyword.
     *
     * Why this helper exists:
     * - Vertical and horizontal panning need different start/end keywords.
     * - Unknown values safely fall back to vertical because it is the best match
     *   for square or portrait images placed in wide mastheads.
     *
     * @param  mixed  $direction
     * @return string
     */
    protected function normalizePanDirection(mixed $direction): string
    {
        $direction = is_string($direction) ? strtolower(trim($direction)) : 'vertical';

        return in_array($direction, ['vertical', 'horizontal'], true)
            ? $direction
            : 'vertical';
    }

    /**
     * Normalize vertical pan keywords.
     *
     * @param  mixed   $keyword
     * @param  string  $fallback
     * @return string
     */
    protected function normalizeVerticalKeyword(mixed $keyword, string $fallback = 'top'): string
    {
        $keyword = is_string($keyword) ? strtolower(trim($keyword)) : $fallback;

        return in_array($keyword, ['top', 'center', 'bottom'], true)
            ? $keyword
            : $fallback;
    }

    /**
     * Normalize horizontal pan keywords.
     *
     * @param  mixed   $keyword
     * @param  string  $fallback
     * @return string
     */
    protected function normalizeHorizontalKeyword(mixed $keyword, string $fallback = 'left'): string
    {
        $keyword = is_string($keyword) ? strtolower(trim($keyword)) : $fallback;

        return in_array($keyword, ['left', 'center', 'right'], true)
            ? $keyword
            : $fallback;
    }

    /**
     * Convert [[highlight]] syntax into controlled span markup while preserving
     * ACF-generated <br> tags from the formatted textarea value.
     *
     * Why this method works:
     * - ACF already inserts <br> tags for real line breaks when the textarea is
     *   configured that way.
     * - We temporarily replace those <br> tags with a unique token so escaping
     *   does not print them as text.
     * - We escape all remaining editor text.
     * - We inject only our own controlled highlight span markup.
     * - We restore the <br> tags and then run a tight HTML allowlist so only
     *   the exact tags we expect remain.
     *
     * Example output:
     * <span class="text-gradient text-gradient--signal-red" data-sccc-highlight>Text</span><br>Next line
     *
     * @param  string  $text
     * @param  string  $style
     * @return string
     */
    protected function applyHighlightSyntax(string $text, string $style = 'club-blue'): string
    {
        if ($text === '') {
            return '';
        }

        $allowed = [
            'club-blue',
            'signal-red',
            'space-city',
            'club-blue-solid',
            'signal-red-solid',
            'deep-purple-solid',
        ];

        $style = strtolower(trim($style));
        $style = in_array($style, $allowed, true) ? $style : 'club-blue';

        /**
         * Preserve any ACF-generated line breaks before escaping.
         *
         * Notes:
         * - ACF may output <br>, <br/>, or <br /> depending on context.
         * - We normalize all of them to a single placeholder token first.
         * - The token is intentionally unusual to avoid accidental collisions
         *   with editor-entered text.
         */
        $brToken = '___SCCC_MASTHEAD_BR___';

        $safe = preg_replace('/<br\s*\/?>/i', $brToken, $text);
        $safe = is_string($safe) ? $safe : $text;

        // Allow only the intentional inline formatting needed by this block.
        $safe = wp_kses($safe, [
            'strong' => [],
        ]);

        $safe = (string) preg_replace(
            '/\[\[(.+?)\]\]/s',
            '<span class="text-gradient text-gradient--' . $style . '" data-sccc-highlight>$1</span>',
            $safe
        );

        // Restore preserved line breaks after escaping/highlight injection.
        $safe = str_replace($brToken, '<br>', $safe);

        /**
         * Final safety pass:
         * - Allow only the exact markup this method intentionally outputs.
         * - No arbitrary HTML from the textarea is allowed through.
         */
        return wp_kses($safe, [
            'br'   => [],
            'span'   => [
                'class'               => true,
                'data-sccc-highlight' => true,
            ],
            'strong' => [],
        ]);
    }
}