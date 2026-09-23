<?php

namespace App\Fields;

use Log1x\AcfComposer\Builder;
use Log1x\AcfComposer\Field;

/**
 * File: app/Fields/LegalDocumentSettings.php
 *
 * Field Group: Legal Document Settings
 *
 * Purpose:
 * Adds editor-friendly controls for pages using the Legal Document template.
 *
 * Why this exists:
 * The legal template should not force editors to rely on the WordPress excerpt,
 * modified date, or hardcoded Blade copy. These fields make the hero area,
 * document meta, and closing note editable directly from the page editor.
 *
 * Important:
 * The page body should contain the actual legal sections only. The visible
 * document title, intro, last updated text, and bottom note are handled here.
 */
class LegalDocumentSettings extends Field
{
    /**
     * Build the ACF field group.
     *
     * @return array
     */
    public function fields(): array
    {
        $fields = Builder::make('legal_document_settings', [
            'title' => 'Legal Document Settings',
            'position' => 'acf_after_title',
            'style' => 'default',
        ]);

        /*
        |--------------------------------------------------------------------------
        | Location Rules
        |--------------------------------------------------------------------------
        |
        | This field group is intended for the Legal Document page template.
        | Sage/WordPress template slugs can vary depending on how the theme wrapper
        | exposes Blade templates, so the common Sage/Blade values are included.
        |
        */

        $fields
            ->setLocation('page_template', '==', 'template-legal.blade.php')
                ->or('page_template', '==', 'views/template-legal.blade.php')
                ->or('page_template', '==', 'resources/views/template-legal.blade.php');

        /*
        |--------------------------------------------------------------------------
        | Hero / Document Header Fields
        |--------------------------------------------------------------------------
        |
        | These fields control the top legal document hero so the body content can
        | start with the first real legal section instead of duplicating the title.
        |
        */

        $fields
            ->addText('legal_document_eyebrow', [
                'label' => 'Eyebrow Text',
                'instructions' => 'Small label above the document title.',
                'default_value' => 'Space City Car Club Legal Document',
                'placeholder' => 'Space City Car Club Legal Document',
            ])
            ->addText('legal_document_title', [
                'label' => 'Document Title',
                'instructions' => 'Optional. Leave blank to use the WordPress page title.',
                'placeholder' => 'Terms and Conditions',
            ])
            ->addTextarea('legal_document_intro', [
                'label' => 'Intro Text',
                'instructions' => 'Short intro displayed under the document title. Leave blank to use the page excerpt, then the template fallback.',
                'rows' => 4,
                'new_lines' => '',
                'placeholder' => 'These Terms explain the rules for membership, events, merchandise, website use, and participation with Space City Car Club.',
            ])
            ->addText('legal_document_last_updated', [
                'label' => 'Last Updated Date',
                'instructions' => 'Optional. Leave blank to use the WordPress modified date automatically.',
                'placeholder' => 'April 27, 2026',
            ])
            ->addText('legal_document_applies_to', [
                'label' => 'Applies To Text',
                'instructions' => 'Small pill text displayed next to the last updated date.',
                'default_value' => 'Applies to members, visitors, event participants, and website users',
                'placeholder' => 'Applies to members, visitors, event participants, and website users',
            ]);

        /*
        |--------------------------------------------------------------------------
        | Body Cleanup
        |--------------------------------------------------------------------------
        |
        | This lets the template remove the duplicate title heading from the page
        | body while keeping all actual H2 legal sections intact.
        |
        */

        $fields
            ->addTrueFalse('legal_document_strip_first_heading', [
                'label' => 'Remove Duplicate Body Heading',
                'instructions' => 'Recommended. Removes the first H1, or the first heading that matches the document/page title, from the body content.',
                'default_value' => 1,
                'ui' => 1,
                'ui_on_text' => 'Remove',
                'ui_off_text' => 'Keep',
            ]);

        /*
        |--------------------------------------------------------------------------
        | Closing Note
        |--------------------------------------------------------------------------
        |
        | The bottom note gives visitors a helpful next step without making the
        | legal page feel like a marketing landing page.
        |
        */

        $fields
            ->addText('legal_document_bottom_note_title', [
                'label' => 'Bottom Note Title',
                'default_value' => 'Questions about this document?',
                'placeholder' => 'Questions about this document?',
            ])
            ->addTextarea('legal_document_bottom_note_text', [
                'label' => 'Bottom Note Text',
                'rows' => 3,
                'new_lines' => '',
                'default_value' => 'Please contact Space City Car Club if you need clarification about these terms, membership rules, event policies, or website-related questions.',
                'placeholder' => 'Please contact Space City Car Club if you need clarification about these terms, membership rules, event policies, or website-related questions.',
            ]);

        return $fields->build();
    }
}