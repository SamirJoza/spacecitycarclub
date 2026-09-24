<?php

/**
 * File path + filename: app/Support/Vendors/Vendors.php
 *
 * Purpose:
 * - Single source of truth for the Vendor application feature: post type and
 *   taxonomy slugs, statuses, meta keys, and shared helper methods used by the
 *   post type, Gravity Forms intake, approval and payment modules.
 *
 * Why this file exists:
 * - The vendor workflow spans several files (CPT, ACF fields, two Gravity
 *   Forms, approval emails, Stripe payment). Keeping every key and rule here
 *   prevents the modules from drifting apart.
 *
 * Workflow (one Vendor post = one application for one show):
 *   pending  → vendor applied, waiting for manual review
 *   approved → staff approved; payment email with a private link was sent
 *   paid     → vendor paid the fee through the Gravity Forms Stripe form
 *   declined → staff declined (e.g. venue does not allow food vendors)
 *   cancelled→ withdrawn / cancelled after the fact
 *
 * Fee rule:
 * - Each show (MagePeople event) has a default vendor fee (ACF on the event).
 * - Staff can override the fee per application before/at approval.
 * - The resolved fee is locked into `_vendor_fee_due` while the application is
 *   approved, and the payment form always charges that amount server-side.
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

    /** Term slug that marks food vendors (checked against the show's "food allowed" flag). */
    public const FOOD_TYPE_SLUG = 'food-beverage';

    /* -------------------------------------------------------------------------
     * Statuses
     * ---------------------------------------------------------------------- */

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_PAID = 'paid';

    public const STATUS_DECLINED = 'declined';

    public const STATUS_CANCELLED = 'cancelled';

    /* -------------------------------------------------------------------------
     * ACF field names (editable in the Vendor edit screen)
     * ---------------------------------------------------------------------- */

    public const FIELD_STATUS = 'vendor_status';

    public const FIELD_FEE_OVERRIDE = 'vendor_fee';

    public const FIELD_EVENT = 'vendor_event';

    public const FIELD_DESCRIPTION = 'vendor_description';

    public const FIELD_WEBSITE = 'vendor_website';

    public const FIELD_SOCIAL = 'vendor_social';

    public const FIELD_FIRST_NAME = 'vendor_contact_first_name';

    public const FIELD_LAST_NAME = 'vendor_contact_last_name';

    public const FIELD_EMAIL = 'vendor_contact_email';

    public const FIELD_PHONE = 'vendor_contact_phone';

    public const FIELD_NEEDS_POWER = 'vendor_needs_power';

    public const FIELD_NOTES = 'vendor_notes';

    public const FIELD_DECLINE_REASON = 'vendor_decline_reason';

    public const FIELD_SEND_EMAIL = 'vendor_send_status_email';

    public const FIELD_ADMIN_NOTES = 'vendor_admin_notes';

    /* -------------------------------------------------------------------------
     * ACF field names on the show (MagePeople event)
     * ---------------------------------------------------------------------- */

    public const EVENT_FIELD_ACCEPTING = 'vendors_accepting';

    public const EVENT_FIELD_FEE = 'vendor_fee_default';

    public const EVENT_FIELD_FOOD_ALLOWED = 'vendors_food_allowed';

    /* -------------------------------------------------------------------------
     * Internal (non-ACF) post meta — written by code only
     * ---------------------------------------------------------------------- */

    public const META_ENTRY_ID = '_vendor_gf_entry_id';

    public const META_FORM_ID = '_vendor_gf_form_id';

    public const META_MARKETING_OPTIN = '_vendor_marketing_optin';

    public const META_REFERRAL_SOURCE = '_vendor_referral_source';

    public const META_FEE_DUE = '_vendor_fee_due';

    public const META_TOKEN = '_vendor_payment_token';

    public const META_APPROVED_AT = '_vendor_approved_at';

    public const META_PAYMENT_EMAILED_AT = '_vendor_payment_emailed_at';

    public const META_PAID_AT = '_vendor_paid_at';

    public const META_PAID_AMOUNT = '_vendor_paid_amount';

    public const META_TRANSACTION_ID = '_vendor_transaction_id';

    public const META_PAYMENT_ENTRY_ID = '_vendor_payment_entry_id';

    public const META_PAYMENT_FORM_ID = '_vendor_payment_form_id';

    /* -------------------------------------------------------------------------
     * Gravity Forms identification
     *
     * Forms are recognised by their CSS class (Form Settings → CSS Class Name)
     * so the code keeps working no matter which form ID the import receives.
     * Fields are recognised by their Admin Field Label.
     * ---------------------------------------------------------------------- */

    public const APPLICATION_FORM_CLASS = 'sccc-vendor-application';

    public const PAYMENT_FORM_CLASS = 'sccc-vendor-payment';

    /** URL query parameters used by the private payment link. */
    public const QUERY_APPLICATION = 'vendor_application';

    public const QUERY_TOKEN = 'vendor_token';

    /**
     * Status slug → human label.
     *
     * @return array<string, string>
     */
    public static function statuses(): array
    {
        return [
            self::STATUS_PENDING => __('Pending review', 'sccc'),
            self::STATUS_APPROVED => __('Approved — awaiting payment', 'sccc'),
            self::STATUS_PAID => __('Paid — confirmed', 'sccc'),
            self::STATUS_DECLINED => __('Declined', 'sccc'),
            self::STATUS_CANCELLED => __('Cancelled', 'sccc'),
        ];
    }

    /**
     * Default vendor type terms (slug → name). Slugs must match the choice
     * values in the Gravity Forms application form JSON.
     *
     * @return array<string, string>
     */
    public static function defaultTypes(): array
    {
        return [
            self::FOOD_TYPE_SLUG => 'Food & Beverage',
            'automotive-parts' => 'Automotive Parts & Accessories',
            'detailing-car-care' => 'Detailing & Car Care',
            'automotive-services' => 'Automotive Services',
            'apparel-merchandise' => 'Apparel & Merchandise',
            'art-photography' => 'Art & Photography',
            'other' => 'Other',
        ];
    }

    public static function status(int $postId): string
    {
        $status = (string) get_post_meta($postId, self::FIELD_STATUS, true);

        return array_key_exists($status, self::statuses()) ? $status : self::STATUS_PENDING;
    }

    public static function statusLabel(string $status): string
    {
        return self::statuses()[$status] ?? ucfirst($status);
    }

    public static function isVendor($post): bool
    {
        $post = get_post($post);

        return $post instanceof WP_Post && $post->post_type === self::POST_TYPE;
    }

    /* -------------------------------------------------------------------------
     * Show (event) helpers
     * ---------------------------------------------------------------------- */

    public static function eventId(int $vendorId): int
    {
        return (int) get_post_meta($vendorId, self::FIELD_EVENT, true);
    }

    /**
     * Upcoming published shows that currently accept vendor applications.
     *
     * @return array<int, WP_Post>
     */
    public static function openShows(): array
    {
        $now = current_time('Y-m-d H:i:s');
        $ids = [];

        foreach (['event_upcoming_datetime', 'event_start_datetime'] as $metaKey) {
            $ids = array_merge($ids, get_posts([
                'post_type' => self::EVENT_POST_TYPE,
                'post_status' => 'publish',
                'posts_per_page' => 100,
                'fields' => 'ids',
                'no_found_rows' => true,
                'meta_query' => [
                    [
                        'key' => $metaKey,
                        'value' => $now,
                        'compare' => '>=',
                        'type' => 'DATETIME',
                    ],
                ],
            ]));
        }

        $shows = [];
        foreach (array_unique(array_map('intval', $ids)) as $eventId) {
            if (! self::eventAcceptsVendors($eventId)) {
                continue;
            }

            $post = get_post($eventId);
            if ($post instanceof WP_Post) {
                $shows[$eventId] = $post;
            }
        }

        uasort($shows, static fn (WP_Post $a, WP_Post $b): int => self::eventTimestamp($a->ID) <=> self::eventTimestamp($b->ID));

        return $shows;
    }

    public static function eventAcceptsVendors(int $eventId): bool
    {
        if (get_post_type($eventId) !== self::EVENT_POST_TYPE || get_post_status($eventId) !== 'publish') {
            return false;
        }

        return (bool) get_post_meta($eventId, self::EVENT_FIELD_ACCEPTING, true);
    }

    public static function eventFoodAllowed(int $eventId): bool
    {
        $value = get_post_meta($eventId, self::EVENT_FIELD_FOOD_ALLOWED, true);

        // Unset means "not decided yet" → treat as allowed so nothing is flagged by accident.
        return $value === '' || (bool) $value;
    }

    public static function eventDefaultFee(int $eventId): float
    {
        $fee = get_post_meta($eventId, self::EVENT_FIELD_FEE, true);

        return is_numeric($fee) ? max(0.0, (float) $fee) : 0.0;
    }

    public static function eventTimestamp(int $eventId): int
    {
        $raw = (string) (
            get_post_meta($eventId, 'event_upcoming_datetime', true)
            ?: get_post_meta($eventId, 'event_start_datetime', true)
            ?: trim(get_post_meta($eventId, 'event_start_date', true).' '.get_post_meta($eventId, 'event_start_time', true))
        );

        $ts = $raw !== '' ? strtotime($raw) : false;

        return $ts ?: 0;
    }

    /**
     * "Show title — Sat, Oct 12, 2026 · Venue" label used in forms, emails and admin.
     */
    public static function eventLabel(int $eventId, bool $withVenue = true): string
    {
        if ($eventId <= 0 || ! get_post($eventId)) {
            return __('(show not found)', 'sccc');
        }

        $parts = [html_entity_decode(get_the_title($eventId), ENT_QUOTES, 'UTF-8')];
        $ts = self::eventTimestamp($eventId);

        if ($ts) {
            $parts[] = wp_date('D, M j, Y', $ts);
        }

        $label = implode(' — ', $parts);
        $venue = trim((string) get_post_meta($eventId, 'mep_location_venue', true));

        if ($withVenue && $venue !== '') {
            $label .= ' · '.$venue;
        }

        return $label;
    }

    /* -------------------------------------------------------------------------
     * Vendor helpers
     * ---------------------------------------------------------------------- */

    public static function isFoodVendor(int $vendorId): bool
    {
        return has_term(self::FOOD_TYPE_SLUG, self::TAXONOMY_TYPE, $vendorId);
    }

    /** True when a food vendor applied to a show whose venue does not allow food. */
    public static function hasFoodConflict(int $vendorId): bool
    {
        $eventId = self::eventId($vendorId);

        return $eventId > 0 && self::isFoodVendor($vendorId) && ! self::eventFoodAllowed($eventId);
    }

    public static function typeLabel(int $vendorId): string
    {
        $terms = get_the_terms($vendorId, self::TAXONOMY_TYPE);

        return is_array($terms) && $terms ? $terms[0]->name : '—';
    }

    public static function contactName(int $vendorId): string
    {
        return trim(get_post_meta($vendorId, self::FIELD_FIRST_NAME, true).' '.get_post_meta($vendorId, self::FIELD_LAST_NAME, true));
    }

    public static function contactEmail(int $vendorId): string
    {
        return sanitize_email((string) get_post_meta($vendorId, self::FIELD_EMAIL, true));
    }

    public static function businessName(int $vendorId): string
    {
        return html_entity_decode(get_the_title($vendorId), ENT_QUOTES, 'UTF-8');
    }

    /**
     * Fee to charge: the per-application override when set, otherwise the
     * show's default vendor fee.
     */
    public static function resolveFee(int $vendorId): float
    {
        $override = get_post_meta($vendorId, self::FIELD_FEE_OVERRIDE, true);

        if (is_numeric($override) && (float) $override > 0) {
            return round((float) $override, 2);
        }

        return round(self::eventDefaultFee(self::eventId($vendorId)), 2);
    }

    public static function feeDue(int $vendorId): float
    {
        $fee = get_post_meta($vendorId, self::META_FEE_DUE, true);

        return is_numeric($fee) ? round((float) $fee, 2) : 0.0;
    }

    public static function money(float $amount): string
    {
        return '$'.number_format($amount, 2);
    }

    /* -------------------------------------------------------------------------
     * Payment link
     * ---------------------------------------------------------------------- */

    public static function ensureToken(int $vendorId): string
    {
        $token = (string) get_post_meta($vendorId, self::META_TOKEN, true);

        if ($token === '') {
            $token = wp_generate_password(40, false, false);
            update_post_meta($vendorId, self::META_TOKEN, $token);
        }

        return $token;
    }

    public static function tokenIsValid(int $vendorId, string $token): bool
    {
        $stored = (string) get_post_meta($vendorId, self::META_TOKEN, true);

        return $stored !== '' && $token !== '' && hash_equals($stored, $token);
    }

    /** Page that contains the Vendor Payment form (Theme Settings → Vendor Settings). */
    public static function paymentPageUrl(): string
    {
        $pageId = function_exists('get_field') ? (int) get_field('vendor_payment_page', 'option') : 0;

        return $pageId > 0 && get_post_status($pageId) === 'publish' ? (string) get_permalink($pageId) : '';
    }

    public static function paymentUrl(int $vendorId): string
    {
        $base = self::paymentPageUrl();

        if ($base === '') {
            return '';
        }

        return add_query_arg([
            self::QUERY_APPLICATION => $vendorId,
            self::QUERY_TOKEN => self::ensureToken($vendorId),
        ], $base);
    }

    /** Staff notification address (Theme Settings → Vendor Settings), falling back to the site admin email. */
    public static function staffEmail(): string
    {
        $email = function_exists('get_field') ? sanitize_email((string) get_field('vendor_staff_email', 'option')) : '';

        return is_email($email) ? $email : (string) get_option('admin_email');
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
     * Find a form field by its Admin Field Label.
     *
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
     * Entry value for the field with the given Admin Field Label.
     *
     * @param  array<string, mixed>  $form
     * @param  array<string, mixed>  $entry
     */
    public static function entryValue(array $form, array $entry, string $adminLabel): string
    {
        $field = self::field($form, $adminLabel);

        if (! $field) {
            return '';
        }

        if (! empty($field->inputs) && is_array($field->inputs)) {
            $values = [];
            foreach ($field->inputs as $input) {
                $value = trim((string) rgar($entry, (string) $input['id']));
                if ($value !== '') {
                    $values[] = $value;
                }
            }

            return implode(',', $values);
        }

        return trim((string) rgar($entry, (string) $field->id));
    }

    /* -------------------------------------------------------------------------
     * Email
     * ---------------------------------------------------------------------- */

    /**
     * Send a simple branded HTML email.
     *
     * @param  array<int, array{label: string, url: string}>  $buttons
     */
    public static function mail(string $to, string $subject, string $bodyHtml, array $buttons = [], string $replyTo = ''): bool
    {
        if (! is_email($to)) {
            return false;
        }

        $logo = class_exists(\App\Support\Admin\WordPressBranding::class)
            ? \App\Support\Admin\WordPressBranding::logoUrl()
            : '';

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
}
