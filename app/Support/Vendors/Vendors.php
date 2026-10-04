<?php

/**
 * File path + filename: app/Support/Vendors/Vendors.php
 *
 * Purpose:
 * - Single source of truth for the Vendor feature: slugs, statuses, field and
 *   meta keys, show (event) rules, fees, show history, payment links, Gravity
 *   Forms helpers and the branded email helper.
 *
 * Data model:
 * - One `vendor` post per business (never duplicated — matched by email).
 *   "Vendor since" is the post date.
 * - Vendor status (the business):
 *     pending  → new application waiting for review
 *     approved → approved vendor; can sign up for future club shows directly
 *     rejected → rejected (reason emailed); can be deleted later
 *     blocked  → "do not re-invite"; returning sign-up is refused
 * - Show history (ACF repeater `vendor_show_history`), one row per show:
 *     pending          → applied, waiting for the vendor to be approved
 *     awaiting_payment → approved for this show, payment link sent
 *     active           → paid — the vendor is active for that show
 *     declined         → not accepted for this show
 *     cancelled        → withdrawn / cancelled
 *
 * Shows:
 * - Shows are MagePeople events (`mep_events`) with "Club show: accept vendor
 *   applications" switched on (app/Fields/EventVendorSettings.php). Events of
 *   other organizations leave it off and never appear anywhere in this feature.
 */

namespace App\Support\Vendors;

use WP_Post;

defined('ABSPATH') || exit;

final class Vendors
{
    /* -------------------------------------------------------------------------
     * Slugs
     * ---------------------------------------------------------------------- */

    public const POST_TYPE = 'vendor';

    public const TAXONOMY_TYPE = 'vendor_type';

    public const EVENT_POST_TYPE = 'mep_events';

    /** Vendor type slug for food vendors (checked against each show's food rule). */
    public const FOOD_TYPE_SLUG = 'food-beverage';

    /* -------------------------------------------------------------------------
     * Statuses
     * ---------------------------------------------------------------------- */

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_BLOCKED = 'blocked';

    public const SHOW_PENDING = 'pending';

    public const SHOW_AWAITING = 'awaiting_payment';

    public const SHOW_ACTIVE = 'active';

    public const SHOW_DECLINED = 'declined';

    public const SHOW_CANCELLED = 'cancelled';

    /* -------------------------------------------------------------------------
     * Vendor ACF fields (names). Keys are `field_` + name.
     * ---------------------------------------------------------------------- */

    public const FIELD_STATUS = 'vendor_status';

    public const FIELD_REJECT_REASON = 'vendor_reject_reason';

    public const FIELD_SEND_EMAIL = 'vendor_send_status_email';

    public const FIELD_ADMIN_NOTES = 'vendor_admin_notes';

    public const FIELD_TYPE = 'vendor_type_term';

    public const FIELD_DISPLAY_NAME = 'vendor_display_name';

    public const FIELD_BLURB = 'vendor_blurb';

    public const FIELD_OFFERINGS = 'vendor_offerings';

    public const FIELD_WEBSITE = 'vendor_website';

    public const FIELD_INSTAGRAM = 'vendor_instagram';

    public const FIELD_FACEBOOK = 'vendor_facebook';

    public const FIELD_TIKTOK = 'vendor_tiktok';

    public const FIELD_LOGO = 'vendor_logo';

    public const FIELD_PHOTOS = 'vendor_photos';

    public const FIELD_FIRST_NAME = 'vendor_contact_first_name';

    public const FIELD_LAST_NAME = 'vendor_contact_last_name';

    public const FIELD_ROLE = 'vendor_contact_role';

    public const FIELD_EMAIL = 'vendor_contact_email';

    public const FIELD_PHONE = 'vendor_contact_phone';

    public const FIELD_TEXT_OK = 'vendor_text_ok';

    public const FIELD_ADDRESS = 'vendor_address';

    public const FIELD_CITY = 'vendor_city';

    public const FIELD_STATE = 'vendor_state';

    public const FIELD_ZIP = 'vendor_zip';

    public const FIELD_DAYOF_NAME = 'vendor_dayof_name';

    public const FIELD_DAYOF_PHONE = 'vendor_dayof_phone';

    public const FIELD_BOOTH_TYPE = 'vendor_booth_type';

