<?php

/**
 * File path + filename: app/Support/Woo/EventFrontendCleanup.php
 *
 * Space City Car Club frontend cleanup for MagePeople / WooCommerce Event Manager.
 *
 * Purpose:
 * - Remove/hide plugin output that is not useful for the SCCC event experience.
 * - Hide empty registration/error message wrappers when no ticket is defined.
 * - Hide the "Total Seats" sidebar block only when the event's saved
 *   MagePeople "Show Available Seat?" setting is turned off.
 *
 * Notes:
 * - This file does not change event access logic.
 * - This file does not affect admin settings.
 * - This file only cleans frontend output on single MagePeople event pages.
 * - Seat status is NOT hidden globally.
 * - Seat status is controlled by the event meta key: mep_available_seat.
 */

namespace App\Support\Woo;

final class EventFrontendCleanup
{
    /**
     * Prevent duplicate hook registration if the file is loaded more than once.
     */
    private static bool $registered = false;

    /**
     * MagePeople event post type.
     */
    private const EVENT_POST_TYPE = 'mep_events';

    /**
     * MagePeople "Show Available Seat?" event meta key.
     *
     * Expected possible values may include:
     * - on
     * - yes
     * - 1
     * - true
     * - off
     * - no
     * - 0
     * - false
     * - empty string
     */
    private const AVAILABLE_SEAT_META_KEY = 'mep_available_seat';

    /**
     * Register frontend cleanup hooks.
     */
    public static function register(): void
    {
        if (self::$registered) {
            return;
        }

        self::$registered = true;

        add_filter('body_class', [self::class, 'addBodyClasses'], 40);
        add_action('wp_head', [self::class, 'printCleanupCss'], 40);
        add_action('wp_footer', [self::class, 'printCleanupScript'], 40);
    }

    /**
     * Add body classes so CSS/JS can react to the saved event seat setting.
     *
     * @param array<int,string> $classes
     *
     * @return array<int,string>
     */
    public static function addBodyClasses(array $classes): array
    {
        if (! self::isSingleEventPage()) {
            return $classes;
        }

        $eventId = self::getCurrentEventId();

        if (! $eventId) {
            return $classes;
        }

        $classes[] = 'sccc-event-cleanup-enabled';

        if (! self::eventHasAvailableSeatMeta($eventId)) {
            $classes[] = 'sccc-event-seat-meta-missing';

            return array_values(array_unique($classes));
        }

        if (self::eventShouldShowAvailableSeats($eventId)) {
            $classes[] = 'sccc-event-seats-enabled';
        } else {
            $classes[] = 'sccc-event-seats-disabled';
        }

        return array_values(array_unique($classes));
    }

    /**
     * Print small CSS cleanup for single event pages.
     */
    public static function printCleanupCss(): void
    {
        if (! self::isSingleEventPage()) {
            return;
        }

        ?>
        <style id="sccc-event-frontend-cleanup-css">
            /*
             * MagePeople sometimes outputs an empty registration/error message box:
             * <div class="mpwem_style"><div class="reg_close_msg"></div></div>
             *
             * Hide the empty message itself and the wrapper when supported.
             */
            body.single-mep_events .reg_close_msg:empty {
                display: none !important;
            }

            body.single-mep_events .mpwem_style:has(> .reg_close_msg:empty) {
                display: none !important;
            }

            /*
             * Hide seat status only when the event's "Show Available Seat?"
             * setting is saved as disabled/off.
             *
             * If the setting is on, or if the meta key is missing, this rule
             * does not hide the block.
             */
            body.single-mep_events.sccc-event-seats-disabled .mpwem_seat_status {
                display: none !important;
            }
        </style>
        <?php
    }

