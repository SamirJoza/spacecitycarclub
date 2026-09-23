<?php

/**
 * Space City Car Club
 * File: app/Fields/TestimonialFields.php
 *
 * Registers the ACF field group for the Testimonial custom post type.
 *
 * Why this file exists:
 * - The CPT admin columns depend on these fields being stored in post meta.
 * - The future Gutenberg Testimonial block will also use these same fields
 *   when pulling a testimonial from the CPT.
 *
 * Editorial model:
 * - Post title               = Name of person
 * - Featured image           = Profile image
 * - testimonial_text         = Main quote / testimonial content
 * - testimonial_meta_line    = Supporting line, such as:
 *                              "Platinum Member since 2021"
 */

namespace App\Fields;

use App\Support\CPT\TestimonialPostType;
use Log1x\AcfComposer\Builder;
use Log1x\AcfComposer\Field;

class TestimonialFields extends Field
{
    /**
     * Register the testimonial field group.
     *
     * Why this exists:
     * - Keeps testimonial content structured and reusable.
     * - Avoids using the generic WordPress editor for this content type.
     *
     * @return array<string, mixed>
     */
    public function fields(): array
    {
        $fields = Builder::make('testimonial_fields');

        /**
         * Attach this field group only to the Testimonial CPT.
         */
        $fields->setLocation('post_type', '==', TestimonialPostType::POST_TYPE);

        /**
         * Main testimonial text.
         *
         * Why textarea:
         * - This is quote-style content, not rich editorial body copy.
         * - A textarea keeps the entry experience simple and predictable.
         */
        $fields->addTextarea('testimonial_text', [
            'label' => 'Testimonial Text',
            'instructions' => 'Enter the main testimonial text shown in the block and admin list preview.',
            'required' => 1,
            'rows' => 6,
            'new_lines' => 'br',
            'maxlength' => 1500,
        ]);

        /**
         * Supporting line under the person’s name.
         *
         * Example:
         * - Platinum Member since 2021
         */
        $fields->addText('testimonial_meta_line', [
            'label' => 'Member Line',
            'instructions' => 'Optional supporting line shown below the name. Example: Platinum Member since 2021',
            'required' => 0,
            'maxlength' => 120,
            'placeholder' => 'Platinum Member since 2021',
        ]);

        return $fields->build();
    }
}