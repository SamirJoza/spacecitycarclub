<?php

/**
 * File path + filename: app/Support/Vendors/bootstrap.php
 *
 * Boots the Vendor application feature:
 * - Vendors                  Shared keys, statuses and helpers
 * - VendorPostType           `vendor` CPT, `vendor_type` taxonomy, admin list + record box
 * - VendorApplicationForm    Gravity Forms "Vendor Application" → one pending application per show
 * - VendorApproval           Manual review: approve (payment link email), decline, resend
 * - VendorPayment            Gravity Forms "Vendor Payment" (Stripe) → marks the application paid
 *
 * ACF field groups live in app/Fields/Vendor.php and app/Fields/EventVendorSettings.php,
 * settings in app/Options/VendorSettings.php. Form JSON + setup steps:
 * resources/gravity-forms/.
 *
 * Load order: include from app/setup.php after the UserRoles bootstrap so the
 * `vendor` capability mapping in OperationalRoles is already registered.
 */

namespace App\Support\Vendors;

defined('ABSPATH') || exit;

require_once __DIR__.'/Vendors.php';
require_once __DIR__.'/VendorPostType.php';
require_once __DIR__.'/VendorApplicationForm.php';
require_once __DIR__.'/VendorApproval.php';
require_once __DIR__.'/VendorPayment.php';

VendorPostType::register();
VendorApplicationForm::register();
VendorApproval::register();
VendorPayment::register();