    public const FIELD_TRAILER_LENGTH = 'vendor_trailer_length';

    public const FIELD_EQUIPMENT = 'vendor_equipment';

    public const FIELD_POWER = 'vendor_power';

    public const FIELD_GENERATOR = 'vendor_generator';

    public const FIELD_OPEN_FLAME = 'vendor_open_flame';

    public const FIELD_STAFF_COUNT = 'vendor_staff_count';

    public const FIELD_NOTES = 'vendor_notes';

    public const FIELD_FEATURE_OK = 'vendor_feature_ok';

    public const FIELD_OFFER = 'vendor_member_offer';

    public const FIELD_HISTORY = 'vendor_show_history';

    /** Repeater sub-field names. */
    public const ROW_EVENT = 'show';

    public const ROW_STATUS = 'status';

    public const ROW_FEE = 'fee';

    public const ROW_PAID_AMOUNT = 'paid_amount';

    public const ROW_PAID_AT = 'paid_at';

    public const ROW_TRANSACTION = 'transaction_id';

    public const ROW_ENTRY = 'payment_entry_id';

    public const ROW_NOTES = 'notes';

    /* -------------------------------------------------------------------------
     * Event (show) ACF fields
     * ---------------------------------------------------------------------- */

    public const EVENT_ACCEPTING = 'vendors_accepting';

    public const EVENT_FEE = 'vendor_fee_default';

    public const EVENT_FOOD_ALLOWED = 'vendors_food_allowed';

    public const EVENT_DEADLINE = 'vendor_application_deadline';

    public const EVENT_PRICING_MODE = 'vendor_pricing_mode';

    public const EVENT_BOOTH_PRICES = 'vendor_booth_prices';

    /* -------------------------------------------------------------------------
     * Internal meta (written by code only)
     * ---------------------------------------------------------------------- */

    public const META_EMAIL_KEY = '_vendor_email_normalized';

    public const META_TYPE_NAME = '_vendor_type_name';

    public const META_SHOW_ID = '_vendor_show_id';            // multiple rows: every show in the history

    public const META_ACTIVE_SHOW_ID = '_vendor_active_show_id'; // multiple rows: shows the vendor is active for

    public const META_LAST_SHOW_DATE = '_vendor_last_show_date';  // Y-m-d H:i:s of the latest show in history (sortable)

    public const META_ENTRY_ID = '_vendor_gf_entry_id';

    public const META_FORM_ID = '_vendor_gf_form_id';

    public const META_MARKETING_OPTIN = '_vendor_marketing_optin';

    public const META_REFERRAL_SOURCE = '_vendor_referral_source';

    public const META_HEARD_ABOUT = '_vendor_heard_about';

    public const META_SIGNATURE = '_vendor_signature';

    public const META_TOKEN = '_vendor_token';

    public const META_TOKEN_EXPIRES = '_vendor_token_expires';

    public const META_APPROVED_AT = '_vendor_approved_at';

    public const META_LINK_EMAILED_AT = '_vendor_link_emailed_at';

    /** Private links stay valid for this long after they are emailed. */
    public const TOKEN_TTL = 30 * DAY_IN_SECONDS;

    /* -------------------------------------------------------------------------
     * Gravity Forms identification (CSS class on the form, Admin Field Labels)
     * ---------------------------------------------------------------------- */

    public const APPLICATION_FORM_CLASS = 'sccc-vendor-application';

    public const RETURNING_FORM_CLASS = 'sccc-vendor-returning';

    public const PAYMENT_FORM_CLASS = 'sccc-vendor-payment';

    public const QUERY_VENDOR = 'vendor';

    public const QUERY_TOKEN = 'vendor_token';

    /* -------------------------------------------------------------------------
     * Labels and choices
     * ---------------------------------------------------------------------- */

    /** @return array<string, string> */
    public static function statuses(): array
    {
        return [
            self::STATUS_PENDING => __('Pending review', 'sccc'),
            self::STATUS_APPROVED => __('Approved vendor', 'sccc'),
            self::STATUS_REJECTED => __('Rejected', 'sccc'),
            self::STATUS_BLOCKED => __('Do not re-invite', 'sccc'),
        ];
    }

