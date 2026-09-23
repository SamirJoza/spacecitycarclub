<?php

namespace App\Blocks;

use Log1x\AcfComposer\Block;
use Log1x\AcfComposer\Builder;

/**
 * File path + filename: app/Blocks/ScccAccordion.php
 *
 * Purpose:
 * - Register the SCCC Accordion Gutenberg block.
 * - Provide editor-controlled accordion sections and items.
 *
 * Notes:
 * - The Blade view is explicitly defined to avoid ACF Composer deriving
 *   the wrong view name from the SCCC acronym.
 * - Styling is intentionally handled at the block render level in:
 *   resources/views/blocks/sccc-accordion.blade.php
 */
class ScccAccordion extends Block
{
    public $name = 'SCCC Accordion';

    public $view = 'blocks.sccc-accordion';

    public $description = 'Theme-styled accordion section for grouped page content.';

    public $category = 'theme-blocks';

    public $icon = 'editor-help';

    public $keywords = ['accordion', 'faq', 'toggle', 'content'];

    public $post_types = ['post', 'page'];

    public $mode = 'preview';

    public $supports = [
        'align' => ['wide', 'full'],
        'anchor' => true,
    ];

    public function fields(): array
    {
        $fields = Builder::make('sccc_accordion');

        $fields
            ->addText('eyebrow', [
                'label' => 'Eyebrow',
                'instructions' => 'Optional small label above the headline.',
            ])
            ->addText('headline', [
                'label' => 'Headline',
                'instructions' => 'Main heading for the accordion block.',
            ])
            ->addTextarea('intro', [
                'label' => 'Intro Text',
                'instructions' => 'Optional short intro text below the headline.',
                'rows' => 3,
                'new_lines' => 'br',
            ])
            ->addTrueFalse('open_first_item', [
                'label' => 'Open first item by default?',
                'ui' => 1,
                'default_value' => 1,
            ])
            ->addTrueFalse('allow_multiple_open', [
                'label' => 'Allow multiple items open at once?',
                'instructions' => 'When disabled, opening one item closes the others inside the same section.',
                'ui' => 1,
                'default_value' => 0,
            ])
            ->addRepeater('sections', [
                'label' => 'Accordion Sections',
                'button_label' => 'Add Section',
                'layout' => 'block',
                'min' => 1,
            ])
                ->addText('section_label', [
                    'label' => 'Section Label',
                    'required' => 1,
                ])
                ->addTextarea('section_description', [
                    'label' => 'Section Description',
                    'rows' => 2,
                    'new_lines' => 'br',
                ])
                ->addRepeater('items', [
                    'label' => 'Accordion Items',
                    'button_label' => 'Add Item',
                    'layout' => 'block',
                    'min' => 1,
                ])
                    ->addText('question', [
                        'label' => 'Question / Title',
                        'required' => 1,
                    ])
                    ->addWysiwyg('answer', [
                        'label' => 'Answer / Content',
                        'tabs' => 'all',
                        'toolbar' => 'basic',
                        'media_upload' => 0,
                        'delay' => 1,
                    ])
                ->endRepeater()
            ->endRepeater();

        return $fields->build();
    }

    public function with(): array
    {
        return [
            'eyebrow' => (string) get_field('eyebrow'),
            'headline' => (string) get_field('headline'),
            'intro' => (string) get_field('intro'),
            'openFirstItem' => (bool) get_field('open_first_item'),
            'allowMultipleOpen' => (bool) get_field('allow_multiple_open'),
            'sections' => $this->sections(),
        ];
    }

    protected function sections(): array
    {
        $sections = get_field('sections');

        if (empty($sections) || ! is_array($sections)) {
            return [];
        }

        return collect($sections)
            ->map(function ($section) {
                $items = $section['items'] ?? [];

                return [
                    'label' => (string) ($section['section_label'] ?? ''),
                    'description' => (string) ($section['section_description'] ?? ''),
                    'items' => is_array($items)
                        ? collect($items)
                            ->map(fn ($item) => [
                                'question' => (string) ($item['question'] ?? ''),
                                'answer' => (string) ($item['answer'] ?? ''),
                            ])
                            ->filter(fn ($item) => $item['question'] !== '' || $item['answer'] !== '')
                            ->values()
                            ->all()
                        : [],
                ];
            })
            ->filter(fn ($section) => $section['label'] !== '' || ! empty($section['items']))
            ->values()
            ->all();
    }
}