<?php

/**
 * File path + filename: app/Support/Vendors/bootstrap.php
 *
 * Boots the Vendor feature:
 * - Vendors                  Shared keys, statuses, show rules, fees, history, links, email
 * - VendorPostType           `vendor` CPT (one per business), color-coded `vendor_type`, admin list
 * - VendorApplicationForm    New-vendor application (4 pages) → Vendor + pending show row
 * - VendorReturning          Returning vendor: email → personal sign-up link (or friendly stop)
 * - VendorApproval           Manual review: approve (payment link), reject (reason emailed), block
 * - VendorPayment            Sign-up & payment form (Stripe) → show row becomes Active
 * - VendorForms              Builds all three Gravity Forms + Stripe / EmailOctopus feeds in code
 * - VendorExport             Per-show CSV and logo ZIP exports
 * - EventVendorModernEditor  "Vendors" step in MagePeople's Modern event editor
 *
 * Also part of the feature:
 * - ACF: app/Fields/Vendor.php, EventVendorSettings.php, VendorTypeSettings.php
 * - Settings: app/Options/VendorSettings.php
 * - Blocks: app/Blocks/VendorSignup.php, FeaturedVendors.php (+ Blade views)
 * - Guide: resources/gravity-forms/README-vendors.md
 *
 * Load order: include from app/setup.php after the UserRoles bootstrap so the
 * `vendor` capability mapping in OperationalRoles is already registered.
 */

namespace App\Support\Vendors;

defined('ABSPATH') || exit;

require_once __DIR__.'/Vendors.php';
require_once __DIR__.'/VendorPostType.php';
require_once __DIR__.'/VendorApplicationForm.php';
require_once __DIR__.'/VendorReturning.php';
require_once __DIR__.'/VendorApproval.php';
require_once __DIR__.'/VendorPayment.php';
require_once __DIR__.'/VendorForms.php';
require_once __DIR__.'/VendorExport.php';
require_once __DIR__.'/EventVendorModernEditor.php';

VendorPostType::register();
VendorApplicationForm::register();
VendorReturning::register();
VendorApproval::register();
VendorPayment::register();
VendorForms::register();
VendorExport::register();
EventVendorModernEditor::register();
