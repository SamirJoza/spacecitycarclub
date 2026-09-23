<?php

/**
 * File: app/Blocks/ActionSteps.php
 *
 * Action Steps Gutenberg block.
 *
 * This block creates a reusable "next steps" section for public-facing pages.
 * It is intentionally not charity-specific, so it can be reused on the
 * Community Impact page, sponsor pages, membership pages, event pages, and
 * other landing pages where we want to guide visitors toward action.
 *
 * The design avoids another card grid. Instead, it creates a two-column layout:
 * - Left side: headline, body copy, optional closing copy, optional CTA
 * - Right side: numbered action rows
 *
 * This keeps the section useful without making the page feel like repeated cards.
 */

namespace App\Blocks;

use Log1x\AcfComposer\Block;
use Log1x\AcfComposer\Builder;

class ActionSteps extends Block
{
    /**
     * The block display name shown in the Gutenberg editor.
     *
     * @var string
     */
    public $name = 'Action Steps';

    /**
     * The block description shown in the Gutenberg inserter.
     *
     * @var string
     */
    public $description = 'A reusable next-steps section with intro copy and numbered action rows.';

    /**
     * The block category shown in Gutenberg.
     *
     * Keep this broad so the block is easy to find.
     *
     * @var string
     */
    public $category = 'design';

    /**
     * The Dashicon shown in Gutenberg.
     *
     * @var string
     */
    public $icon = 'list-view';

    /**
     * Search terms for the Gutenberg inserter.
     *
     * @var array
     */
    public $keywords = ['action', 'steps', 'impact', 'next steps', 'cta'];

    /**
     * Gutenberg mode.
     *
     * Preview keeps the editor useful without forcing the user into edit mode first.
     *
     * @var string
     */
    public $mode = 'preview';

    /**
     * Supported block features.
     *
     * Anchor and custom class support are useful for page sections.
     *
     * @var array
     */
    public $supports = [
        'align' => false,
        'anchor' => true,
        'customClassName' => true,
        'jsx' => true,
    ];

    /**
     * Data passed to the Blade template before rendering.
     *
     * Each method keeps the template cleaner and centralizes defaults.
     *
     * @return array
     */
    public function with()
    {
        return [
            'headline' => $this->headline(),
            'description' => $this->descriptionCopy(),
            'closing_copy' => $this->closingCopy(),
            'steps' => $this->steps(),
            'primary_cta' => $this->primaryCta(),
            'background_treatment' => $this->backgroundTreatment(),
            'padding_top' => $this->paddingTop(),
            'padding_bottom' => $this->paddingBottom(),
            'show_numbers' => $this->showNumbers(),
        ];
    }