    /**
     * Print JS fallback cleanup.
     *
     * This handles cases where:
     * - :has() is not available
     * - the empty message contains whitespace
     * - plugin scripts inject or alter markup after initial render
     * - the seat block is injected after initial page load
     */
    public static function printCleanupScript(): void
    {
        if (! self::isSingleEventPage()) {
            return;
        }

        $eventId = self::getCurrentEventId();
        $hasSeatMeta = $eventId ? self::eventHasAvailableSeatMeta($eventId) : false;
        $showSeats = $eventId ? self::eventShouldShowAvailableSeats($eventId) : true;

        ?>
        <script id="sccc-event-frontend-cleanup-js">
            (function () {
                const hasSeatMeta = <?php echo wp_json_encode($hasSeatMeta); ?>;
                const showSeats = <?php echo wp_json_encode($showSeats); ?>;

                function isVisuallyEmpty(element) {
                    if (!element) {
                        return true;
                    }

                    return element.textContent.trim() === '' && element.children.length === 0;
                }

                function cleanupEmptyRegistrationMessages() {
                    document.querySelectorAll('body.single-mep_events .reg_close_msg').forEach(function (message) {
                        if (!isVisuallyEmpty(message)) {
                            return;
                        }

                        message.style.display = 'none';

                        const wrapper = message.parentElement;

                        if (
                            wrapper &&
                            wrapper.classList.contains('mpwem_style') &&
                            wrapper.children.length === 1
                        ) {
                            wrapper.style.display = 'none';
                        }
                    });
                }

                function cleanupSeatStatusFromSavedToggle() {
                    /*
                     * If the meta key is missing, do nothing.
                     * This avoids accidentally hiding seats if MagePeople changes
                     * the meta key or if the setting has not been saved yet.
                     */
                    if (!hasSeatMeta) {
                        return;
                    }

                    document.querySelectorAll('body.single-mep_events .mpwem_seat_status').forEach(function (seatStatus) {
                        if (showSeats) {
                            seatStatus.style.display = '';
                            seatStatus.classList.remove('sccc-seat-status-hidden-by-toggle');
                            return;
                        }

                        seatStatus.style.display = 'none';
                        seatStatus.classList.add('sccc-seat-status-hidden-by-toggle');
                    });
                }

                function runCleanup() {
                    cleanupEmptyRegistrationMessages();
                    cleanupSeatStatusFromSavedToggle();
                }

                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', runCleanup);
                } else {
                    runCleanup();
                }

                /*
                 * Run again after plugin scripts have had time to render/adjust.
                 */
                window.setTimeout(runCleanup, 250);
                window.setTimeout(runCleanup, 1000);
            })();
        </script>
        <?php
    }

    /**
     * Check if this is a single MagePeople event page.
     */
    private static function isSingleEventPage(): bool
    {
        return function_exists('is_singular') && is_singular(self::EVENT_POST_TYPE);
    }

    /**
     * Get the current MagePeople event ID.
     */
    private static function getCurrentEventId(): int
    {
        if (! self::isSingleEventPage()) {
            return 0;
        }

        $eventId = get_queried_object_id();

        return $eventId ? (int) $eventId : 0;
    }

    /**
     * Check whether the MagePeople seat toggle meta key exists.
     */
    private static function eventHasAvailableSeatMeta(int $eventId): bool
    {
        if (! $eventId) {
            return false;
        }

        return metadata_exists('post', $eventId, self::AVAILABLE_SEAT_META_KEY);
    }

    /**
     * Determine whether the event should show available/total seats.
     */
    private static function eventShouldShowAvailableSeats(int $eventId): bool
    {
        if (! $eventId) {
            return true;
        }

        if (! self::eventHasAvailableSeatMeta($eventId)) {
            return true;
        }

        $value = get_post_meta($eventId, self::AVAILABLE_SEAT_META_KEY, true);

        return self::isTruthyMetaValue($value);
    }

    /**
     * Normalize common truthy/falsy plugin setting values.
     *
     * @param mixed $value
     */
    private static function isTruthyMetaValue($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (float) $value > 0;
        }

        if (is_array($value)) {
            return ! empty($value);
        }

        $normalized = strtolower(trim((string) $value));

        if ($normalized === '') {
            return false;
        }

        $truthyValues = [
            '1',
            'yes',
            'y',
            'true',
            'on',
            'enable',
            'enabled',
            'show',
            'active',
        ];

        $falsyValues = [
            '0',
            'no',
            'n',
            'false',
            'off',
            'disable',
            'disabled',
            'hide',
            'inactive',
        ];

        if (in_array($normalized, $truthyValues, true)) {
            return true;
        }

        if (in_array($normalized, $falsyValues, true)) {
            return false;
        }

        /*
         * Unknown non-empty values are treated as enabled to avoid accidentally
         * hiding seats if MagePeople stores a custom value.
         */
        return true;
    }
}

/*
|--------------------------------------------------------------------------
| Auto-register
|--------------------------------------------------------------------------
|
| The file only needs to be loaded once with require_once.
| No separate EventFrontendCleanup::register() call is needed elsewhere.
|
*/

EventFrontendCleanup::register();