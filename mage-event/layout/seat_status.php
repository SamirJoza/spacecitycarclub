<?php
/**
 * File path + filename: mage-event/layout/seat_status.php (theme override)
 *
 * Purpose:
 * - On car shows, replace MagePeople's "Total Seats / Available Seats" with
 *   "Cars registered" (from Registered Cars). Other events use MagePeople's
 *   original seat box, unchanged.
 */

defined('ABSPATH') || exit;

$event_id = isset($event_id) ? (int) $event_id : 0;

if ($event_id > 0 && class_exists(\App\Support\CarShows\CarShows::class) && \App\Support\CarShows\CarShows::isCarShow($event_id)) {
    \App\Support\CarShows\CarShowFrontend::renderSeatStatus($event_id);

    return;
}

require MPWEM_PLUGIN_DIR.'/templates/layout/seat_status.php';
