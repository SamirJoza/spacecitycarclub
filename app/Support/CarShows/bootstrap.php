<?php

/**
 * File path + filename: app/Support/CarShows/bootstrap.php
 *
 * Boots car-show registration:
 * - CarShows                Rules: what is a car show, fee/deadline/limit, counts, numbering
 * - RegisteredCarPostType   `registered_car` CPT (one per car), admin list, CSV export
 * - CarRegistrationForm     Car Registration Gravity Form (built in code) + Stripe + emails
 * - CarShowFrontend         Event page: registration box, vendor card, "Cars registered"
 * - Forms\GlassForms        Shared neon-glass styling for all vendor + car show forms/buttons
 *
 * Also part of the feature:
 * - Theme overrides of MagePeople templates: mage-event/layout/registration.php,
 *   mage-event/layout/seat_status.php
 * - Event settings: "Club Show" box (app/Fields/EventVendorSettings.php) and the
 *   Modern editor step (app/Support/Vendors/EventVendorModernEditor.php)
 * - Settings: app/Options/CarShowSettings.php
 * - Guide: resources/gravity-forms/README-car-shows.md
 *
 * Load order: after the Vendors bootstrap (uses Vendors helpers for dates,
 * email and Gravity Forms field lookups).
 */

namespace App\Support\CarShows;

defined('ABSPATH') || exit;

require_once dirname(__DIR__).'/Forms/GlassForms.php';
require_once __DIR__.'/CarShows.php';
require_once __DIR__.'/RegisteredCarPostType.php';
require_once __DIR__.'/CarRegistrationForm.php';
require_once __DIR__.'/CarShowFrontend.php';

\App\Support\Forms\GlassForms::register();
RegisteredCarPostType::register();
CarRegistrationForm::register();