    /** @return array<string, string> */
    public static function showStatuses(): array
    {
        return [
            self::SHOW_PENDING => __('Pending review', 'sccc'),
            self::SHOW_AWAITING => __('Awaiting payment', 'sccc'),
            self::SHOW_ACTIVE => __('Active', 'sccc'),
            self::SHOW_DECLINED => __('Declined', 'sccc'),
            self::SHOW_CANCELLED => __('Cancelled', 'sccc'),
        ];
    }

    /**
     * Default vendor types: slug => [name, color]. Colors can be changed per term
     * (Vendors → Vendor Types). Slugs must match the application form choices.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function defaultTypes(): array
    {
        return [
            self::FOOD_TYPE_SLUG => ['Food & Beverage', '#f97316'],
            'automotive-parts' => ['Automotive Parts & Accessories', '#2563eb'],
            'detailing-car-care' => ['Detailing & Car Care', '#0d9488'],
            'automotive-services' => ['Automotive Services', '#4f46e5'],
            'apparel-merchandise' => ['Apparel & Merchandise', '#db2777'],
            'art-photography' => ['Art & Photography', '#9333ea'],
            'other' => ['Other', '#64748b'],
        ];
    }

    /** @return array<string, string> */
    public static function boothTypes(): array
    {
        return [
            '10x10' => __('10×10 space', 'sccc'),
            '10x20' => __('10×20 space', 'sccc'),
            'table' => __('Table only', 'sccc'),
            'food_truck' => __('Food truck / trailer', 'sccc'),
        ];
    }

    public static function statusLabel(string $status): string
    {
        return self::statuses()[$status] ?? ucfirst($status);
    }

    public static function showStatusLabel(string $status): string
    {
        return self::showStatuses()[$status] ?? ucfirst(str_replace('_', ' ', $status));
    }

    /* -------------------------------------------------------------------------
     * Vendor basics
     * ---------------------------------------------------------------------- */

    public static function isVendor($post): bool
    {
        $post = get_post($post);

        return $post instanceof WP_Post && $post->post_type === self::POST_TYPE;
    }

    public static function status(int $vendorId): string
    {
        $status = (string) get_post_meta($vendorId, self::FIELD_STATUS, true);

        return array_key_exists($status, self::statuses()) ? $status : self::STATUS_PENDING;
    }

    public static function normalizeEmail(string $email): string
    {
        return strtolower(trim(sanitize_email($email)));
    }

    /** Find the vendor for an email address (0 when none). */
    public static function findByEmail(string $email): int
    {
        $key = self::normalizeEmail($email);

        if ($key === '') {
            return 0;
        }

        $ids = get_posts([
            'post_type' => self::POST_TYPE,
            'post_status' => ['publish', 'draft', 'pending', 'private'],
            'posts_per_page' => 1,
            'fields' => 'ids',
            'no_found_rows' => true,
            'meta_query' => [['key' => self::META_EMAIL_KEY, 'value' => $key]],
        ]);

        return $ids ? (int) $ids[0] : 0;
    }

    public static function businessName(int $vendorId): string
    {
        $display = trim((string) get_post_meta($vendorId, self::FIELD_DISPLAY_NAME, true));

        return $display !== '' ? $display : html_entity_decode(get_the_title($vendorId), ENT_QUOTES, 'UTF-8');
    }

    public static function contactFirstName(int $vendorId): string
    {
        return trim((string) get_post_meta($vendorId, self::FIELD_FIRST_NAME, true));
    }

    public static function contactName(int $vendorId): string
    {
        return trim(self::contactFirstName($vendorId).' '.get_post_meta($vendorId, self::FIELD_LAST_NAME, true));
    }

    public static function contactEmail(int $vendorId): string
    {
        return sanitize_email((string) get_post_meta($vendorId, self::FIELD_EMAIL, true));
    }

    public static function logoId(int $vendorId): int
    {
        $logo = (int) get_post_meta($vendorId, self::FIELD_LOGO, true);

        return $logo ?: (int) get_post_thumbnail_id($vendorId);
    }

    /** @return \WP_Term|null */
    public static function typeTerm(int $vendorId)
    {
        $terms = get_the_terms($vendorId, self::TAXONOMY_TYPE);

        return is_array($terms) && $terms ? $terms[0] : null;
    }

    public static function typeSlug(int $vendorId): string
    {
        $term = self::typeTerm($vendorId);

        return $term ? (string) $term->slug : '';
    }

