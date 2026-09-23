<?php
/**
 * File path + filename: app/Options/ProtectedFrontendPages.php
 *
 * Purpose:
 * - Register the Protected Frontend Pages child page under Theme Settings.
 *
 * Why this file exists:
 * - Path protection and redirect rules are operational settings and deserve
 *   their own focused admin page.
 *
 * Important notes:
 * - Field names remain unchanged from the previous combined Theme Settings file.
 * - Existing support code that reads these option values does not need to
 *   change.
 */

declare(strict_types=1);

namespace App\Options;

use Log1x\AcfComposer\Builder;
use Log1x\AcfComposer\Options as Field;

class ProtectedFrontendPages extends Field
{
    /**
     * The child page menu label.
     *
     * @var string
     */
    public $name = 'Protected Frontend Pages';

    /**
     * The child page document title.
     *
     * @var string
     */
    public $title = 'Protected Frontend Pages | Theme Settings';

    /**
     * Stable child page slug.
     *
     * @var string
     */
    public $slug = 'theme-settings-protected-frontend-pages';

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
        $fields = Builder::make('protected_frontend_pages');

        $fields
            ->addTrueFalse('protected_pages_enabled', [
                'label'         => 'Enable Protected Frontend Pages',
                'instructions'  => 'Master switch. When enabled, matching frontend paths will redirect unless the visitor has one of the allowed role slugs.',
                'default_value' => 1,
                'ui'            => 1,
                'ui_on_text'    => 'Enabled',
                'ui_off_text'   => 'Disabled',
            ])

            ->addText('protected_pages_default_redirect', [
                'label'         => 'Default Redirect',
                'instructions'  => 'Optional. Leave blank to redirect blocked visitors to the homepage. You may use a full URL or a relative path like /.',
                'default_value' => '/',
            ])

            ->addRepeater('protected_pages_rules', [
                'label'        => 'Protected Paths',
                'instructions' => 'Add one row per protected frontend page/path. Example path values: member-business-directory OR /member-business-directory/ OR /members/private-area/',
                'layout'       => 'row',
                'button_label' => 'Add Protected Path',
            ])
                ->addText('protected_path', [
                    'label'        => 'Protected Path / Slug',
                    'instructions' => 'Slug or path to protect. Examples: member-business-directory OR /member-business-directory/',
                    'required'     => 1,
                    'wrapper'      => [
                        'width' => '34',
                    ],
                ])
                ->addText('protected_allowed_roles', [
                    'label'         => 'Allowed Role Slugs',
                    'instructions'  => 'Comma-separated role slugs. Example: sccc_member',
                    'default_value' => 'sccc_member',
                    'wrapper'       => [
                        'width' => '33',
                    ],
                ])
                ->addText('protected_redirect', [
                    'label'        => 'Redirect Override',
                    'instructions' => 'Optional. Leave blank to use the default redirect above. You may use a full URL or a relative path like /.',
                    'wrapper'      => [
                        'width' => '33',
                    ],
                ])
            ->endRepeater();

        return $fields->build();
    }
}