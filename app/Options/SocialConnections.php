<?php
/**
 * File path + filename: app/Options/SocialConnections.php
 *
 * Purpose:
 * - Register the Social Connections child page under Theme Settings.
 *
 * Why this file exists:
 * - Social profile URLs are global site settings and deserve their own focused
 *   admin page instead of sharing space with unrelated settings.
 *
 * Important notes:
 * - Field names remain unchanged from the previous combined Theme Settings file.
 * - Frontend code using the existing `socials` option field does not need to
 *   change.
 */

declare(strict_types=1);

namespace App\Options;

use Log1x\AcfComposer\Builder;
use Log1x\AcfComposer\Options as Field;

class SocialConnections extends Field
{
    /**
     * The child page menu label.
     *
     * @var string
     */
    public $name = 'Social Connections';

    /**
     * The child page document title.
     *
     * @var string
     */
    public $title = 'Social Connections | Theme Settings';

    /**
     * Stable child page slug.
     *
     * @var string
     */
    public $slug = 'theme-settings-social-connections';

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
        $fields = Builder::make('social_connections');

        $fields
            ->addRepeater('socials', [
                'label'        => 'Social Profiles',
                'instructions' => 'Add the club’s social profiles here. These values are global and can be used anywhere on the site.',
                'layout'       => 'row',
                'button_label' => 'Add Social Profile',
            ])
                ->addSelect('social', [
                    'label'    => 'Social Network',
                    'required' => 0,
                    'wrapper'  => [
                        'width' => '50',
                    ],
                    'choices' => [
                        'fb'     => 'Facebook',
                        'ig'     => 'Instagram',
                        'yt'     => 'YouTube',
                        'tiktok' => 'TikTok',
                        'x'      => 'Twitter / X',
                    ],
                    'allow_null'    => 0,
                    'ui'            => 1,
                    'return_format' => 'value',
                    'placeholder'   => '-- Select --',
                ])
                ->addUrl('socialURL', [
                    'label'   => 'URL',
                    'wrapper' => [
                        'width' => '50',
                    ],
                ])
            ->endRepeater();

        return $fields->build();
    }
}