<?php
/**
 * File path + filename: app/Options/BusinessDirectoryCta.php
 *
 * Purpose:
 * - Register the Business Directory CTA child page under Theme Settings.
 *
 * Why this file exists:
 * - The member business directory CTA is a self-contained global content block
 *   and is easier to manage on its own page.
 *
 * Important notes:
 * - Field names remain unchanged from the previous combined Theme Settings file.
 * - Existing partials that read these settings from `option` do not need to
 *   change.
 */

declare(strict_types=1);

namespace App\Options;

use Log1x\AcfComposer\Builder;
use Log1x\AcfComposer\Options as Field;

class BusinessDirectoryCta extends Field
{
    /**
     * The child page menu label.
     *
     * @var string
     */
    public $name = 'Business Directory CTA';

    /**
     * The child page document title.
     *
     * @var string
     */
    public $title = 'Business Directory CTA | Theme Settings';

    /**
     * Stable child page slug.
     *
     * @var string
     */
    public $slug = 'theme-settings-business-directory-cta';

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
        $fields = Builder::make('business_directory_cta');

        $fields
            ->addTrueFalse('business_directory_cta_enabled', [
                'label'         => 'Enable Business Directory CTA',
                'instructions'  => 'Master switch for the “Own a Business? Get Listed!” section shown below the member business directory.',
                'default_value' => 1,
                'ui'            => 1,
                'ui_on_text'    => 'Enabled',
                'ui_off_text'   => 'Disabled',
            ])

            ->addText('business_directory_cta_icon', [
                'label'         => 'Icon Name',
                'instructions'  => 'Material Symbols icon name. Example: storefront, edit, support_agent.',
                'default_value' => 'storefront',
            ])

            ->addText('business_directory_cta_heading', [
                'label'         => 'Heading',
                'default_value' => 'Own a Business? Get Listed!',
            ])

            ->addTextarea('business_directory_cta_body', [
                'label'         => 'Body Copy',
                'instructions'  => 'Short supporting paragraph shown below the heading.',
                'rows'          => 3,
                'new_lines'     => 'br',
                'default_value' => 'As a Space City Car Club member, you get free exposure to hundreds of local enthusiasts. Update your member profile today to add your business details.',
            ])

            ->addText('business_directory_cta_primary_label', [
                'label'         => 'Primary Button Label',
                'default_value' => 'Edit Profile',
                'wrapper'       => [
                    'width' => '50',
                ],
            ])

            ->addText('business_directory_cta_primary_url', [
                'label'        => 'Primary Button URL',
                'instructions' => 'Recommended: your member profile or account edit screen.',
                'wrapper'      => [
                    'width' => '50',
                ],
            ])

            ->addText('business_directory_cta_secondary_label', [
                'label'         => 'Secondary Button Label',
                'default_value' => 'Contact Support',
                'wrapper'       => [
                    'width' => '50',
                ],
            ])

            ->addText('business_directory_cta_secondary_url', [
                'label'        => 'Secondary Button URL',
                'instructions' => 'Recommended: support, contact, or help page.',
                'wrapper'      => [
                    'width' => '50',
                ],
            ]);

        return $fields->build();
    }
}