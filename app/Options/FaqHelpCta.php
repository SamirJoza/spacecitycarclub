<?php
/**
 * File path + filename: app/Options/FaqHelpCta.php
 *
 * Purpose:
 * - Register the FAQ Help CTA child page under Theme Settings.
 *
 * Why this file exists:
 * - The closing "Still have questions?" section on the FAQ page is global site
 *   content and should be editable in one dedicated admin location.
 *
 * Important notes:
 * - Field names remain unchanged from the previous combined Theme Settings file.
 * - Existing FAQ partials using these option field names do not need to change.
 */

declare(strict_types=1);

namespace App\Options;

use Log1x\AcfComposer\Builder;
use Log1x\AcfComposer\Options as Field;

class FaqHelpCta extends Field
{
    /**
     * The child page menu label.
     *
     * @var string
     */
    public $name = 'FAQ Help CTA';

    /**
     * The child page document title.
     *
     * @var string
     */
    public $title = 'FAQ Help CTA | Theme Settings';

    /**
     * Stable child page slug.
     *
     * @var string
     */
    public $slug = 'theme-settings-faq-help-cta';

    /**
     * Attach this page under the Theme Settings parent page.
     *
     * @var string
     */
    public $parent = 'theme-settings';

    /**
     * The option page field group.
     *
     * @return array
     */
    public function fields(): array
    {
        $fields = Builder::make('faq_help_cta');

        $fields
            ->addText('faq_help_heading', [
                'label'         => 'Help Section Heading',
                'instructions'  => 'Main headline shown above the FAQ help CTA buttons.',
                'default_value' => 'Still have questions?',
            ])

            ->addTextarea('faq_help_copy', [
                'label'         => 'Help Section Copy',
                'instructions'  => 'Short supporting copy shown below the heading.',
                'rows'          => 3,
                'new_lines'     => 'br',
                'default_value' => 'Can’t find the answer you’re looking for? Reach out to our team directly and we’ll point you in the right direction.',
            ])

            ->addLink('faq_help_primary_button', [
                'label'         => 'Primary Button',
                'instructions'  => 'Main CTA button for the FAQ help section.',
                'return_format' => 'array',
            ])

            ->addLink('faq_help_secondary_button', [
                'label'         => 'Secondary Button',
                'instructions'  => 'Optional secondary CTA button.',
                'return_format' => 'array',
            ]);

        return $fields->build();
    }
}