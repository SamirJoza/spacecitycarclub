<?php
/**
 * File path + filename: mage-event/layout/registration.php (theme override)
 *
 * Purpose:
 * - MagePeople loads this instead of its own layout/registration.php
 *   (MPWEM_Functions::template_path looks in the theme's mage-event/ folder).
 * - Car shows (Category "Car Show" + Organizer "Space City Car Club") get our
 *   Car Registration box; every other event gets MagePeople's
 *   original ticket box, unchanged.
 *
 * - Club shows that accept vendors also get a "Vendors wanted" note first
 *   (below the event description, above registration).
 *
 * Keep this file thin: the logic lives in App\Support\CarShows\CarShowFrontend.
 */

defined('ABSPATH') || exit;

$event_id = isset($event_id) ? (int) $event_id : 0;

// "Vendors wanted" note (club shows accepting vendors): below the description, above registration.
if ($event_id > 0 && class_exists(\App\Support\CarShows\CarShowFrontend::class)) {
    \App\Support\CarShows\CarShowFrontend::renderVendorCard($event_id);
}

if ($event_id > 0 && class_exists(\App\Support\CarShows\CarShows::class) && \App\Support\CarShows\CarShows::isCarShow($event_id)) {
    \App\Support\CarShows\CarShowFrontend::renderRegistration($event_id);

    return;
}

require MPWEM_PLUGIN_DIR.'/templates/layout/registration.php';
