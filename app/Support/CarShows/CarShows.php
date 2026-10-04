<?php

/**
 * File path + filename: app/Support/CarShows/CarShows.php
 *
 * Purpose:
 * - Shared keys and rules for car-show registration.
 *
 * What counts as a car show:
 * - A MagePeople event (`mep_events`) whose Category (`mep_cat`) is "Car Show"
 *   AND whose Organizer (`mep_org`) is "Space City Car Club". Both are matched
 *   by term name or slug, case-insensitively. Other events are left entirely to
 *   MagePeople (tickets, seats, attendee form).
 *
 * Prices and capacity come from MagePeople's own ticket types
 * (`mep_event_ticket_type`, edited in the event's Ticket & Pricing step):
 * - each enabled ticket type = one registration option (e.g. "Early Bird",
 *   "Regular"), price per car = ticket price
 * - ticket quantity = how many cars that option allows (0/empty = no limit)
 * - ticket "sale end date" ends that option (early-bird pricing)
 * - registration closes when the show starts
 * Cars are counted from Registered Cars (not MagePeople's seat counters).
 *
 * Registered cars:
 * - One `registered_car` post per car (see RegisteredCarPostType), numbered per
 *   show in payment order: 1, 2, 3 … The numbers never repeat within a show.
 */

namespace App\Support\CarShows;

use App\Support\Vendors\Vendors;

defined('ABSPATH') || exit;

final class CarShows
{
    public const POST_TYPE = 'registered_car';

    public const EVENT_POST_TYPE = 'mep_events';

    public const TAX_CATEGORY = 'mep_cat';

    public const TAX_ORGANIZER = 'mep_org';

    /** Last number handed out for a show (event post meta, internal). */
    public const EVENT_LAST_NUMBER = '_sccc_car_number_last';

    /* Registered car meta. */
    public const META_SHOW = '_car_show_id';

    public const META_NUMBER = '_car_number';

    public const META_YEAR = '_car_year';

    public const META_MAKE = '_car_make';

    public const META_MODEL = '_car_model';

    public const META_COLOR = '_car_color';

    public const META_NOTES = '_car_notes';

    public const META_FIRST = '_owner_first_name';

    public const META_LAST = '_owner_last_name';

    public const META_EMAIL = '_owner_email';

    public const META_PHONE = '_owner_phone';

    public const META_CITY = '_owner_city';

    public const META_USER = '_owner_user_id';

    public const META_ENTRY = '_gf_entry_id';

    public const META_AMOUNT = '_amount_paid';

    public const META_TRANSACTION = '_transaction_id';

    public const META_SOURCE = '_registration_source';

    /** Ticket type (sanitized name) the car was registered with. */
    public const META_TICKET = '_car_ticket';

    public const META_TICKET_NAME = '_car_ticket_name';

    /** Most cars one registration can include. */
    public const MAX_CARS_PER_REGISTRATION = 10;

    /** Gravity Form CSS class. */
    public const FORM_CLASS = 'sccc-car-registration';

    /* -------------------------------------------------------------------------
     * Is this a car show?
     * ---------------------------------------------------------------------- */

    public static function isCarShow(int $eventId): bool
    {
        if ($eventId <= 0 || get_post_type($eventId) !== self::EVENT_POST_TYPE) {
            return false;
        }

        $result = self::hasTerm($eventId, self::TAX_CATEGORY, self::categoryNames())
            && self::hasTerm($eventId, self::TAX_ORGANIZER, self::organizerNames());

        return (bool) apply_filters('sccc_is_car_show', $result, $eventId);
    }

    /** @return array<int, string> */
    public static function categoryNames(): array
    {
        return (array) apply_filters('sccc_car_show_category_names', ['Car Show', 'Car Shows']);
    }

    /** @return array<int, string> */
    public static function organizerNames(): array
    {
        return (array) apply_filters('sccc_car_show_organizer_names', ['Space City Car Club']);
    }