    public static function isFoodVendor(int $vendorId): bool
    {
        return self::typeSlug($vendorId) === self::FOOD_TYPE_SLUG;
    }

    /** Hex color for a vendor type term (term meta, falling back to the defaults). */
    public static function typeColor($term): string
    {
        $term = is_numeric($term) ? get_term((int) $term, self::TAXONOMY_TYPE) : $term;

        if (! $term || is_wp_error($term)) {
            return '#64748b';
        }

        $color = sanitize_hex_color((string) get_term_meta($term->term_id, 'vendor_type_color', true));

        return $color ?: (self::defaultTypes()[$term->slug][1] ?? '#64748b');
    }

    /* -------------------------------------------------------------------------
     * Shows (MagePeople events)
     * ---------------------------------------------------------------------- */

    /** Club show that currently accepts vendor applications (upcoming, before its deadline). */
    public static function showIsOpen(int $eventId): bool
    {
        if (! self::isClubShow($eventId)) {
            return false;
        }

        $now = time();
        $start = self::eventTimestamp($eventId);

        if ($start && $start < $now) {
            return false;
        }

        // ACF date pickers store Ymd (e.g. 20270401).
        $deadline = preg_replace('/\D/', '', (string) get_post_meta($eventId, self::EVENT_DEADLINE, true));
        if (strlen($deadline) === 8) {
            $deadlineTs = self::localTimestamp(substr($deadline, 0, 4).'-'.substr($deadline, 4, 2).'-'.substr($deadline, 6, 2).' 23:59:59');
            if ($deadlineTs && $deadlineTs < $now) {
                return false;
            }
        }

        return true;
    }

    /** Published event flagged as one of our own shows that takes vendors. */
    public static function isClubShow(int $eventId): bool
    {
        return $eventId > 0
            && get_post_type($eventId) === self::EVENT_POST_TYPE
            && get_post_status($eventId) === 'publish'
            && (bool) get_post_meta($eventId, self::EVENT_ACCEPTING, true);
    }

    /**
     * All open club shows, soonest first.
     *
     * @return array<int, int> event IDs
     */
    public static function openShows(): array
    {
        $ids = get_posts([
            'post_type' => self::EVENT_POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => 50,
            'fields' => 'ids',
            'no_found_rows' => true,
            'meta_query' => [['key' => self::EVENT_ACCEPTING, 'value' => '1']],
        ]);

        $open = array_values(array_filter(array_map('intval', $ids), [self::class, 'showIsOpen']));
        usort($open, static fn (int $a, int $b): int => self::eventTimestamp($a) <=> self::eventTimestamp($b));

        return $open;
    }

    /** The next upcoming club show that takes vendors (even if its deadline passed), or 0. */
    public static function nextClubShow(): int
    {
        $now = time();
        $ids = get_posts([
            'post_type' => self::EVENT_POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => 50,
            'fields' => 'ids',
            'no_found_rows' => true,
            'meta_query' => [['key' => self::EVENT_ACCEPTING, 'value' => '1']],
        ]);

        $upcoming = array_filter(array_map('intval', $ids), static fn (int $id): bool => self::eventTimestamp($id) >= $now - DAY_IN_SECONDS);
        usort($upcoming, static fn (int $a, int $b): int => self::eventTimestamp($a) <=> self::eventTimestamp($b));

        return $upcoming ? (int) $upcoming[0] : 0;
    }

    public static function showAllowsFood(int $eventId): bool
    {
        $value = get_post_meta($eventId, self::EVENT_FOOD_ALLOWED, true);

        return $value === '' || (bool) $value;
    }

    /** Food rule: food vendors only at shows that allow food. */
    public static function showAllowsType(int $eventId, string $typeSlug): bool
    {
        return $typeSlug !== self::FOOD_TYPE_SLUG || self::showAllowsFood($eventId);
    }

    /**
     * Vendor fee for a show, honoring the optional booth-type price table.
     */
    public static function showFee(int $eventId, string $boothType = ''): float
    {
        $flat = get_post_meta($eventId, self::EVENT_FEE, true);
        $fee = is_numeric($flat) ? (float) $flat : 0.0;

        if ((string) get_post_meta($eventId, self::EVENT_PRICING_MODE, true) === 'booth' && $boothType !== '' && function_exists('get_field')) {
            foreach ((array) get_field(self::EVENT_BOOTH_PRICES, $eventId) as $row) {
                if (($row['booth_type'] ?? '') === $boothType && is_numeric($row['price'] ?? null)) {
                    $fee = (float) $row['price'];
                    break;
                }
            }
        }

        return round(max(0.0, $fee), 2);
    }

