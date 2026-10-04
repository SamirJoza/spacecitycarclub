<?php
/**
 * File path + filename: app/Options/VendorSettings.php
 *
 * Purpose:
 * - Register the Vendor Settings child page under Theme Settings.
 *
 * Why this file exists:
 * - The vendor workflow needs a few site-level settings that should not be
 *   hard-coded: the vendor page and the sign-up/payment page, who receives
 *   staff notifications, the EmailOctopus list, and editable messages
 *   (approval email extra, food-vendor message, closed message).
 * - The status box at the top of this page (VendorForms::renderStatus) shows
 *   whether the vendor forms and feeds exist and offers "Sync vendor forms".
 *
 * Important notes:
 * - Field names are read by App\Support\Vendors\Vendors (pageUrl(),
 *   staffEmail(), option()) and VendorApproval. Keep them unchanged.
 */

declare(strict_types=1);

namespace App\Options;

use Log1x\AcfComposer\Builder;
use Log1x\AcfComposer\Options as Field;

class VendorSettings extends Field
{
    /**
     * The child page menu label.
     *
     * @var string
     */
    public $name = 'Vendor Settings';

    /**
     * The child page document title.
     *
     * @var string
     */
    public $title = 'Vendor Settings | Theme Settings';

    /**
     * Stable child page slug (used by the Theme Settings side navigation).
     *
     * @var string
     */
    public $slug = 'theme-settings-vendor-settings';

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
        $fields = Builder::make('vendor_settings');

        $fields
            ->addPostObject('vendor_application_page', [
                'label' => 'Vendor page',
                'instructions' => 'The public page with the "Vendor Sign-up" block (new + returning vendors). Used for links in emails and messages.',
                'post_type' => ['page'],
                'return_format' => 'id',
                'allow_null' => 1,
                'ui' => 1,
                'wrapper' => ['width' => '50'],
            ])
            ->addPostObject('vendor_payment_page', [
                'label' => 'Vendor sign-up & payment page',
                'instructions' => 'The page containing the "Vendor Sign-up & Payment" form. Personal links in vendor emails point here. Hide it from menus.',
                'post_type' => ['page'],
                'return_format' => 'id',
                'allow_null' => 1,
                'ui' => 1,
                'wrapper' => ['width' => '50'],
            ])
            ->addEmail('vendor_staff_email', [
                'label' => 'Staff notification email',
                'instructions' => 'Receives "vendor paid" notices and is the Reply-To on vendor emails. Defaults to the site admin email.',
                'wrapper' => ['width' => '50'],
            ])
            ->addSelect('vendor_emailoctopus_list', [
                'label' => 'EmailOctopus list for vendors',
                'instructions' => 'New vendors who tick "Stay in the loop" are added to this list. Saving creates/updates the EmailOctopus feed automatically. Invite past vendors to a new show from EmailOctopus with a link to the Vendor page.',
                'choices' => [], // Filled from the EmailOctopus add-on by VendorForms::loadEmailOctopusLists().
                'allow_null' => 1,
                'ui' => 1,
                'return_format' => 'value',
                'wrapper' => ['width' => '50'],
            ])
            ->addTextarea('vendor_approval_email_note', [
                'label' => 'Extra text for the approval email',
                'instructions' => 'Optional. Added to every approval email, e.g. load-in times or booth size.',
                'rows' => 3,
                'new_lines' => 'br',
            ])
            ->addTextarea('vendor_food_message', [
                'label' => 'Message for food vendors at a no-food show',
                'instructions' => 'Shown when a food vendor tries to sign up for a show that doesn\'t allow food. {show} is replaced with the show name.',
                'rows' => 3,
                'new_lines' => '',
                'default_value' => 'Sorry — the {show} venue doesn\'t allow food vendors, so we can\'t offer you a spot this time. We\'d love to have you back at a future show and will let you know when applications open.',
            ])
            ->addText('vendor_applications_closed_message', [
                'label' => 'Message when no shows are open',
                'default_value' => 'Vendor applications are currently closed. Check back soon for our next show.',
            ]);

        return $fields->build();
    }
}
