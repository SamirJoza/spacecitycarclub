<?php

/**
 * File: app/Blocks/EventImpact.php
 *
 * Event Impact Flow Gutenberg block.
 *
 * Purpose:
 * This block renders a flexible "impact pathway" that can be reused for
 * event flows, mission journeys, process sections, and other step-based content.
 *
 * This revision keeps the current block behavior and finalizes the layout
 * controls:
 * - Text alignment supports left, center, and justified.
 * - Headline, intro copy, cards, and closing copy all use the selected
 *   available block width.
 * - Closing blockquotes remain a special editorial treatment handled in Blade.
 * - Card decoration remains a single choice: large number, mirrored label,
 *   or none.
 * - Connector gradient remains independently selectable.
 */

namespace App\Blocks;

use Log1x\AcfComposer\Block;
use Log1x\AcfComposer\Builder;

class EventImpact extends Block
{
    /**
     * The block name shown in the editor.
     *
     * @var string
     */
    public $name = 'Event Impact Flow';

    /**
     * The block description shown in the editor.
     *
     * @var string
     */
    public $description = 'A flexible impact flow for showing how events, gatherings, or programs create community support.';

    /**
     * The block category.
     *
     * @var string
     */
    public $category = 'design';

    /**
     * The block icon.
     *
     * @var string
     */
    public $icon = 'heart';

    /**
     * Data passed to the Blade view before rendering.
     *
     * @return array
     */
    public function with()
    {
        return [
            'headline'            => $this->headline(),
            'intro'               => $this->intro(),
            'steps'               => $this->steps(),
            'closingCopy'         => $this->closingCopy(),
            'layoutDirection'     => $this->allowedOption('layout_direction', 'horizontal', ['horizontal', 'vertical']),
            'contentWidth'        => $this->allowedOption('content_width', 'default', ['narrow', 'default', 'wide']),
            'textAlign'           => $this->allowedOption('text_align', 'left', ['left', 'center', 'justify']),
            'backgroundTreatment' => $this->allowedOption('background_treatment', 'transparent', ['transparent', 'surface', 'gradient', 'dark_panel']),
            'stepStyle'           => $this->allowedOption('step_style', 'glass', ['glass', 'solid', 'minimal']),
            'connectorStyle'      => $this->allowedOption('connector_style', 'arrow', ['line', 'arrow', 'none']),
            'connectorTheme'      => $this->allowedOption('connector_theme', 'brand', ['brand', 'blue_purple', 'neon_green']),
            'accentColor'         => $this->allowedOption('accent_color', 'mixed', ['mixed', 'blue', 'red', 'purple']),
            'cardDecoration'      => $this->cardDecoration(),
            'paddingTop'          => $this->allowedOption('padding_top', 'large', ['none', 'small', 'medium', 'large', 'xlarge']),
            'paddingBottom'       => $this->allowedOption('padding_bottom', 'large', ['none', 'small', 'medium', 'large', 'xlarge']),
        ];
    }