    /**
     * Block field group.
     *
     * The fields are organized into content, steps, call to action, and settings.
     * This makes the editor easier to use while keeping the output flexible.
     *
     * @return array
     */
    public function fields()
    {
        $fields = Builder::make('action_steps');

        $fields
            /**
             * Content tab.
             *
             * The main copy explains the section and should stay short enough
             * to avoid repeating the rest of the page.
             */
            ->addTab('content', [
                'label' => 'Content',
                'placement' => 'top',
            ])
                ->addText('headline', [
                    'label' => 'Headline',
                    'instructions' => 'Example: How You Can Help',
                    'required' => 1,
                    'default_value' => 'How You Can Help',
                ])
                ->addWysiwyg('description', [
                    'label' => 'Description',
                    'instructions' => 'Short body copy for the left side of the section.',
                    'required' => 0,
                    'tabs' => 'visual',
                    'toolbar' => 'basic',
                    'media_upload' => 0,
                    'delay' => 0,
                ])
                ->addWysiwyg('closing_copy', [
                    'label' => 'Closing Copy',
                    'instructions' => 'Optional short closing statement below the main description.',
                    'required' => 0,
                    'tabs' => 'visual',
                    'toolbar' => 'basic',
                    'media_upload' => 0,
                    'delay' => 0,
                ])

            /**
             * Steps tab.
             *
             * These are rows, not cards. The goal is a clean action list that
             * breaks up the page visually and avoids another grid.
             */
            ->addTab('steps_tab', [
                'label' => 'Steps',
                'placement' => 'top',
            ])
                ->addRepeater('steps', [
                    'label' => 'Action Steps',
                    'instructions' => 'Add 2 to 5 steps. Three or four usually works best.',
                    'required' => 0,
                    'layout' => 'block',
                    'button_label' => 'Add Step',
                    'min' => 1,
                    'max' => 6,
                ])
                    ->addText('step_title', [
                        'label' => 'Step Title',
                        'instructions' => 'Example: Attend an Event',
                        'required' => 1,
                    ])
                    ->addTextarea('step_description', [
                        'label' => 'Step Description',
                        'instructions' => 'Keep this short and public-facing.',
                        'required' => 0,
                        'rows' => 2,
                        'new_lines' => '',
                    ])
                    ->addLink('step_link', [
                        'label' => 'Optional Step Link',
                        'instructions' => 'Optional link for this specific step.',
                        'required' => 0,
                        'return_format' => 'array',
                    ])
                ->endRepeater()

            /**
             * CTA tab.
             *
             * This optional CTA gives the section one stronger conversion point
             * without forcing every row to become a button.
             */
            ->addTab('cta', [
                'label' => 'CTA',
                'placement' => 'top',
            ])
                ->addLink('primary_cta', [
                    'label' => 'Primary CTA',
                    'instructions' => 'Optional button shown under the left-side copy.',
                    'required' => 0,
                    'return_format' => 'array',
                ])

            /**
             * Settings tab.
             *
             * These settings match the section-level controls we have been using:
             * background treatment and top/bottom spacing keywords.
             */
            ->addTab('settings', [
                'label' => 'Settings',
                'placement' => 'top',
            ])
                ->addSelect('background_treatment', [
                    'label' => 'Background Treatment',
                    'instructions' => 'Choose how the full-width section background should render.',
                    'choices' => [
                        'none' => 'None',
                        'surface' => 'Surface',
                        'subtle' => 'Subtle Gradient',
                        'dark' => 'Dark Contrast',
                    ],
                    'default_value' => 'subtle',
                    'ui' => 1,
                    'return_format' => 'value',
                ])
                ->addSelect('padding_top', [
                    'label' => 'Top Padding',
                    'choices' => [
                        'none' => 'None',
                        's' => 'Small',
                        'm' => 'Medium',
                        'l' => 'Large',
                        'xl' => 'Extra Large',
                        'xxl' => 'XXL',
                    ],
                    'default_value' => 'xl',
                    'ui' => 1,
                    'return_format' => 'value',
                ])
                ->addSelect('padding_bottom', [
                    'label' => 'Bottom Padding',
                    'choices' => [
                        'none' => 'None',
                        's' => 'Small',
                        'm' => 'Medium',
                        'l' => 'Large',
                        'xl' => 'Extra Large',
                        'xxl' => 'XXL',
                    ],
                    'default_value' => 'xl',
                    'ui' => 1,
                    'return_format' => 'value',
                ])
                ->addTrueFalse('show_numbers', [
                    'label' => 'Show Step Numbers',
                    'instructions' => 'Display 01, 02, 03 style numbers beside each step.',
                    'default_value' => 1,
                    'ui' => 1,
                ]);

        return $fields->build();
    }

    /**
     * Return the section headline.
     *
     * @return string
     */
    public function headline()
    {
        return get_field('headline') ?: 'How You Can Help';
    }

    /**
     * Return the main section description.
     *
     * WYSIWYG output is sanitized in the Blade view.
     *
     * @return string
     */
    public function descriptionCopy()
    {
        return get_field('description') ?: '';
    }

    /**
     * Return optional closing copy.
     *
     * @return string
     */
    public function closingCopy()
    {
        return get_field('closing_copy') ?: '';
    }

    /**
     * Return the action steps repeater.
     *
     * @return array
     */
    public function steps()
    {
        return get_field('steps') ?: [];
    }

    /**
     * Return the optional primary CTA.
     *
     * @return array|null
     */
    public function primaryCta()
    {
        return get_field('primary_cta') ?: null;
    }

    /**
     * Return the background treatment keyword.
     *
     * @return string
     */
    public function backgroundTreatment()
    {
        return get_field('background_treatment') ?: 'subtle';
    }

    /**
     * Return the top padding keyword.
     *
     * @return string
     */
    public function paddingTop()
    {
        return get_field('padding_top') ?: 'xl';
    }

    /**
     * Return the bottom padding keyword.
     *
     * @return string
     */
    public function paddingBottom()
    {
        return get_field('padding_bottom') ?: 'xl';
    }

    /**
     * Return whether the step numbers should be displayed.
     *
     * @return bool
     */
    public function showNumbers()
    {
        return (bool) get_field('show_numbers');
    }
}