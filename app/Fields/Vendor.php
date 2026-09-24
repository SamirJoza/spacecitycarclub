<?php

/**
 * File path + filename: app/Fields/Vendor.php
 *
 * Purpose:
 * - ACF field group for the Vendor application edit screen.
 * - Tabs: Business, Contact, Show, Review.
 *
 * Why this file exists:
 * - Applications arrive from the Gravity Forms "Vendor Application" form and
 *   are written into these fields by VendorApplicationForm. Staff can correct
 *   details here and run the manual approval from the Review tab.
 *
 * Important notes:
 * - Field names are the constants in App\Support\Vendors\Vendors. Changing a
 *   name here requires the same change there.
 * - Changing "Application status" and clicking Update triggers the workflow in
 *   VendorApproval (approval email with private payment link, decline email).
 * - Payment details are written by code and shown read-only in the
 *   "Application record" side box, not here.
 */

namespace App\Fields;

use App\Support\Vendors\Vendors;
use Log1x\AcfComposer\Field;
use StoutLogic\AcfBuilder\FieldsBuilder;

class Vendor extends Field
{
    public function fields(): array
    {
        $fields = new FieldsBuilder('vendor_application', [
            'title' => 'Vendor Application',
            'position' => 'acf_after_title',
            'style' => 'default',
        ]);

        $fields->setLocation('post_type', '==', Vendors::POST_TYPE);

        /* ---------------------------------------------------------------------
         * Tab: Business
         * ------------------------------------------------------------------ */
        $fields
            ->addTab('Business')

            ->addTaxonomy('vendor_type_term', [
                'key' => 'field_vendor_type_term',
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
            ->addUrl(Vendors::FIELD_WEBSITE, [
                'key' => 'field_vendor_website',
                'label' => 'Website',
                'wrapper' => ['width' => '50'],
            ])
            ->addTextarea(Vendors::FIELD_DESCRIPTION, [
                'key' => 'field_vendor_description',
                'label' => 'Short description',
                'instructions' => 'What they sell or offer.',
                'rows' => 4,
                'maxlength' => 500,
                'new_lines' => '',
            ])
            ->addText(Vendors::FIELD_SOCIAL, [
                'key' => 'field_vendor_social',
                'label' => 'Social media',
                'instructions' => 'Instagram / Facebook handle or URL.',
            ]);

        /* ---------------------------------------------------------------------
         * Tab: Contact
         * ------------------------------------------------------------------ */
        $fields
            ->addTab('Contact')

            ->addText(Vendors::FIELD_FIRST_NAME, [
                'key' => 'field_vendor_contact_first_name',
                'label' => 'First name',
                'required' => 1,
                'wrapper' => ['width' => '50'],
            ])
            ->addText(Vendors::FIELD_LAST_NAME, [
                'key' => 'field_vendor_contact_last_name',
                'label' => 'Last name',
                'required' => 1,
                'wrapper' => ['width' => '50'],
            ])
            ->addEmail(Vendors::FIELD_EMAIL, [
                'key' => 'field_vendor_contact_email',
                'label' => 'Email',
                'instructions' => 'Approval, payment and decline emails go to this address.',
                'required' => 1,
                'wrapper' => ['width' => '50'],
            ])
            ->addText(Vendors::FIELD_PHONE, [
                'key' => 'field_vendor_contact_phone',
                'label' => 'Phone',
                'wrapper' => ['width' => '50'],
            ]);

        /* ---------------------------------------------------------------------
         * Tab: Show
         * ------------------------------------------------------------------ */
        $fields
            ->addTab('Show')

            ->addPostObject(Vendors::FIELD_EVENT, [
                'key' => 'field_vendor_event',
                'label' => 'Show',
                'instructions' => 'The show this application is for. Each show a vendor picks becomes its own application.',
                'post_type' => [Vendors::EVENT_POST_TYPE],
                'return_format' => 'id',
                'allow_null' => 0,
                'ui' => 1,
                'required' => 1,
            ])
            ->addSelect(Vendors::FIELD_NEEDS_POWER, [
                'key' => 'field_vendor_needs_power',
                'label' => 'Needs electricity',
                'choices' => ['no' => 'No', 'yes' => 'Yes'],
                'default_value' => 'no',
                'return_format' => 'value',
                'wrapper' => ['width' => '50'],
            ])
            ->addTextarea(Vendors::FIELD_NOTES, [
                'key' => 'field_vendor_notes',
                'label' => 'Notes from vendor',
                'rows' => 3,
                'new_lines' => '',
            ]);

        /* ---------------------------------------------------------------------
         * Tab: Review
         * ------------------------------------------------------------------ */
        $fields
            ->addTab('Review')

            ->addSelect(Vendors::FIELD_STATUS, [
                'key' => 'field_vendor_status',
                'label' => 'Application status',
                'instructions' => 'Approving locks in the fee and emails the vendor a private payment link. Payment is only requested after approval.',
                'choices' => Vendors::statuses(),
                'default_value' => Vendors::STATUS_PENDING,
                'return_format' => 'value',
                'wrapper' => ['width' => '50'],
            ])
            ->addNumber(Vendors::FIELD_FEE_OVERRIDE, [
                'key' => 'field_vendor_fee',
                'label' => 'Fee override',
                'instructions' => 'Leave empty to charge the show\'s default vendor fee.',
                'prepend' => '$',
                'min' => 0,
                'step' => 0.01,
                'wrapper' => ['width' => '50'],
            ])
            ->addTextarea(Vendors::FIELD_DECLINE_REASON, [
                'key' => 'field_vendor_decline_reason',
                'label' => 'Reason (sent to the vendor)',
                'instructions' => 'Optional. Included in the decline email, e.g. "This venue does not allow outside food vendors."',
                'rows' => 3,
                'new_lines' => '',
            ])
                ->conditional(Vendors::FIELD_STATUS, '==', Vendors::STATUS_DECLINED)
            ->addTrueFalse(Vendors::FIELD_SEND_EMAIL, [
                'key' => 'field_vendor_send_status_email',
                'label' => 'Email the vendor',
                'message' => 'Send the approval / decline email when the status changes',
                'default_value' => 1,
                'ui' => 1,
            ])
            ->addTextarea(Vendors::FIELD_ADMIN_NOTES, [
                'key' => 'field_vendor_admin_notes',
                'label' => 'Internal notes',
                'instructions' => 'Staff only. Never sent to the vendor.',
                'rows' => 3,
                'new_lines' => '',
            ]);

        return $fields->build();
    }
}
