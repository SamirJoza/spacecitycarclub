<?php
/**
 * File path + filename: app/Options/SponsorSingleCta.php
 *
 * Purpose:
 * - Register the Sponsor Single CTA child page under Theme Settings.
 *
 * Why this file exists:
 * - Sponsor single pages need a reusable, globally managed CTA area beneath the
 *   main content without hardcoding copy or links into the Blade partial.
 * - Editors should be able to update the CTA text, alignment, and buttons from
 *   Theme Settings instead of editing templates.
 *
 * Important notes:
 * - These values are intended for frontend use with `get_field(..., 'option')`.
 * - Button labels and URLs are optional; if either pair is incomplete, that
 *   button simply will not render.
 */

declare(strict_types=1);

namespace App\Options;

use Log1x\AcfComposer\Builder;
use Log1x\AcfComposer\Options as Field;

class SponsorSingleCta extends Field
{
    /**
     * The child page menu label.
     *
     * @var string
     */
    public $name = 'Sponsor Single CTA';

    /**
     * The child page document title.
     *
     * @var string
     */
    public $title = 'Sponsor Single CTA | Theme Settings';

    /**
     * Stable child page slug.
     *
     * @var string
     */
    public $slug = 'theme-settings-sponsor-single-cta';

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
        $fields = Builder::make('sponsor_single_cta');

        $fields
            ->addTrueFalse('sponsor_single_cta_enabled', [
                'label' => 'Enable Sponsor Single CTA',
                'instructions' => 'Turn the CTA section beneath sponsor content on or off globally.',
                'ui' => 1,
                'default_value' => 1,
                'wrapper' => [
                    'width' => '25',
                ],
            ])
            ->addButtonGroup('sponsor_single_cta_alignment', [
                'label' => 'Text Alignment',
                'instructions' => 'Choose how the CTA copy and buttons should align.',
                'choices' => [
                    'left' => 'Left',
                    'center' => 'Center',
                ],
                'default_value' => 'left',
                'return_format' => 'value',
                'layout' => 'horizontal',
                'wrapper' => [
                    'width' => '25',
                ],
            ])
            ->addText('sponsor_single_cta_eyebrow', [
                'label' => 'Eyebrow',
                'instructions' => 'Optional short label above the heading.',
                'default_value' => 'Support the Club',
                'wrapper' => [
                    'width' => '50',
                ],
            ])
            ->addText('sponsor_single_cta_heading', [
                'label' => 'Heading',
                'instructions' => 'Main CTA heading shown beneath sponsor content.',
                'default_value' => 'Explore our sponsors or become one.',
                'wrapper' => [
                    'width' => '100',
                ],
            ])
            ->addTextarea('sponsor_single_cta_body', [
                'label' => 'Body Text',
                'instructions' => 'Optional supporting copy. Line breaks are preserved.',
                'rows' => 4,
                'new_lines' => 'br',
                'default_value' => 'Discover the businesses that help power Space City Car Club, or learn how your company can support the community.',
                'wrapper' => [
                    'width' => '100',
                ],
            ])
            ->addText('sponsor_single_cta_primary_label', [
                'label' => 'Primary Button Label',
                'default_value' => 'View All Sponsors',
                'wrapper' => [
                    'width' => '25',
                ],
            ])
            ->addText('sponsor_single_cta_primary_url', [
                'label' => 'Primary Button URL',
                'wrapper' => [
                    'width' => '50',
                ],
            ])
            ->addTrueFalse('sponsor_single_cta_primary_new_tab', [
                'label' => 'Primary Button Opens In New Tab',
                'ui' => 1,
                'default_value' => 0,
                'wrapper' => [
                    'width' => '25',
                ],
            ])
            ->addText('sponsor_single_cta_secondary_label', [
                'label' => 'Secondary Button Label',
                'default_value' => 'Become a Sponsor',
                'wrapper' => [
                    'width' => '25',
                ],
            ])
            ->addText('sponsor_single_cta_secondary_url', [
                'label' => 'Secondary Button URL',
                'wrapper' => [
                    'width' => '50',
                ],
            ])
            ->addTrueFalse('sponsor_single_cta_secondary_new_tab', [
                'label' => 'Secondary Button Opens In New Tab',
                'ui' => 1,
                'default_value' => 0,
                'wrapper' => [
                    'width' => '25',
                ],
            ]);

        return $fields->build();
    }
}