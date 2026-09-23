<?php

/**
 * --------------------------------------------------------------------------
 * File path + filename: app/Fields/Leadership.php
 * --------------------------------------------------------------------------
 * Purpose:
 * - Define the ACF field group for Leadership entries.
 *
 * Why this file exists:
 * - The Leadership edit screen should stay simple and focused.
 * - This field group keeps only the pieces currently needed:
 *   linked user, leadership category, custom position text, display order,
 *   profile image override, and bio override.
 *
 * Important implementation notes:
 * - The taxonomy field still writes to the taxonomy slug `leadership_group`
 *   for backward compatibility, but the UI label is now “Leadership Category”.
 * - The custom positions textarea intentionally uses the field name:
 *   `leadership_position_titles`
 * - The old `leadership_positions` field name is avoided because it previously
 *   belonged to a taxonomy/multi-value field and can contain array-shaped data.
 *
 * Display order:
 * - `leadership_display_order` is used by the Leadership Band block for
 *   Leadership Team and Officer ordering.
 * - Lower numbers appear first.
 * - The recommended pattern is 10, 20, 30, 40, etc. so future positions can be
 *   inserted between existing ones without changing every record.
 */

namespace App\Fields;

use Log1x\AcfComposer\Field;
use StoutLogic\AcfBuilder\FieldsBuilder;

defined('ABSPATH') || exit;

class Leadership extends Field
{
    /**
     * Build the Leadership field group.
     */
    public function fields(): array
    {
        $fields = new FieldsBuilder('leadership_details', [
            'title'    => __('Leadership Details', 'sccc'),
            'position' => 'acf_after_title',
            'style'    => 'default',
        ]);

        /**
         * Keep this field group scoped only to the Leadership CPT.
         */
        $fields->setLocation('post_type', '==', 'leadership');

        /**
         * Linked member/user.
         *
         * Why this stays required:
         * - The Leadership entry is intended to point to a real WordPress user.
         * - This keeps avatar/bio fallbacks straightforward when no override is
         *   provided.
         */
        $fields->addUser('leadership_user', [
            'label'         => __('Linked Member (User)', 'sccc'),
            'instructions'  => __('Select the member this leadership record belongs to.', 'sccc'),
            'required'      => 1,
            'return_format' => 'id',
            'multiple'      => 0,
            'allow_null'    => 0,
            'role'          => ['sccc_member'],
        ]);

        /**
         * Leadership category.
         *
         * Why this uses radio buttons:
         * - The simplified model treats this as a single classification.
         * - Terms are curated and managed through the taxonomy admin screen, not
         *   created ad hoc from the editor.
         * - Editors can still assign an existing term from this screen.
         */
        $fields->addTaxonomy('leadership_group', [
            'label'         => __('Leadership Category', 'sccc'),
            'instructions'  => __('Choose one classification such as Leadership Team, Board Member, Officer, or Committee.', 'sccc'),
            'taxonomy'      => \App\LEADERSHIP_GROUP_TAX ?? 'leadership_group',
            'field_type'    => 'radio',
            'add_term'      => 0,
            'save_terms'    => 1,
            'load_terms'    => 1,
            'return_format' => 'id',
            'allow_null'    => 0,
        ]);

        /**
         * Display order.
         *
         * Why this exists:
         * - Position text is content and should be freely renameable.
         * - Sort order is structure and should not depend on exact text matches.
         * - The Leadership Band block uses this field for the Leadership Team
         *   and Officer categories only.
         *
         * Recommended values:
         * - 10 President
         * - 20 Vice President
         * - 30 Treasurer
         * - 40 Secretary
         * - 50 Membership Director
         */
        $fields->addNumber('leadership_display_order', [
            'label'         => __('Display Order', 'sccc'),
            'instructions'  => __('Optional. Lower numbers appear first for Leadership Team and Officer categories. Use 10, 20, 30, etc. so future positions can be inserted later.', 'sccc'),
            'required'      => 0,
            'min'           => 0,
            'step'          => 1,
            'placeholder'   => '10',
            'wrapper'       => [
                'width' => '33',
            ],
        ]);

        /**
         * Custom position titles.
         *
         * Important:
         * - This uses `leadership_position_titles`.
         * - Do not rename this back to `leadership_positions`; that older field
         *   name may still contain legacy taxonomy-array data.
         *
         * Editing pattern:
         * - One position per line.
         * - The frontend block renders these as comma-separated titles.
         */
        $fields->addTextarea('leadership_position_titles', [
            'label'         => __('Position(s)', 'sccc'),
            'instructions'  => __('Enter one position per line. Example: President, Treasurer, Event Coordinator.', 'sccc'),
            'required'      => 0,
            'rows'          => 4,
            'new_lines'     => 'br',
        ]);

        /**
         * Profile image override.
         *
         * Why this stays optional:
         * - If empty, the site can continue falling back to the linked user’s
         *   avatar / Gravatar behavior.
         */
        $fields->addImage('leadership_photo_override', [
            'label'         => __('Profile Image Override', 'sccc'),
            'instructions'  => __('Optional. Use this instead of the linked user avatar / Gravatar.', 'sccc'),
            'required'      => 0,
            'return_format' => 'array',
            'preview_size'  => 'thumbnail',
            'library'       => 'all',
        ]);

        /**
         * Bio override.
         *
         * Why this stays optional:
         * - If empty, templates can continue falling back to the linked user bio.
         */
        $fields->addWysiwyg('leadership_bio_override', [
            'label'         => __('Bio Override', 'sccc'),
            'instructions'  => __('Optional. Use this to override the linked user bio.', 'sccc'),
            'required'      => 0,
            'tabs'          => 'visual',
            'toolbar'       => 'basic',
            'media_upload'  => 0,
            'delay'         => 0,
        ]);

        return $fields->build();
    }
}