    /**
     * The block field group.
     *
     * @return array
     */
    public function fields()
    {
        $fields = Builder::make('event_impact');

        $fields
            ->addTab('Content')

            ->addText('headline', [
                'label' => 'Headline',
                'instructions' => 'Main section headline.',
                'default_value' => 'Every Event Can Be a Chance to Serve',
                'wrapper' => [
                    'width' => '100',
                ],
            ])

            ->addWysiwyg('intro', [
                'label' => 'Intro Copy',
                'instructions' => 'Opening copy that explains the purpose of the flow.',
                'tabs' => 'visual',
                'toolbar' => 'basic',
                'media_upload' => 0,
                'delay' => 0,
                'default_value' => '<p>Car events bring people together in a natural way. People come for the vehicles, the sound, the stories, the families, the friends, and the feeling of being part of something.</p><p>That gives every event a bigger opportunity.</p>',
            ])

            ->addRepeater('steps', [
                'label' => 'Impact Steps',
                'instructions' => 'Add the steps in the impact flow. Three steps usually works best.',
                'layout' => 'block',
                'button_label' => 'Add impact step',
                'min' => 1,
                'max' => 6,
            ])
                ->addText('label', [
                    'label' => 'Small Label',
                    'instructions' => 'Optional. Example: Awareness, Support, Community.',
                    'wrapper' => [
                        'width' => '30',
                    ],
                ])
                ->addText('title', [
                    'label' => 'Step Title',
                    'instructions' => 'Short, clear step title.',
                    'wrapper' => [
                        'width' => '70',
                    ],
                ])
                ->addTextarea('description', [
                    'label' => 'Step Description',
                    'instructions' => 'Short supporting copy for this step.',
                    'rows' => 3,
                    'new_lines' => 'wpautop',
                ])
            ->endRepeater()

            ->addWysiwyg('closing_copy', [
                'label' => 'Closing Copy',
                'instructions' => 'Optional closing line or paragraph below the flow.',
                'tabs' => 'visual',
                'toolbar' => 'basic',
                'media_upload' => 0,
                'delay' => 0,
                'default_value' => '<p>A successful Space City Car Club event is not only measured by how many vehicles show up. It is also measured by the conversations started, the needs met, and the people who walk away ready to help.</p>',
            ])

            ->addTab('Layout')

            ->addSelect('layout_direction', [
                'label' => 'Layout Direction',
                'instructions' => 'Horizontal works well for three steps. Vertical works better for longer flows or narrow sections.',
                'choices' => [
                    'horizontal' => 'Horizontal flow',
                    'vertical' => 'Vertical flow',
                ],
                'default_value' => 'horizontal',
                'ui' => 1,
                'return_format' => 'value',
                'wrapper' => [
                    'width' => '50',
                ],
            ])

            ->addSelect('content_width', [
                'label' => 'Content Width',
                'instructions' => 'Controls the max width of the inner content.',
                'choices' => [
                    'narrow' => 'Narrow',
                    'default' => 'Default',
                    'wide' => 'Wide',
                ],
                'default_value' => 'default',
                'ui' => 1,
                'return_format' => 'value',
                'wrapper' => [
                    'width' => '50',
                ],
            ])

            ->addSelect('text_align', [
                'label' => 'Text Alignment',
                'instructions' => 'Controls the alignment of the headline, intro, cards, and closing copy.',
                'choices' => [
                    'left' => 'Left',
                    'center' => 'Center',
                    'justify' => 'Justified',
                ],
                'default_value' => 'left',
                'ui' => 1,
                'return_format' => 'value',
                'wrapper' => [
                    'width' => '50',
                ],
            ])

            ->addSelect('connector_style', [
                'label' => 'Connector Style',
                'instructions' => 'Controls the visual connector between steps.',
                'choices' => [
                    'line' => 'Line',
                    'arrow' => 'Arrow',
                    'none' => 'None',
                ],
                'default_value' => 'arrow',
                'ui' => 1,
                'return_format' => 'value',
                'wrapper' => [
                    'width' => '50',
                ],
            ])

            ->addTab('Style')

            ->addSelect('background_treatment', [
                'label' => 'Background Treatment',
                'instructions' => 'Controls the section background.',
                'choices' => [
                    'transparent' => 'Transparent',
                    'surface' => 'Surface panel',
                    'gradient' => 'Subtle neon gradient',
                    'dark_panel' => 'Dark cinematic panel',
                ],
                'default_value' => 'transparent',
                'ui' => 1,
                'return_format' => 'value',
                'wrapper' => [
                    'width' => '50',
                ],
            ])

            ->addSelect('step_style', [
                'label' => 'Step Style',
                'instructions' => 'Controls how each step is visually framed.',
                'choices' => [
                    'glass' => 'Glass',
                    'solid' => 'Solid',
                    'minimal' => 'Minimal',
                ],
                'default_value' => 'glass',
                'ui' => 1,
                'return_format' => 'value',
                'wrapper' => [
                    'width' => '50',
                ],
            ])

            ->addSelect('card_decoration', [
                'label' => 'Card Decoration',
                'instructions' => 'Choose one decorative background treatment inside each card.',
                'choices' => [
                    'number' => 'Large step number',
                    'label' => 'Mirrored small label',
                    'none' => 'None',
                ],
                'default_value' => 'number',
                'ui' => 1,
                'return_format' => 'value',
                'wrapper' => [
                    'width' => '50',
                ],
            ])

            ->addSelect('accent_color', [
                'label' => 'Card Accent Color',
                'instructions' => 'Controls the accent treatment used for card labels and borders.',
                'choices' => [
                    'mixed' => 'Blue and red mix',
                    'blue' => 'Electric blue',
                    'red' => 'Neon red',
                    'purple' => 'Purple',
                ],
                'default_value' => 'mixed',
                'ui' => 1,
                'return_format' => 'value',
                'wrapper' => [
                    'width' => '50',
                ],
            ])

            ->addSelect('connector_theme', [
                'label' => 'Connector Gradient',
                'instructions' => 'Controls the gradient used on the connector badge only.',
                'choices' => [
                    'brand' => 'Brand Blue to Red',
                    'blue_purple' => 'Blue to Purple',
                    'neon_green' => 'Neon Green',
                ],
                'default_value' => 'brand',
                'ui' => 1,
                'return_format' => 'value',
                'wrapper' => [
                    'width' => '50',
                ],
            ])

            ->addSelect('padding_top', [
                'label' => 'Top Spacing',
                'instructions' => 'Controls top padding of the section.',
                'choices' => [
                    'none' => 'None',
                    'small' => 'Small',
                    'medium' => 'Medium',
                    'large' => 'Large',
                    'xlarge' => 'Extra large',
                ],
                'default_value' => 'large',
                'ui' => 1,
                'return_format' => 'value',
                'wrapper' => [
                    'width' => '50',
                ],
            ])

            ->addSelect('padding_bottom', [
                'label' => 'Bottom Spacing',
                'instructions' => 'Controls bottom padding of the section.',
                'choices' => [
                    'none' => 'None',
                    'small' => 'Small',
                    'medium' => 'Medium',
                    'large' => 'Large',
                    'xlarge' => 'Extra large',
                ],
                'default_value' => 'large',
                'ui' => 1,
                'return_format' => 'value',
                'wrapper' => [
                    'width' => '50',
                ],
            ]);

        return $fields->build();
    }