    /**
     * @param  array<int, string>  $names
     */
    private static function hasTerm(int $postId, string $taxonomy, array $names): bool
    {
        $terms = get_the_terms($postId, $taxonomy);

        if (! is_array($terms) || ! $terms) {
            return false;
        }

        $wanted = array_map('sanitize_title', $names);

        foreach ($terms as $term) {
            if (in_array(sanitize_title($term->name), $wanted, true) || in_array($term->slug, $wanted, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Car-show events for admin dropdowns, newest first.
     *
     * @return array<int, int>
     */
    public static function carShowEventIds(): array
    {
        $termIds = [];
        $wanted = array_map('sanitize_title', self::categoryNames());

        $terms = get_terms(['taxonomy' => self::TAX_CATEGORY, 'hide_empty' => false]);
        foreach (is_array($terms) ? $terms : [] as $term) {
            if (in_array(sanitize_title($term->name), $wanted, true) || in_array($term->slug, $wanted, true)) {
                $termIds[] = (int) $term->term_id;
            }
        }

        if (! $termIds) {
            return [];
        }

        $ids = get_posts([
            'post_type' => self::EVENT_POST_TYPE,
            'post_status' => ['publish', 'future', 'draft', 'private'],
            'posts_per_page' => 100,
            'fields' => 'ids',
            'no_found_rows' => true,
            'tax_query' => [['taxonomy' => self::TAX_CATEGORY, 'field' => 'term_id', 'terms' => $termIds]],
        ]);

        $ids = array_values(array_filter(array_map('intval', $ids), [self::class, 'isCarShow']));
        usort($ids, static fn (int $a, int $b): int => Vendors::eventTimestamp($b) <=> Vendors::eventTimestamp($a));

        return $ids;
    }

    /* -------------------------------------------------------------------------
     * Tickets (MagePeople ticket types = registration options)
     * ---------------------------------------------------------------------- */

    /**
     * All enabled ticket types for a show.
     *
     * @return array<string, array{key:string,name:string,price:float,capacity:int,sold:int,left:int,ends:int,on_sale:bool,details:string}>
     */
    public static function tickets(int $eventId): array
    {
        $types = get_post_meta($eventId, 'mep_event_ticket_type', true);
        $types = is_array($types) ? $types : [];
        $now = time();
        $tickets = [];

        foreach ($types as $type) {
            if (! is_array($type)) {
                continue;
            }

            $name = trim((string) ($type['option_name_t'] ?? ''));
            $enabled = ($type['option_ticket_enable'] ?? 'yes') !== 'no';

            if ($name === '' || ! $enabled) {
                continue;
            }

            $key = sanitize_title($name);
            $price = (float) apply_filters('sccc_car_ticket_price', (float) preg_replace('/[^0-9.]/', '', (string) ($type['option_price_t'] ?? '0')), $type, $eventId);
            $capacity = max(0, (int) ($type['option_qty_t'] ?? 0));
            $endsRaw = trim((string) ($type['option_sale_end_date_t'] ?? ''));
            $ends = $endsRaw !== '' ? Vendors::localTimestamp($endsRaw) : 0;
            $sold = self::registeredCount($eventId, $key);
            $left = $capacity > 0 ? max(0, $capacity - $sold) : PHP_INT_MAX;

            $tickets[$key] = [
                'key' => $key,
                'name' => $name,
                'price' => round(max(0.0, $price), 2),
                'capacity' => $capacity,
                'sold' => $sold,
                'left' => $left,
                'ends' => $ends,
                'on_sale' => (! $ends || $ends > $now) && $left > 0,
                'details' => trim(wp_strip_all_tags((string) ($type['option_details_t'] ?? ''))),
            ];
        }

        return $tickets;
    }

    /**
     * Ticket types that can be bought right now, cheapest first.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function onSaleTickets(int $eventId): array
    {
        $tickets = array_filter(self::tickets($eventId), static fn (array $t): bool => $t['on_sale']);
        uasort($tickets, static fn (array $a, array $b): int => $a['price'] <=> $b['price']);

        return $tickets;
    }

    /** Cars still available across on-sale tickets (PHP_INT_MAX when unlimited). */
    public static function spotsLeft(int $eventId): int
    {
        $left = 0;

        foreach (self::onSaleTickets($eventId) as $ticket) {
            if ($ticket['left'] === PHP_INT_MAX) {
                return PHP_INT_MAX;
            }
            $left += $ticket['left'];
        }

        return $left;
    }

    /** Sum of ticket quantities (0 = at least one ticket is unlimited). */
    public static function capacity(int $eventId): int
    {
        $total = 0;

        foreach (self::tickets($eventId) as $ticket) {
            if ($ticket['capacity'] <= 0) {
                return 0;
            }
            $total += $ticket['capacity'];
        }

        return $total;
    }

    /**
     * Why registration is closed ('' when it's open).
     *
     * @return string one of '', 'not_car_show', 'unpublished', 'not_set_up', 'past', 'ended', 'full'
     */
    public static function closedReason(int $eventId): string
    {
        if (! self::isCarShow($eventId)) {
            return 'not_car_show';
        }

        if (get_post_status($eventId) !== 'publish') {
            return 'unpublished';
        }

        $start = Vendors::eventTimestamp($eventId);
        if ($start && $start < time()) {
            return 'past';
        }

        $tickets = self::tickets($eventId);
        if (! $tickets) {
            return 'not_set_up';
        }

        if (self::onSaleTickets($eventId)) {
            return '';
        }

        // Nothing on sale: full if any ticket is still within its sale window.
        foreach ($tickets as $ticket) {
            if (! $ticket['ends'] || $ticket['ends'] > time()) {
                return 'full';
            }
        }

        return 'ended';
    }

    public static function isOpen(int $eventId): bool
    {
        return self::closedReason($eventId) === '';
    }

    public static function closedMessage(int $eventId): string
    {
        switch (self::closedReason($eventId)) {
            case 'full':
                return self::option('car_show_full_message', __('This show is full — every car spot has been taken. Follow us for the next one!', 'sccc'));

            case 'not_set_up':
            case 'unpublished':
                return __('Car registration for this show opens soon.', 'sccc');

            default:
                return self::option('car_show_closed_message', __('Car registration for this show is closed.', 'sccc'));
        }
    }

    /* -------------------------------------------------------------------------
     * Registered cars
     * ---------------------------------------------------------------------- */

    /** Registered (not trashed) cars for a show, optionally for one ticket type. */
    public static function registeredCount(int $eventId, string $ticketKey = ''): int
    {
        global $wpdb;

        if ($eventId <= 0) {
            return 0;
        }

        $sql = "SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = %s AND m.meta_value = %s";
        $args = [self::META_SHOW, (string) $eventId];

        if ($ticketKey !== '') {
            $sql .= " INNER JOIN {$wpdb->postmeta} t ON t.post_id = p.ID AND t.meta_key = %s AND t.meta_value = %s";
            $args[] = self::META_TICKET;
            $args[] = $ticketKey;
        }

        $sql .= " WHERE p.post_type = %s AND p.post_status = 'publish'";
        $args[] = self::POST_TYPE;

        return (int) $wpdb->get_var($wpdb->prepare($sql, $args)); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
    }

    /**
     * Hand out the next $count numbers for a show. Uses a MySQL named lock so two
     * registrations completing at the same moment can never get the same number.
     *
     * @return array<int, int>
     */
    public static function reserveNumbers(int $eventId, int $count): array
    {
        global $wpdb;

        $count = max(1, $count);
        $lock = 'sccc_car_numbers_'.$eventId;
        $locked = (int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 15)', $lock)) === 1;

        try {
            // Read straight from the database (not the object cache).
            $last = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s LIMIT 1",
                $eventId,
                self::EVENT_LAST_NUMBER
            ));

            // Never go below a number that's already on a car (e.g. cars added by hand).
            $highest = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT MAX(CAST(n.meta_value AS UNSIGNED)) FROM {$wpdb->postmeta} n
                 INNER JOIN {$wpdb->postmeta} s ON s.post_id = n.post_id AND s.meta_key = %s AND s.meta_value = %s
                 WHERE n.meta_key = %s",
                self::META_SHOW,
                (string) $eventId,
                self::META_NUMBER
            ));

            $start = max($last, $highest) + 1;
            $numbers = range($start, $start + $count - 1);

            update_post_meta($eventId, self::EVENT_LAST_NUMBER, (string) end($numbers));
            wp_cache_delete($eventId, 'post_meta');
        } finally {
            if ($locked) {
                $wpdb->query($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock));
            }
        }