    /**
     * Show date/time. MagePeople keeps several date metas that can drift apart
     * (e.g. a stale "upcoming" value after the date was changed), so use the
     * latest valid one — an event is never treated as past too early.
     */
    public static function eventTimestamp(int $eventId): int
    {
        $candidates = [
            (string) get_post_meta($eventId, 'event_upcoming_datetime', true),
            (string) get_post_meta($eventId, 'event_start_datetime', true),
            trim(get_post_meta($eventId, 'event_start_date', true).' '.get_post_meta($eventId, 'event_start_time', true)),
        ];

        $latest = 0;
        foreach ($candidates as $raw) {
            $raw = trim($raw);
            if ($raw === '' || $raw === '0') {
                continue;
            }
            $latest = max($latest, self::localTimestamp($raw));
        }

        return $latest;
    }

    /**
     * Why a show is not open for vendor applications (empty = open). For admins.
     *
     * @return array<int, string>
     */
    public static function showClosedReasons(int $eventId): array
    {
        $reasons = [];

        if (get_post_type($eventId) !== self::EVENT_POST_TYPE) {
            return ['not a MagePeople event'];
        }

        if (get_post_status($eventId) !== 'publish') {
            $reasons[] = sprintf('event is "%s", not published', get_post_status($eventId));
        }

        $accepting = get_post_meta($eventId, self::EVENT_ACCEPTING, true);
        if (! $accepting) {
            $reasons[] = sprintf('"Club show: accept vendor applications" is off (saved value: %s)', var_export($accepting, true));
        }

        $start = self::eventTimestamp($eventId);
        if ($start && $start < time()) {
            $reasons[] = sprintf('show date %s has passed', wp_date('M j, Y g:i a', $start));
        }

        $deadline = preg_replace('/\D/', '', (string) get_post_meta($eventId, self::EVENT_DEADLINE, true));
        if (strlen($deadline) === 8) {
            $deadlineTs = self::localTimestamp(substr($deadline, 0, 4).'-'.substr($deadline, 4, 2).'-'.substr($deadline, 6, 2).' 23:59:59');
            if ($deadlineTs && $deadlineTs < time()) {
                $reasons[] = sprintf('vendor application deadline %s has passed', wp_date('M j, Y', $deadlineTs));
            }
        }

        return $reasons;
    }

    /** Parse a site-local date/time string into a timestamp. */
    public static function localTimestamp(string $raw): int
    {
        try {
            return (new \DateTimeImmutable($raw, wp_timezone()))->getTimestamp();
        } catch (\Exception $e) {
            return 0;
        }
    }

    public static function eventTitle(int $eventId): string
    {
        return $eventId > 0 && get_post($eventId) ? html_entity_decode(get_the_title($eventId), ENT_QUOTES, 'UTF-8') : __('(show not found)', 'sccc');
    }

    /** "Show — Sat, Apr 18, 2027 · Venue" */
    public static function eventLabel(int $eventId, bool $withVenue = true): string
    {
        $label = self::eventTitle($eventId);
        $ts = self::eventTimestamp($eventId);

        if ($ts) {
            $label .= ' — '.wp_date('D, M j, Y', $ts);
        }

        $venue = trim((string) get_post_meta($eventId, 'mep_location_venue', true));
        if ($withVenue && $venue !== '') {
            $label .= ' · '.$venue;
        }

        return $label;
    }

