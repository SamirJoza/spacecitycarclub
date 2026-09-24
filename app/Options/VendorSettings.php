<?php
/**
 * File path + filename: app/Options/VendorSettings.php
 *
 * Purpose:
 * - Register the Vendor Settings child page under Theme Settings.
 *
 * Why this file exists:
 * - The vendor workflow needs a few site-level settings that should not be
 *   hard-coded: the page that holds the Vendor Payment form, who receives staff
 *   notifications, and optional extra copy for the approval email.
 *
 * Important notes:
 * - Field names are read by App\Support\Vendors\Vendors (paymentPageUrl(),
 *   staffEmail()) and VendorApproval. Keep them unchanged.
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
            ->addPostObject('vendor_payment_page', [
                'label' => 'Vendor payment page',
                'instructions' => 'The page that contains the "Vendor Payment" Gravity Form. Approval emails link here with a private token.',
                'post_type' => ['page'],
                'return_format' => 'id',
                'allow_null' => 1,
                'ui' => 1,
                'wrapper' => ['width' => '50'],
            ])
            ->addEmail('vendor_staff_email', [
                'label' => 'Staff notification email',
                'instructions' => 'Receives "vendor paid" notices and is used as Reply-To on vendor emails. Defaults to the site admin email.',
                'wrapper' => ['width' => '50'],
            ])
            ->addTextarea('vendor_approval_email_note', [
                'label' => 'Extra text for the approval email',
                'instructions' => 'Optional. Added to every approval email, e.g. load-in times, booth size, insurance or health-permit reminders.',
                'rows' => 4,
                'new_lines' => 'br',
            ])
            ->addText('vendor_applications_closed_message', [
                'label' => 'Message when no shows are open',
                'instructions' => 'Shown instead of the application form when no upcoming show accepts vendors.',
                'default_value' => 'Vendor applications are currently closed. Check back soon for upcoming shows.',
            ]);

        return $fields->build();
    }
}