        return $numbers;
    }

    /** "1969 Chevrolet Camaro" */
    public static function carLabel(int $carId): string
    {
        $parts = array_filter([
            (string) get_post_meta($carId, self::META_YEAR, true),
            (string) get_post_meta($carId, self::META_MAKE, true),
            (string) get_post_meta($carId, self::META_MODEL, true),
        ], static fn (string $part): bool => trim($part) !== '');

        return $parts ? implode(' ', $parts) : __('(car details missing)', 'sccc');
    }

    public static function ownerName(int $carId): string
    {
        return trim(get_post_meta($carId, self::META_FIRST, true).' '.get_post_meta($carId, self::META_LAST, true));
    }

    /** Keep the admin title readable: "#12 · 1969 Chevrolet Camaro — Jane Doe". */
    public static function buildTitle(int $carId): string
    {
        $number = (int) get_post_meta($carId, self::META_NUMBER, true);
        $title = ($number ? '#'.$number.' · ' : '').self::carLabel($carId);
        $owner = self::ownerName($carId);

        return $owner !== '' ? $title.' — '.$owner : $title;
    }

    /* -------------------------------------------------------------------------
     * Settings (Theme Settings → Car Show Settings)
     * ---------------------------------------------------------------------- */

    public static function option(string $name, string $default = ''): string
    {
        $value = function_exists('get_field') ? get_field($name, 'option') : '';

        return is_scalar($value) && trim((string) $value) !== '' ? (string) $value : $default;
    }

    public static function staffEmail(): string
    {
        $email = sanitize_email(self::option('car_show_staff_email'));

        return is_email($email) ? $email : Vendors::staffEmail();
    }

    public static function money(float $amount): string
    {
        return '$'.number_format($amount, 2);
    }
}
