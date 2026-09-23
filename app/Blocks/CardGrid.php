<?php

/**
 * File: app/Blocks/CardGrid.php
 *
 * Why this file exists:
 * - Registers the Card Grid block with ACF Composer for the Sage theme.
 * - Defines the editor fields used to build each card.
 * - Normalizes editor data before it is passed into the Blade view.
 *
 * Change note:
 * - Adds controlled per-card Icon Size and Icon Color selectors.
 * - Keeps both values sanitized before they reach the Blade file so editor
 *   choices remain predictable and theme-safe.
 */

namespace App\Blocks;

use Log1x\AcfComposer\Block;
use Log1x\AcfComposer\Builder;

class CardGrid extends Block
{
    public $name        = 'Card Grid';
    public $description = 'Reusable card grid (manual cards) with chip badges, optional meta line, and Material Symbols icons.';
    public $category    = 'theme-blocks';
    public $icon        = 'grid-view';
    public $keywords    = ['cards', 'grid', 'events', 'merch', 'blog'];
    public $post_types  = ['post', 'page'];
    public $mode        = 'preview';

    public $supports = [
        'anchor'   => true,
        'align'    => ['wide', 'full'],
        'spacing'  => ['padding' => true, 'margin' => true],
        'color'    => [
            'background' => true,
            'text'       => true,
            'gradients'  => true,
        ],
        'jsx'      => true,
        'multiple' => true,
    ];

    public function with(): array
    {
        $cards = get_field('cards') ?: [];
        $headline = (string) (get_field('headline') ?: '');
        $headlineHighlight = $this->sanitizeHighlightStyle((string) (get_field('headline_highlight') ?: 'club-blue'));

        return [
            // Header
            'eyebrow'              => (string) (get_field('eyebrow') ?: ''),
            'headline'             => $headline,
            'headline_rendered'     => $this->applyHighlightSyntax($headline, $headlineHighlight),
            'description'           => (string) (get_field('description') ?: ''),
            'header_align'          => (string) (get_field('header_align') ?: ''),
            'headline_highlight'    => $headlineHighlight,

            // Grid
            'card_layout'           => $this->sanitizeCardLayout((string) (get_field('card_layout') ?: 'default')),
            'columns_d'             => (int) (get_field('columns_d') ?: 3),
            'columns_t'             => (int) (get_field('columns_t') ?: 2),
            'columns_m'             => (int) (get_field('columns_m') ?: 1),
            'gap'                   => (string) (get_field('gap') ?: 'md'), // sm|md|lg
            'card_style'            => (string) (get_field('card_style') ?: 'standard'), // standard|glow|image
            'equal_height'          => (bool) get_field('equal_height'),

            // Cards
            'cards'                 => $this->normalizeCards($cards),
        ];
    }