    /* -------------------------------------------------------------------------
     * Show history
     * ---------------------------------------------------------------------- */

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function history(int $vendorId): array
    {
        $rows = function_exists('get_field') ? get_field(self::FIELD_HISTORY, $vendorId, false) : [];

        if (! is_array($rows)) {
            return [];
        }

        // Unformatted ACF values are keyed by sub-field key → map to names.
        $out = [];
        foreach ($rows as $row) {
            $mapped = [];
            foreach ((array) $row as $key => $value) {
                $name = str_starts_with((string) $key, 'field_vendor_row_') ? substr((string) $key, strlen('field_vendor_row_')) : (string) $key;
                $mapped[$name] = $value;
            }
            $out[] = $mapped;
        }

        return $out;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    public static function saveHistory(int $vendorId, array $rows): void
    {
        usort($rows, static fn (array $a, array $b): int => self::eventTimestamp((int) ($a[self::ROW_EVENT] ?? 0)) <=> self::eventTimestamp((int) ($b[self::ROW_EVENT] ?? 0)));

        $keyed = [];
        foreach ($rows as $row) {
            $item = [];
            foreach ($row as $name => $value) {
                $item['field_vendor_row_'.$name] = $value;
            }
            $keyed[] = $item;
        }

        if (function_exists('update_field')) {
            update_field('field_'.self::FIELD_HISTORY, $keyed, $vendorId);
        }

        self::syncIndexes($vendorId);
    }

    /** Row index for a show, or -1. */
    public static function historyIndex(array $rows, int $eventId): int
    {
        foreach ($rows as $index => $row) {
            if ((int) ($row[self::ROW_EVENT] ?? 0) === $eventId) {
                return (int) $index;
            }
        }

        return -1;
    }

    public static function showRowStatus(int $vendorId, int $eventId): string
    {
        $rows = self::history($vendorId);
        $index = self::historyIndex($rows, $eventId);

        return $index >= 0 ? (string) ($rows[$index][self::ROW_STATUS] ?? '') : '';
    }

    /**
     * Keep the flat, queryable meta in sync with the history repeater and the
     * vendor type (used for filters, sorting and the Featured Vendors block).
     */
    public static function syncIndexes(int $vendorId): void
    {
        delete_post_meta($vendorId, self::META_SHOW_ID);
        delete_post_meta($vendorId, self::META_ACTIVE_SHOW_ID);

        $latest = 0;
        foreach (self::history($vendorId) as $row) {
            $eventId = (int) ($row[self::ROW_EVENT] ?? 0);
            if (! $eventId) {
                continue;
            }

            add_post_meta($vendorId, self::META_SHOW_ID, $eventId);

            if (($row[self::ROW_STATUS] ?? '') === self::SHOW_ACTIVE) {
                add_post_meta($vendorId, self::META_ACTIVE_SHOW_ID, $eventId);
            }

            $latest = max($latest, self::eventTimestamp($eventId));
        }

        if ($latest) {
            update_post_meta($vendorId, self::META_LAST_SHOW_DATE, wp_date('Y-m-d H:i:s', $latest));
        } else {
            delete_post_meta($vendorId, self::META_LAST_SHOW_DATE);
        }

        $term = self::typeTerm($vendorId);
        update_post_meta($vendorId, self::META_TYPE_NAME, $term ? $term->name : '');
        update_post_meta($vendorId, self::META_EMAIL_KEY, self::normalizeEmail(self::contactEmail($vendorId)));
    }

    /**
     * Shows this vendor can sign up for right now, with the fee for each.
     * Rows already awaiting payment keep their locked-in fee.
     *
     * @return array<int, float> event ID => fee
     */
    public static function payableShows(int $vendorId): array
    {
        $shows = [];
        $rows = self::history($vendorId);
        $type = self::typeSlug($vendorId);
        $booth = (string) get_post_meta($vendorId, self::FIELD_BOOTH_TYPE, true);

        foreach ($rows as $row) {
            $eventId = (int) ($row[self::ROW_EVENT] ?? 0);
            if (($row[self::ROW_STATUS] ?? '') === self::SHOW_AWAITING && self::isClubShow($eventId) && self::showAllowsType($eventId, $type)) {
                $fee = is_numeric($row[self::ROW_FEE] ?? null) ? (float) $row[self::ROW_FEE] : self::showFee($eventId, $booth);
                if ($fee > 0) {
                    $shows[$eventId] = round($fee, 2);
                }
            }
        }

        // Approved vendors can sign up for any open show they are not already in.
        if (self::status($vendorId) === self::STATUS_APPROVED) {
            foreach (self::openShows() as $eventId) {
                if (isset($shows[$eventId]) || self::historyIndex($rows, $eventId) >= 0 || ! self::showAllowsType($eventId, $type)) {
                    continue;
                }

                $fee = self::showFee($eventId, $booth);
                if ($fee > 0) {
                    $shows[$eventId] = $fee;
                }
            }
        }

        return $shows;
    }

    /**
     * Open shows this vendor is excluded from only because of the food rule.
     *
     * @return array<int, int>
     */
    public static function foodBlockedShows(int $vendorId): array
    {
        if (! self::isFoodVendor($vendorId)) {
            return [];
        }

        return array_values(array_filter(self::openShows(), static fn (int $id): bool => ! self::showAllowsFood($id)));
    }

    /* -------------------------------------------------------------------------
     * Private links
     * ---------------------------------------------------------------------- */

    public static function issueToken(int $vendorId): string
    {
        $token = wp_generate_password(40, false, false);
        update_post_meta($vendorId, self::META_TOKEN, $token);
        update_post_meta($vendorId, self::META_TOKEN_EXPIRES, time() + self::TOKEN_TTL);

        return $token;
    }

    public static function tokenIsValid(int $vendorId, string $token): bool
    {
        $stored = (string) get_post_meta($vendorId, self::META_TOKEN, true);
        $expires = (int) get_post_meta($vendorId, self::META_TOKEN_EXPIRES, true);

        return $stored !== '' && $token !== '' && hash_equals($stored, $token) && ($expires === 0 || $expires >= time());
    }

    public static function clearToken(int $vendorId): void
    {
        delete_post_meta($vendorId, self::META_TOKEN);
        delete_post_meta($vendorId, self::META_TOKEN_EXPIRES);
    }

    /** Link to the vendor sign-up/payment page (issues a fresh token). */
    public static function signupUrl(int $vendorId): string
    {
        $base = self::pageUrl('vendor_payment_page');

        return $base === '' ? '' : add_query_arg([
            self::QUERY_VENDOR => $vendorId,
            self::QUERY_TOKEN => self::issueToken($vendorId),
        ], $base);
    }

    /* -------------------------------------------------------------------------
     * Settings (Theme Settings → Vendor Settings)
     * ---------------------------------------------------------------------- */

    public static function option(string $name, string $default = ''): string
    {
        $value = function_exists('get_field') ? get_field($name, 'option') : '';

        return is_scalar($value) && trim((string) $value) !== '' ? (string) $value : $default;
    }

    public static function pageUrl(string $option): string
    {
        $pageId = function_exists('get_field') ? (int) get_field($option, 'option') : 0;

        if ($pageId > 0 && get_post_status($pageId) === 'publish') {
            return (string) get_permalink($pageId);
        }

        // Not chosen in Vendor Settings: use the published page that holds the
        // matching block (Vendor Sign-up block for the vendor page).
        $blocks = [
            'vendor_application_page' => 'acf/vendor-signup',
        ];

        if (isset($blocks[$option])) {
            $found = self::pageWithBlock($blocks[$option]);

            return $found ? (string) get_permalink($found) : '';
        }

        return '';
    }

    /** First published page containing a block (a found page is cached for an hour). */
    public static function pageWithBlock(string $blockName): int
    {
        $cacheKey = 'sccc_page_block_'.md5($blockName);
        $cached = get_transient($cacheKey);

        if ($cached !== false) {
            $id = (int) $cached;

            return $id > 0 && get_post_status($id) === 'publish' ? $id : 0;
        }

        global $wpdb;
        $id = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'page' AND post_status = 'publish' AND post_content LIKE %s ORDER BY menu_order ASC, ID ASC LIMIT 1",
            '%<!-- wp:'.$wpdb->esc_like($blockName).' %'
        ));