    /**
     * Returns the headline field with a safe default.
     *
     * @return string
     */
    public function headline(): string
    {
        return (string) (get_field('headline') ?: 'Every Event Can Be a Chance to Serve');
    }

    /**
     * Returns intro copy with a safe default.
     *
     * @return string
     */
    public function intro(): string
    {
        return (string) (get_field('intro') ?: '<p>Car events bring people together in a natural way. People come for the vehicles, the sound, the stories, the families, the friends, and the feeling of being part of something.</p><p>That gives every event a bigger opportunity.</p>');
    }

    /**
     * Returns the impact steps.
     *
     * If the editor has not added steps yet, the block still renders a useful
     * default flow so the preview is not empty.
     *
     * @return array
     */
    public function steps(): array
    {
        $steps = get_field('steps');

        if (! is_array($steps) || empty($steps)) {
            return $this->defaultSteps();
        }

        $steps = array_map(function ($step) {
            $title = isset($step['title']) ? trim((string) $step['title']) : '';

            if ($title === '') {
                return null;
            }

            return [
                'label' => isset($step['label']) ? trim((string) $step['label']) : '',
                'title' => $title,
                'description' => isset($step['description']) ? (string) $step['description'] : '',
            ];
        }, $steps);

        $steps = array_filter($steps);

        return ! empty($steps) ? array_values($steps) : $this->defaultSteps();
    }

    /**
     * Returns optional closing copy.
     *
     * @return string
     */
    public function closingCopy(): string
    {
        return (string) (get_field('closing_copy') ?: '<p>A successful Space City Car Club event is not only measured by how many vehicles show up. It is also measured by the conversations started, the needs met, and the people who walk away ready to help.</p>');
    }

    /**
     * Returns the decorative card treatment.
     *
     * The current field is card_decoration.
     *
     * Backward compatibility:
     * If older block instances only have show_numbers saved, keep the same
     * visual behavior. If show_numbers was off, return none. Otherwise default
     * to number.
     *
     * @return string
     */
    private function cardDecoration(): string
    {
        $value = get_field('card_decoration');

        if (is_string($value) && in_array($value, ['number', 'label', 'none'], true)) {
            return $value;
        }

        $legacyShowNumbers = get_field('show_numbers');

        if ($legacyShowNumbers === false || $legacyShowNumbers === '0' || $legacyShowNumbers === 0) {
            return 'none';
        }

        return 'number';
    }

    /**
     * Default steps used before editor content is saved.
     *
     * @return array
     */
    private function defaultSteps(): array
    {
        return [
            [
                'label' => 'Awareness',
                'title' => 'Raise Awareness',
                'description' => '<p>Every event gives people a chance to learn about the cause, the need, and why it matters close to home.</p>',
            ],
            [
                'label' => 'Support',
                'title' => 'Collect Support',
                'description' => '<p>Donation drives, raffles, and sponsor-backed efforts help turn a gathering into practical help.</p>',
            ],
            [
                'label' => 'Community',
                'title' => 'Bring People Together',
                'description' => '<p>Members, guests, families, and local supporters create the kind of community that makes real impact possible.</p>',
            ],
        ];
    }

    /**
     * Returns a safe select field value.
     *
     * @param string $field
     * @param string $default
     * @param array  $allowed
     * @return string
     */
    private function allowedOption(string $field, string $default, array $allowed): string
    {
        $value = get_field($field);

        if (! is_string($value) || ! in_array($value, $allowed, true)) {
            return $default;
        }

        return $value;
    }
}