<?php

/**
 * File path + filename: app/Fields/Vendor.php
 *
 * Purpose:
 * - ACF field group for the Vendor edit screen (one record per business).
 * - Tabs: Review · Business · Contact · Booth · Promotion · Shows.
 *
 * Why this file exists:
 * - New vendors arrive from the "Vendor Application" Gravity Form and are
 *   written into these fields by VendorApplicationForm. Returning vendors add
 *   rows to the Shows tab when they sign up and pay (VendorPayment).
 * - Staff approve / reject the vendor on the Review tab (VendorApproval).
 *
 * Important notes:
 * - Every field key is `field_` + the field name; names are the constants in
 *   App\Support\Vendors\Vendors. Show-history sub-fields use
 *   `field_vendor_row_{name}`. Keep these in sync.
 */

namespace App\Fields;

use App\Support\Vendors\Vendors;
use Log1x\AcfComposer\Field;
use StoutLogic\AcfBuilder\FieldsBuilder;

class Vendor extends Field
{
    public function fields(): array
    {
        $fields = new FieldsBuilder('vendor_profile', [
            'title' => 'Vendor',
            'position' => 'acf_after_title',
            'style' => 'default',
        ]);

        $fields->setLocation('post_type', '==', Vendors::POST_TYPE);

        $key = static fn (string $name): string => 'field_'.$name;

        /* ---------------------------------------------------------------------
         * Review
         * ------------------------------------------------------------------ */
        $fields
            ->addTab('Review')
            ->addSelect(Vendors::FIELD_STATUS, [
                'key' => $key(Vendors::FIELD_STATUS),
                'label' => 'Vendor status',
                'instructions' => 'Approving emails the vendor a private link to pay for the show they applied to. Approved vendors can sign up for future club shows themselves. Rejecting requires a reason, which is emailed to them.',
                'choices' => Vendors::statuses(),
                'default_value' => Vendors::STATUS_PENDING,
                'return_format' => 'value',
                'wrapper' => ['width' => '50'],
            ])
            ->addTrueFalse(Vendors::FIELD_SEND_EMAIL, [
                'key' => $key(Vendors::FIELD_SEND_EMAIL),
                'label' => 'Email the vendor',
                'message' => 'Send the approval / rejection email when the status changes',
                'default_value' => 1,
                'ui' => 1,
                'wrapper' => ['width' => '50'],
            ])
            ->addTextarea(Vendors::FIELD_REJECT_REASON, [
                'key' => $key(Vendors::FIELD_REJECT_REASON),
                'label' => 'Reason for rejection (sent to the vendor)',
                'rows' => 3,
                'new_lines' => '',
            ])
                ->conditional(Vendors::FIELD_STATUS, '==', Vendors::STATUS_REJECTED)
            ->addTextarea(Vendors::FIELD_ADMIN_NOTES, [
                'key' => $key(Vendors::FIELD_ADMIN_NOTES),
                'label' => 'Internal notes',
                'instructions' => 'Staff only. Never sent to the vendor.',
                'rows' => 3,
                'new_lines' => '',
            ]);

        /* ---------------------------------------------------------------------
         * Business
         * ------------------------------------------------------------------ */
        $fields
            ->addTab('Business')
            ->addText(Vendors::FIELD_DISPLAY_NAME, [
                'key' => $key(Vendors::FIELD_DISPLAY_NAME),
                'label' => 'Display name',
                'instructions' => 'Name used in promotions if different from the business name (title).',
                'wrapper' => ['width' => '50'],
            ])
            ->addTaxonomy(Vendors::FIELD_TYPE, [
                'key' => $key(Vendors::FIELD_TYPE),
                'label' => 'Type of business',
                'taxonomy' => Vendors::TAXONOMY_TYPE,
                'field_type' => 'select',
                'allow_null' => 0,
                'add_term' => 0,
                'save_terms' => 1,
                'load_terms' => 1,
                'return_format' => 'id',
                'required' => 1,
                'wrapper' => ['width' => '50'],
            ])
            ->addTextarea(Vendors::FIELD_BLURB, [
                'key' => $key(Vendors::FIELD_BLURB),
                'label' => 'Short description',
                'instructions' => 'Used in promotions and the show program.',
                'rows' => 3,
                'maxlength' => 300,
                'new_lines' => '',
            ])
            ->addTextarea(Vendors::FIELD_OFFERINGS, [
                'key' => $key(Vendors::FIELD_OFFERINGS),
                'label' => 'What they sell / offer',
                'rows' => 3,
                'new_lines' => '',
            ])
            ->addUrl(Vendors::FIELD_WEBSITE, [
                'key' => $key(Vendors::FIELD_WEBSITE),
                'label' => 'Website',
                'wrapper' => ['width' => '50'],
            ])
            ->addText(Vendors::FIELD_INSTAGRAM, [
                'key' => $key(Vendors::FIELD_INSTAGRAM),
                'label' => 'Instagram',
                'wrapper' => ['width' => '50'],
            ])
            ->addText(Vendors::FIELD_FACEBOOK, [
                'key' => $key(Vendors::FIELD_FACEBOOK),
                'label' => 'Facebook',
                'wrapper' => ['width' => '50'],
            ])
            ->addText(Vendors::FIELD_TIKTOK, [
                'key' => $key(Vendors::FIELD_TIKTOK),
                'label' => 'TikTok',
                'wrapper' => ['width' => '50'],
            ]);

        /* ---------------------------------------------------------------------
         * Contact
         * ------------------------------------------------------------------ */
        $fields
            ->addTab('Contact')
            ->addText(Vendors::FIELD_FIRST_NAME, [
                'key' => $key(Vendors::FIELD_FIRST_NAME),
                'label' => 'First name',
                'required' => 1,
                'wrapper' => ['width' => '33'],
            ])
            ->addText(Vendors::FIELD_LAST_NAME, [
                'key' => $key(Vendors::FIELD_LAST_NAME),
                'label' => 'Last name',
                'required' => 1,
                'wrapper' => ['width' => '33'],
            ])
            ->addText(Vendors::FIELD_ROLE, [
                'key' => $key(Vendors::FIELD_ROLE),
                'label' => 'Role',
                'wrapper' => ['width' => '34'],
            ])
            ->addEmail(Vendors::FIELD_EMAIL, [
                'key' => $key(Vendors::FIELD_EMAIL),
                'label' => 'Email',
                'instructions' => 'Unique per vendor. Returning vendors are recognised by this address.',
                'required' => 1,
                'wrapper' => ['width' => '33'],
            ])
            ->addText(Vendors::FIELD_PHONE, [
                'key' => $key(Vendors::FIELD_PHONE),
                'label' => 'Mobile phone',
                'wrapper' => ['width' => '33'],
            ])
            ->addTrueFalse(Vendors::FIELD_TEXT_OK, [
                'key' => $key(Vendors::FIELD_TEXT_OK),
                'label' => 'OK to text',
                'ui' => 1,
                'wrapper' => ['width' => '34'],
            ])
            ->addText(Vendors::FIELD_ADDRESS, [
                'key' => $key(Vendors::FIELD_ADDRESS),
                'label' => 'Street address',
            ])
            ->addText(Vendors::FIELD_CITY, [
                'key' => $key(Vendors::FIELD_CITY),
                'label' => 'City',
                'wrapper' => ['width' => '40'],
            ])
            ->addText(Vendors::FIELD_STATE, [
                'key' => $key(Vendors::FIELD_STATE),
                'label' => 'State',
                'wrapper' => ['width' => '30'],
            ])
            ->addText(Vendors::FIELD_ZIP, [
                'key' => $key(Vendors::FIELD_ZIP),
                'label' => 'ZIP',
                'wrapper' => ['width' => '30'],
            ])
            ->addText(Vendors::FIELD_DAYOF_NAME, [
                'key' => $key(Vendors::FIELD_DAYOF_NAME),
                'label' => 'Show-day contact',
                'wrapper' => ['width' => '50'],
            ])
            ->addText(Vendors::FIELD_DAYOF_PHONE, [
                'key' => $key(Vendors::FIELD_DAYOF_PHONE),
                'label' => 'Show-day phone',
                'wrapper' => ['width' => '50'],
            ]);

        /* ---------------------------------------------------------------------
         * Booth
         * ------------------------------------------------------------------ */
        $fields
            ->addTab('Booth')
            ->addSelect(Vendors::FIELD_BOOTH_TYPE, [
                'key' => $key(Vendors::FIELD_BOOTH_TYPE),
                'label' => 'Booth type',
                'instructions' => 'Used for the fee when a show uses booth-type pricing.',
                'choices' => Vendors::boothTypes(),
                'allow_null' => 1,
                'return_format' => 'value',
                'wrapper' => ['width' => '50'],
            ])
            ->addText(Vendors::FIELD_TRAILER_LENGTH, [
                'key' => $key(Vendors::FIELD_TRAILER_LENGTH),
                'label' => 'Truck / trailer length',
                'wrapper' => ['width' => '50'],
            ])
            ->addCheckbox(Vendors::FIELD_EQUIPMENT, [
                'key' => $key(Vendors::FIELD_EQUIPMENT),
                'label' => 'Brings own',
                'choices' => ['tent' => 'Tent / canopy', 'tables' => 'Tables', 'chairs' => 'Chairs'],
                'layout' => 'horizontal',
                'return_format' => 'value',
            ])
            ->addSelect(Vendors::FIELD_POWER, [
                'key' => $key(Vendors::FIELD_POWER),
                'label' => 'Needs electricity',
                'choices' => ['no' => 'No', 'yes' => 'Yes'],
                'default_value' => 'no',
                'return_format' => 'value',
                'wrapper' => ['width' => '33'],
            ])
            ->addSelect(Vendors::FIELD_GENERATOR, [
                'key' => $key(Vendors::FIELD_GENERATOR),
                'label' => 'Brings a generator',
                'choices' => ['no' => 'No', 'yes' => 'Yes'],
                'default_value' => 'no',
                'return_format' => 'value',
                'wrapper' => ['width' => '33'],
            ])
            ->addSelect(Vendors::FIELD_OPEN_FLAME, [
                'key' => $key(Vendors::FIELD_OPEN_FLAME),
                'label' => 'Open flame / propane',
                'choices' => ['no' => 'No', 'yes' => 'Yes'],
                'default_value' => 'no',
                'return_format' => 'value',
                'wrapper' => ['width' => '34'],
            ])
            ->addNumber(Vendors::FIELD_STAFF_COUNT, [
                'key' => $key(Vendors::FIELD_STAFF_COUNT),
                'label' => 'Staff on site',
                'min' => 0,
                'wrapper' => ['width' => '33'],
            ])
            ->addTextarea(Vendors::FIELD_NOTES, [
                'key' => $key(Vendors::FIELD_NOTES),
                'label' => 'Notes from vendor',
                'rows' => 3,
                'new_lines' => '',
            ]);

        /* ---------------------------------------------------------------------
         * Promotion
         * ------------------------------------------------------------------ */
        $fields
            ->addTab('Promotion')
            ->addTrueFalse(Vendors::FIELD_FEATURE_OK, [
                'key' => $key(Vendors::FIELD_FEATURE_OK),
                'label' => 'OK to feature',
                'message' => 'Vendor allows us to use their name and logo in show promotions',
                'ui' => 1,
            ])
            ->addImage(Vendors::FIELD_LOGO, [
                'key' => $key(Vendors::FIELD_LOGO),
                'label' => 'Logo',
                'instructions' => 'Also used as the featured image. Shown in the Featured Vendors block.',
                'return_format' => 'id',
                'preview_size' => 'medium',
                'library' => 'all',
                'wrapper' => ['width' => '40'],
            ])
            ->addGallery(Vendors::FIELD_PHOTOS, [
                'key' => $key(Vendors::FIELD_PHOTOS),
                'label' => 'Product photos',
                'return_format' => 'id',
                'max' => 6,
                'wrapper' => ['width' => '60'],
            ])
            ->addText(Vendors::FIELD_OFFER, [
                'key' => $key(Vendors::FIELD_OFFER),
                'label' => 'Offer for members / attendees',
                'placeholder' => 'e.g. 10% off for SCCC members',
            ]);

        /* ---------------------------------------------------------------------
         * Shows (history, one row per show)
         * ------------------------------------------------------------------ */
        $fields
            ->addTab('Shows')
            ->addRepeater(Vendors::FIELD_HISTORY, [
                'key' => $key(Vendors::FIELD_HISTORY),
                'label' => 'Show history',
                'instructions' => 'One row per show. Rows are added automatically when the vendor applies or signs up; payments fill in the paid fields. Set a row to "Active" manually for cash / check payments.',
                'layout' => 'table',
                'button_label' => 'Add show',
            ])
                ->addPostObject(Vendors::ROW_EVENT, [
                    'key' => 'field_vendor_row_'.Vendors::ROW_EVENT,
                    'label' => 'Show',
                    'post_type' => [Vendors::EVENT_POST_TYPE],
                    'return_format' => 'id',
                    'ui' => 1,
                    'required' => 1,
                ])
                ->addSelect(Vendors::ROW_STATUS, [
                    'key' => 'field_vendor_row_'.Vendors::ROW_STATUS,
                    'label' => 'Status',
                    'choices' => Vendors::showStatuses(),
                    'default_value' => Vendors::SHOW_PENDING,
                    'return_format' => 'value',
                ])
                ->addNumber(Vendors::ROW_FEE, [
                    'key' => 'field_vendor_row_'.Vendors::ROW_FEE,
                    'label' => 'Fee',
                    'instructions' => 'Locked in at approval. Override here if needed.',
                    'prepend' => '$',
                    'min' => 0,
                    'step' => 0.01,
                ])
                ->addNumber(Vendors::ROW_PAID_AMOUNT, [
                    'key' => 'field_vendor_row_'.Vendors::ROW_PAID_AMOUNT,
                    'label' => 'Paid',
                    'prepend' => '$',
                    'min' => 0,
                    'step' => 0.01,
                ])
                ->addText(Vendors::ROW_PAID_AT, [
                    'key' => 'field_vendor_row_'.Vendors::ROW_PAID_AT,
                    'label' => 'Paid on',
                ])
                ->addText(Vendors::ROW_TRANSACTION, [
                    'key' => 'field_vendor_row_'.Vendors::ROW_TRANSACTION,
                    'label' => 'Transaction',
                ])
                ->addNumber(Vendors::ROW_ENTRY, [
                    'key' => 'field_vendor_row_'.Vendors::ROW_ENTRY,
                    'label' => 'Payment entry',
                ])
                ->addText(Vendors::ROW_NOTES, [
                    'key' => 'field_vendor_row_'.Vendors::ROW_NOTES,
                    'label' => 'Notes',
                ])
            ->endRepeater();

        return $fields->build();
    }
}