    public function fields(): array
    {
        $fields = Builder::make('card_grid');

        $fields
            ->addTab('Header', ['placement' => 'top'])
            ->addText('eyebrow', [
                'label' => 'Eyebrow (optional)',
            ])
            ->addTextarea('headline', [
                'label'        => 'Headline (optional)',
                'instructions' => 'Use [[double brackets]] to highlight a word/phrase. Example: Some great [[New]] event. You may also use <strong>bold text</strong>.',
                'rows'         => 2,
                'new_lines'    => 'br',
            ])
            ->addSelect('headline_highlight', [
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
            ->addTextarea('description', [
                'label' => 'Description (optional)',
                'rows'  => 2,
                'new_lines' => 'br',
            ])
            ->addSelect('header_align', [
                'label' => 'Header Alignment',
                'choices' => [
                    ''       => 'Inherit',
                    'left'   => 'Left',
                    'center' => 'Center',
                    'right'  => 'Right',
                ],
                'default_value' => '',
                'ui' => 1,
            ])

            ->addTab('Grid', ['placement' => 'top'])
            ->addSelect('card_layout', [
                'label' => 'Card Layout Mode',
                'instructions' => 'Default keeps the current card layout. Icon Feature creates larger icon-led cards. Decorative Info creates compact info cards with a section-style treatment and decorative corner accent.',
                'choices' => [
                    'default'         => 'Default Cards',
                    'icon_feature'    => 'Icon Feature Cards',
                    'decorative_info' => 'Decorative Info Cards',
                ],
                'default_value' => 'default',
                'ui' => 1,
            ])
            ->addRange('columns_d', [
                'label' => 'Columns (Desktop)',
                'min' => 1, 'max' => 4, 'step' => 1, 'default_value' => 3,
            ])
            ->addRange('columns_t', [
                'label' => 'Columns (Tablet)',
                'min' => 1, 'max' => 3, 'step' => 1, 'default_value' => 2,
            ])
            ->addRange('columns_m', [
                'label' => 'Columns (Mobile)',
                'min' => 1, 'max' => 2, 'step' => 1, 'default_value' => 1,
            ])
            ->addSelect('gap', [
                'label' => 'Gap',
                'choices' => [
                    'sm' => 'Small',
                    'md' => 'Medium',
                    'lg' => 'Large',
                ],
                'default_value' => 'md',
                'ui' => 1,
            ])
            ->addSelect('card_style', [
                'label' => 'Card Style',
                'instructions' => 'Used by the Default Cards layout. The Icon Feature and Decorative Info layouts have their own built-in styling.',
                'choices' => [
                    'standard' => 'Standard',
                    'glow'     => 'Glow (hover bloom)',
                    'image'    => 'Image-heavy',
                ],
                'default_value' => 'standard',
                'ui' => 1,
            ])
            ->addTrueFalse('equal_height', [
                'label' => 'Equal height cards',
                'ui' => 1,
                'default_value' => 1,
            ])

            ->addTab('Cards', ['placement' => 'top'])
            ->addRepeater('cards', [
                'label'        => 'Cards',
                'layout'       => 'block',
                'button_label' => 'Add Card',
                'min'          => 1,
            ])
                ->addSelect('variant', [
                    'label' => 'Card Variant',
                    'choices' => [
                        'standard' => 'Standard',
                        'meta'     => 'With Meta + Icon (optional)',
                    ],
                    'default_value' => 'standard',
                    'ui' => 1,
                ])

                ->addImage('image', [
                    'label'         => 'Image (optional)',
                    'return_format' => 'array',
                    'preview_size'  => 'medium',
                ])

                ->addText('badge', [
                    'label' => 'Chip / Badge / Small Label (optional)',
                    'instructions' => 'Default cards use this as a chip. Icon Feature cards use this as the small uppercase label. If an image exists, this becomes a cut-corner chip.',
                ])

                ->addSelect('badge_tone', [
                    'label' => 'Chip / Accent color',
                    'instructions' => 'Also controls the accent color for Icon Feature and Decorative Info card icons.',
                    'choices' => [
                        'primary' => 'Primary',
                        'signal' => 'Signal',
                        'accent'  => 'Accent',
                        'success' => 'Success',
                        'warning' => 'Warning',
                        'neutral' => 'Neutral',
                    ],
                    'default_value' => 'primary',
                    'ui' => 1,
                ])

                ->addText('title', [
                    'label'    => 'Title',
                    'required' => 1,
                ])
                ->addTextarea('text', [
                    'label' => 'Text (optional)',
                    'rows'  => 3,
                    'new_lines' => 'br',
                ])

                ->addLink('link', [
                    'label' => 'Link (optional)',
                    'return_format' => 'array',
                ])

                ->addGroup('meta_line', [
                    'label' => 'Meta line (optional)',
                ])->conditional('variant', '==', 'meta')
                    ->addText('label', ['label' => 'Label (optional)', 'placeholder' => 'e.g. Date'])
                    ->addText('value', ['label' => 'Value (optional)', 'placeholder' => 'e.g. Nov 12'])
                ->endGroup()

                ->addSelect('icon_mode', [
                    'label' => 'Icon Source',
                    'instructions' => 'Used by Meta cards, Icon Feature cards, and Decorative Info cards.',
                    'choices' => [
                        'none'   => 'None',
                        'select' => 'Dropdown (Material Symbols)',
                        'custom' => 'Custom icon name',
                    ],
                    'default_value' => 'none',
                    'ui' => 1,
                ])

                ->addSelect('icon_name', [
                    'label'   => 'Icon (Material Symbols)',
                    'choices' => $this->iconChoices(),
                    'ui'      => 1,
                ])->conditional('icon_mode', '==', 'select')

                ->addText('icon_custom', [
                    'label'        => 'Icon (Custom name)',
                    'instructions' => 'Paste a Material Symbols icon name (e.g. "rocket_launch").',
                ])->conditional('icon_mode', '==', 'custom')

                ->addSelect('icon_size', [
                    'label' => 'Icon Size',
                    'instructions' => 'Controls the displayed icon size for this card. Larger sizes keep the circle tight while allowing the glyph to take center stage.',
                    'choices' => [
                        'default'  => 'Default',
                        'medium'   => 'Medium',
                        'large'    => 'Large',
                        'x-large'  => 'X-Large',
                        'xx-large' => 'XX-Large',
                    ],
                    'default_value' => 'default',
                    'ui' => 1,
                ])

                ->addSelect('icon_color', [
                    'label' => 'Icon Color',
                    'instructions' => 'Controls only the icon color. Use Card Accent keeps the icon tied to the card chip/accent tone.',
                    'choices' => [
                        'card'    => 'Use Card Accent',
                        'primary' => 'Primary',
                        'signal'  => 'Signal',
                        'accent'  => 'Accent',
                        'success' => 'Success',
                        'warning' => 'Warning',
                        'neutral' => 'Neutral',
                        'text'    => 'Text',
                        'muted'   => 'Muted',
                    ],
                    'default_value' => 'card',
                    'ui' => 1,
                ])

            ->endRepeater();

        return $fields->build();
    }

    private function iconChoices(): array
    {
        return [
            'rocket_launch'     => 'rocket_launch',
            'light_mode'        => 'light_mode',
            'dark_mode'         => 'dark_mode',
            'arrow_forward'     => 'arrow_forward',

            'open_in_new'       => 'open_in_new',
            'shopping_bag'      => 'shopping_bag',
            'add_shopping_cart' => 'add_shopping_cart',

            'person'            => 'person',

            'calendar_month'    => 'calendar_month',
            'event'             => 'event',
            'event_available'   => 'event_available',
            'location_on'       => 'location_on',
            'schedule'          => 'schedule',
            'ticket'            => 'ticket',
            'map'               => 'map',
            'local_cafe'        => 'local_cafe',
            'local_activity'    => 'local_activity',

            'card_membership'   => 'card_membership',
            'verified'          => 'verified',
            'verified_user'     => 'verified_user',
            'shield'            => 'shield',
            'military_tech'     => 'military_tech',
            'emoji_events'      => 'emoji_events',
            'workspace_premium' => 'workspace_premium',
            'redeem'            => 'redeem',
            'payments'          => 'payments',

            'check_circle'      => 'check_circle',
            'checklist'         => 'checklist',
            'settings'          => 'settings',
            'build'             => 'build',
            'build_circle'      => 'build_circle',
            'auto_fix'          => 'auto_fix',
            'speed'             => 'speed',
            'palette'           => 'palette',
            'chat'              => 'chat',
            'contact_support'   => 'contact_support',
            'email'             => 'email',
            'support_agent'     => 'support_agent',
            'groups'            => 'groups',
            'diversity_3'       => 'diversity_3',
            'timeline'          => 'timeline',

            'handshake'          => 'handshake',
            'campaign'           => 'campaign',
            'storefront'         => 'storefront',
            'insights'           => 'insights',
            'badge'              => 'badge',
            'sell'               => 'sell',
            'volunteer_activism' => 'volunteer_activism',
            'public'             => 'public',
        ];
    }

    private function normalizeCards(array $cards): array
    {
        return array_map(function ($c) {
            $variant = (string) ($c['variant'] ?? 'standard');

            $icon_mode   = (string) ($c['icon_mode'] ?? 'none');
            $icon_name   = (string) ($c['icon_name'] ?? '');
            $icon_custom = (string) ($c['icon_custom'] ?? '');
            $icon_size   = $this->sanitizeIconSize((string) ($c['icon_size'] ?? 'default'));
            $icon_color  = $this->sanitizeIconColor((string) ($c['icon_color'] ?? 'card'));

            $icon = '';
            if ($icon_mode === 'select' && $icon_name) {
                $icon = $icon_name;
            }

            if ($icon_mode === 'custom' && $icon_custom) {
                $icon = $icon_custom;
            }

            $img = $c['image'] ?? null;

            return [
                'variant'     => $variant,

                'image_url'   => is_array($img) ? ($img['url'] ?? '') : '',
                'image_alt'   => is_array($img) ? ($img['alt'] ?? '') : '',

                'badge'       => (string) ($c['badge'] ?? ''),
                'badge_tone'  => (string) ($c['badge_tone'] ?? 'primary'),

                'title'       => (string) ($c['title'] ?? ''),
                'text'        => (string) ($c['text'] ?? ''),

                'link'        => is_array($c['link'] ?? null) ? $c['link'] : null,

                'meta_label'  => (string) (($c['meta_line']['label'] ?? '') ?: ''),
                'meta_value'  => (string) (($c['meta_line']['value'] ?? '') ?: ''),

                'icon'        => $icon,
                'icon_size'   => $icon_size,
                'icon_color'  => $icon_color,
            ];
        }, $cards);
    }

    /**
     * Convert [[highlighted text]] into controlled highlight span markup.
     *
     * This intentionally supports <strong> while stripping other editor-entered
     * HTML. The selected highlight style is controlled by the ACF select field.
     */
    private function applyHighlightSyntax(string $text, string $highlightStyle = 'club-blue'): string
    {
        if ($text === '') {
            return '';
        }

        $highlightStyle = $this->sanitizeHighlightStyle($highlightStyle);

        $brToken = '___SCCC_CARD_GRID_BR___';

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
     * Restrict card icon size values to the current editor choices.
     *
     * The Blade template converts this controlled value into scoped CSS variables,
     * which keeps icon sizing predictable across all card layout modes.
     */
    private function sanitizeIconSize(string $iconSize): string
    {
        $iconSize = strtolower(trim($iconSize));

        $allowed = [
            'default',
            'medium',
            'large',
            'x-large',
            'xx-large',
        ];

        return in_array($iconSize, $allowed, true) ? $iconSize : 'default';
    }

    /**
     * Restrict card icon color values to the current editor choices.
     *
     * The Blade template maps these values to existing theme-safe tokens.
     */
    private function sanitizeIconColor(string $iconColor): string
    {
        $iconColor = strtolower(trim($iconColor));

        $allowed = [
            'card',
            'primary',
            'signal',
            'accent',
            'success',
            'warning',
            'neutral',
            'text',
            'muted',
        ];

        return in_array($iconColor, $allowed, true) ? $iconColor : 'card';
    }

    /**
     * Restrict card layout mode to the current editor choices.
     */
    private function sanitizeCardLayout(string $cardLayout): string
    {
        $cardLayout = strtolower(trim($cardLayout));

        $allowed = [
            'default',
            'icon_feature',
            'decorative_info',
        ];

        return in_array($cardLayout, $allowed, true) ? $cardLayout : 'default';
    }
}