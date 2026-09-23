<?php
/**
 * File path + filename: app/Options/NewsletterSection.php
 *
 * Purpose:
 * - Register the Newsletter Section child page under Theme Settings.
 *
 * Why this file exists:
 * - Newsletter visibility, copy, and action settings are global content
 *   controls and are easier to manage on their own page.
 *
 * Important notes:
 * - Field names remain unchanged from the previous combined Theme Settings file.
 * - Existing code that reads newsletter settings from `option` does not need
 *   to change.
 *
 * Reminder for the matching Blade partial:
 * - `single_post` is intended to work with:
 *   (in_array('single_post', $showOn) && is_singular('post'))
 */

declare(strict_types=1);

namespace App\Options;

use Log1x\AcfComposer\Builder;
use Log1x\AcfComposer\Options as Field;

class NewsletterSection extends Field
{
    /**
     * The child page menu label.
     *
     * @var string
     */
    public $name = 'Newsletter Section';

    /**
     * The child page document title.
     *
     * @var string
     */
    public $title = 'Newsletter Section | Theme Settings';

    /**
     * Stable child page slug.
     *
     * @var string
     */
    public $slug = 'theme-settings-newsletter-section';

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
        $fields = Builder::make('newsletter_section');

        $fields
            ->addTrueFalse('newsletter_enabled', [
                'label'         => 'Enable Newsletter Section',
                'instructions'  => 'Master switch. Turn off to hide the newsletter banner everywhere instantly.',
                'default_value' => 1,
                'ui'            => 1,
                'ui_on_text'    => 'Enabled',
                'ui_off_text'   => 'Disabled',
            ])

            ->addCheckbox('newsletter_show_on', [
                'label'        => 'Show On',
                'instructions' => 'Select which page types should display the newsletter section. Has no effect when the master switch above is disabled.',
                'choices'      => [
                    'single_post'          => '📝  Single Blog Posts',
                    'blog_index'           => '📰  Blog Index (Latest Posts page)',
                    'blog_category'        => '🏷️  Blog Category Archives',
                    'blog_tag'             => '🔖  Blog Tag Archives',
                    'blog_author'          => '👤  Blog Author Archives',
                    'blog_date'            => '📅  Blog Date Archives',
                    'woo_shop'             => '🛒  WooCommerce Shop Page',
                    'woo_product_category' => '📦  WooCommerce Product Category Archives',
                    'events_archive'       => '📆  Events Archive',
                ],
                'default_value' => [
                    'single_post',
                    'blog_index',
                    'blog_category',
                    'blog_tag',
                    'blog_author',
                    'blog_date',
                ],
                'layout' => 'vertical',
            ])

            ->addText('newsletter_heading', [
                'label'         => 'Heading',
                'default_value' => 'Stay in the fast lane',
            ])

            ->addTextarea('newsletter_body', [
                'label'         => 'Body Copy',
                'instructions'  => 'Short paragraph shown below the heading.',
                'rows'          => 3,
                'default_value' => 'Subscribe to our newsletter to get the latest event announcements, member exclusive deals, and automotive news delivered straight to your inbox.',
            ])

            ->addText('newsletter_button_label', [
                'label'         => 'Button Label',
                'default_value' => 'Subscribe',
            ])

            ->addUrl('newsletter_form_action', [
                'label'        => 'Form Action URL',
                'placeholder'  => 'https://your-mailchimp-or-crm-endpoint.com/subscribe',
                'instructions' => 'The URL your email form POSTs to (e.g. MailChimp action URL). Leave blank to hide the email input and button.',
            ])

            ->addText('newsletter_disclaimer', [
                'label'         => 'Disclaimer Text',
                'default_value' => 'No spam, just cars. Unsubscribe at any time.',
            ]);

        return $fields->build();
    }
}