        if ($id > 0) {
            set_transient($cacheKey, (string) $id, HOUR_IN_SECONDS);
        }

        return $id;
    }

    public static function staffEmail(): string
    {
        $email = sanitize_email(self::option('vendor_staff_email'));

        return is_email($email) ? $email : (string) get_option('admin_email');
    }

    public static function foodMessage(int $eventId): string
    {
        $template = self::option(
            'vendor_food_message',
            'Sorry — the {show} venue doesn\'t allow food vendors, so we can\'t offer you a spot this time. We\'d love to have you back at a future show and will let you know when applications open.'
        );

        return str_replace('{show}', self::eventTitle($eventId), $template);
    }

    public static function closedMessage(): string
    {
        return self::option('vendor_applications_closed_message', 'Vendor applications are currently closed. Check back soon for our next show.');
    }

    /* -------------------------------------------------------------------------
     * Gravity Forms helpers
     * ---------------------------------------------------------------------- */

    /** @param array<string, mixed>|mixed $form */
    public static function formHasClass($form, string $class): bool
    {
        if (! is_array($form)) {
            return false;
        }

        $classes = preg_split('/\s+/', (string) ($form['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return in_array($class, $classes, true);
    }

    /**
     * @param  array<string, mixed>  $form
     * @return object|null GF_Field
     */
    public static function field(array $form, string $adminLabel)
    {
        foreach ((array) ($form['fields'] ?? []) as $field) {
            if (is_object($field) && (string) ($field->adminLabel ?? '') === $adminLabel) {
                return $field;
            }
        }

        return null;
    }

    /**
     * Value of a field (by Admin Field Label) in an entry. Multi-input fields
     * return their non-empty inputs joined with commas unless $input is given.
     *
     * @param  array<string, mixed>  $form
     * @param  array<string, mixed>  $entry
     */
    public static function entryValue(array $form, array $entry, string $adminLabel, string $input = ''): string
    {
        $field = self::field($form, $adminLabel);

        if (! $field) {
            return '';
        }

        if ($input !== '') {
            return trim((string) rgar($entry, $field->id.'.'.$input));
        }

        if (! empty($field->inputs) && is_array($field->inputs)) {
            $values = [];
            foreach ($field->inputs as $item) {
                $value = trim((string) rgar($entry, (string) $item['id']));
                if ($value !== '') {
                    $values[] = $value;
                }
            }

            return implode(',', $values);
        }

        return trim((string) rgar($entry, (string) $field->id));
    }

    /** Posted value for a field during validation (before an entry exists). */
    public static function postedValue(array $form, string $adminLabel): string
    {
        $field = self::field($form, $adminLabel);

        return $field ? trim((string) rgpost('input_'.$field->id)) : '';
    }

    /* -------------------------------------------------------------------------
     * Email
     * ---------------------------------------------------------------------- */

    /**
     * @param  array<int, array{label: string, url: string}>  $buttons
     */
    public static function mail(string $to, string $subject, string $bodyHtml, array $buttons = [], string $replyTo = ''): bool
    {
        if (! is_email($to)) {
            return false;
        }

        $logo = class_exists(\App\Support\Admin\WordPressBranding::class) ? \App\Support\Admin\WordPressBranding::logoUrl() : '';

        $buttonHtml = '';
        foreach ($buttons as $button) {
            $buttonHtml .= sprintf(
                '<p style="margin:28px 0;"><a href="%s" style="display:inline-block;background:#135bec;color:#ffffff;text-decoration:none;font-weight:600;padding:14px 28px;border-radius:8px;">%s</a></p>',
                esc_url($button['url']),
                esc_html($button['label'])
            );
        }

        $html = '<!doctype html><html><body style="margin:0;padding:24px;background:#f6f6f8;font-family:Arial,Helvetica,sans-serif;color:#0f172a;">'
            .'<div style="max-width:600px;margin:0 auto;background:#ffffff;border-radius:12px;padding:32px;line-height:1.6;font-size:16px;">'
            .($logo ? '<p style="text-align:center;margin:0 0 24px;"><img src="'.esc_url($logo).'" alt="'.esc_attr(get_bloginfo('name')).'" style="max-width:180px;height:auto;"></p>' : '')
            .$bodyHtml
            .$buttonHtml
            .'<p style="margin-top:32px;color:#64748b;font-size:13px;">'.esc_html(get_bloginfo('name')).' · '.esc_html(home_url('/')).'</p>'
            .'</div></body></html>';

        $headers = ['Content-Type: text/html; charset=UTF-8'];
        $replyTo = $replyTo !== '' ? $replyTo : self::staffEmail();
        if (is_email($replyTo)) {
            $headers[] = 'Reply-To: '.$replyTo;
        }

        return (bool) wp_mail($to, $subject, $html, $headers);
    }

    public static function greeting(int $vendorId): string
    {
        return '<p>'.esc_html(sprintf(__('Hi %s,', 'sccc'), self::contactFirstName($vendorId) ?: self::businessName($vendorId))).'</p>';
    }
}
