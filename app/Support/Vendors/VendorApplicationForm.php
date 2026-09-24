<?php

/**
 * File path + filename: app/Support/Vendors/VendorApplicationForm.php
 *
 * Purpose:
 * - Wire the Gravity Forms "Vendor Application" form to the Vendor post type.
 *   1) Fill the "Which shows?" checkbox field with upcoming shows that accept
 *      vendors (see EventVendorSettings on each event).
 *   2) Replace the form with a "closed" message when no show is open.
 *   3) After submission, create one Vendor application (status: pending) per
 *      selected show and add a note to the entry listing what was created.
 *
 * Why this file exists:
 * - Vendors can pick several shows at once, but each show is reviewed, approved
 *   (or declined) and paid separately — e.g. a food vendor may be approved for
 *   one venue and declined at a venue that does not allow food.
 *
 * How the form is recognised (no hard-coded form IDs):
 * - Form Settings → CSS Class Name contains `sccc-vendor-application`.
 * - Fields are matched by their Admin Field Label (see resources/gravity-forms/
 *   vendor-application.json and README-vendors.md in that folder).
 *
 * Not handled here:
 * - Emails on submit → Gravity Forms notifications (in the form JSON).
 * - EmailOctopus → the Gravity Forms EmailOctopus add-on feed.
 */

namespace App\Support\Vendors;

defined('ABSPATH') || exit;

final class VendorApplicationForm
{
    /** Admin Field Labels used by the application form. */
    public const F_BUSINESS = 'vendor_business_name';

    public const F_TYPE = 'vendor_type';

    public const F_DESCRIPTION = 'vendor_description';

    public const F_WEBSITE = 'vendor_website';

    public const F_SOCIAL = 'vendor_social';

    public const F_FIRST_NAME = 'vendor_first_name';

    public const F_LAST_NAME = 'vendor_last_name';

    public const F_EMAIL = 'vendor_email';

    public const F_PHONE = 'vendor_phone';

    public const F_SHOWS = 'vendor_shows';

    public const F_POWER = 'vendor_power';

    public const F_NOTES = 'vendor_notes';

    public const F_MARKETING = 'vendor_marketing_optin';

    public const F_REFERRAL = 'vendor_referral_source';

    public static function register(): void
    {
        // Populate the shows checkbox everywhere Gravity Forms builds the form.
        foreach (['gform_pre_render', 'gform_pre_validation', 'gform_pre_submission_filter', 'gform_admin_pre_render'] as $hook) {
            add_filter($hook, [self::class, 'populateShows']);
        }

        add_filter('gform_get_form_filter', [self::class, 'maybeShowClosedMessage'], 10, 2);
        add_filter('gform_validation', [self::class, 'validateShows']);
        add_action('gform_after_submission', [self::class, 'createApplications'], 10, 2);
    }

    /**
     * @param  array<string, mixed>|mixed  $form
     * @return array<string, mixed>|mixed
     */
    public static function populateShows($form)
    {
        if (! Vendors::formHasClass($form, Vendors::APPLICATION_FORM_CLASS)) {
            return $form;
        }

        $field = Vendors::field($form, self::F_SHOWS);
        if (! $field) {
            return $form;
        }

        $choices = [];
        $inputs = [];
        $index = 0;

        foreach (Vendors::openShows() as $eventId => $post) {
            $index++;
            if ($index % 10 === 0) {
                $index++; // Gravity Forms skips input IDs ending in 0 (e.g. 8.10).
            }

            $label = Vendors::eventLabel((int) $eventId);
            $choices[] = ['text' => $label, 'value' => (string) $eventId, 'isSelected' => false, 'price' => ''];
            $inputs[] = ['id' => $field->id.'.'.$index, 'label' => $label, 'name' => ''];
        }

        if ($choices) {
            $field->choices = $choices;
            $field->inputs = $inputs;
        }

        return $form;
    }

    /**
     * Show a friendly message instead of the form when no show is open.
     *
     * @param  array<string, mixed>|mixed  $form
     */
    public static function maybeShowClosedMessage(string $formString, $form): string
    {
        if (! Vendors::formHasClass($form, Vendors::APPLICATION_FORM_CLASS) || is_admin()) {
            return $formString;
        }

        if (Vendors::openShows()) {
            return $formString;
        }

        $message = function_exists('get_field') ? (string) get_field('vendor_applications_closed_message', 'option') : '';
        $message = $message !== '' ? $message : __('Vendor applications are currently closed. Check back soon for upcoming shows.', 'sccc');

        return '<div class="sccc-vendor-closed gform_confirmation_message">'.esc_html($message).'</div>';
    }

    /**
     * Server-side check: every selected show must still be open to vendors.
     *
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    public static function validateShows(array $result): array
    {
        $form = $result['form'] ?? null;

        if (! Vendors::formHasClass($form, Vendors::APPLICATION_FORM_CLASS)) {
            return $result;
        }

        $field = Vendors::field($form, self::F_SHOWS);
        if (! $field) {
            return $result;
        }

        $selected = self::postedShowIds($field);
        $invalid = array_filter($selected, static fn (int $id): bool => ! Vendors::eventAcceptsVendors($id));

        if (! $selected || $invalid) {
            $field->failed_validation = true;
            $field->validation_message = $selected
                ? __('One of the selected shows is no longer accepting vendors. Please review your selection.', 'sccc')
                : __('Please choose at least one show.', 'sccc');
            $result['is_valid'] = false;
        }

        $result['form'] = $form;

        return $result;
    }

    /**
     * Create one pending Vendor application per selected show.
     *
     * @param  array<string, mixed>  $entry
     * @param  array<string, mixed>  $form
     */
    public static function createApplications($entry, $form): void
    {
        if (! is_array($entry) || ! Vendors::formHasClass($form, Vendors::APPLICATION_FORM_CLASS)) {
            return;
        }

        $business = sanitize_text_field(Vendors::entryValue($form, $entry, self::F_BUSINESS));
        $email = sanitize_email(Vendors::entryValue($form, $entry, self::F_EMAIL));
        $showIds = array_filter(array_map('intval', explode(',', Vendors::entryValue($form, $entry, self::F_SHOWS))));

        if ($business === '' || ! is_email($email) || ! $showIds) {
            return;
        }

        $typeSlug = sanitize_title(Vendors::entryValue($form, $entry, self::F_TYPE));
        $term = $typeSlug !== '' ? get_term_by('slug', $typeSlug, Vendors::TAXONOMY_TYPE) : false;
        $marketing = Vendors::entryValue($form, $entry, self::F_MARKETING) !== '' ? '1' : '0';
        $power = strtolower(Vendors::entryValue($form, $entry, self::F_POWER)) === 'yes' ? 'yes' : 'no';

        $created = [];
        $skipped = [];

        foreach (array_unique($showIds) as $eventId) {
            if (! Vendors::eventAcceptsVendors($eventId)) {
                $skipped[] = Vendors::eventLabel($eventId, false).' ('.__('not open', 'sccc').')';
                continue;
            }

            if (self::hasActiveApplication($email, $eventId)) {
                $skipped[] = Vendors::eventLabel($eventId, false).' ('.__('already applied', 'sccc').')';
                continue;
            }

            $postId = wp_insert_post([
                'post_type' => Vendors::POST_TYPE,
                'post_status' => 'publish',
                'post_title' => $business,
            ], true);

            if (is_wp_error($postId) || ! $postId) {
                continue;
            }

            $postId = (int) $postId;

            if ($term && ! is_wp_error($term)) {
                wp_set_object_terms($postId, (int) $term->term_id, Vendors::TAXONOMY_TYPE);
                self::update('field_vendor_type_term', (int) $term->term_id, $postId);
            }

            self::update('field_vendor_description', sanitize_textarea_field(Vendors::entryValue($form, $entry, self::F_DESCRIPTION)), $postId);
            self::update('field_vendor_website', esc_url_raw(Vendors::entryValue($form, $entry, self::F_WEBSITE)), $postId);
            self::update('field_vendor_social', sanitize_text_field(Vendors::entryValue($form, $entry, self::F_SOCIAL)), $postId);
            self::update('field_vendor_contact_first_name', sanitize_text_field(Vendors::entryValue($form, $entry, self::F_FIRST_NAME)), $postId);
            self::update('field_vendor_contact_last_name', sanitize_text_field(Vendors::entryValue($form, $entry, self::F_LAST_NAME)), $postId);
            self::update('field_vendor_contact_email', $email, $postId);
            self::update('field_vendor_contact_phone', sanitize_text_field(Vendors::entryValue($form, $entry, self::F_PHONE)), $postId);
            self::update('field_vendor_event', $eventId, $postId);
            self::update('field_vendor_needs_power', $power, $postId);
            self::update('field_vendor_notes', sanitize_textarea_field(Vendors::entryValue($form, $entry, self::F_NOTES)), $postId);
            self::update('field_vendor_status', Vendors::STATUS_PENDING, $postId);
            self::update('field_vendor_send_status_email', 1, $postId);

            update_post_meta($postId, Vendors::META_ENTRY_ID, (int) rgar($entry, 'id'));
            update_post_meta($postId, Vendors::META_FORM_ID, (int) rgar($form, 'id'));
            update_post_meta($postId, Vendors::META_MARKETING_OPTIN, $marketing);
            update_post_meta($postId, Vendors::META_REFERRAL_SOURCE, sanitize_text_field(Vendors::entryValue($form, $entry, self::F_REFERRAL)) ?: 'Direct');

            $created[] = Vendors::eventLabel($eventId, false).' → #'.$postId;
        }

        if (class_exists('GFAPI')) {
            $note = $created
                ? __('Vendor applications created:', 'sccc')."\n".implode("\n", $created)
                : __('No vendor applications were created.', 'sccc');

            if ($skipped) {
                $note .= "\n".__('Skipped:', 'sccc')."\n".implode("\n", $skipped);
            }

            \GFAPI::add_note((int) rgar($entry, 'id'), 0, 'SCCC Vendors', $note);
        }
    }

    /* -------------------------------------------------------------------------
     * Helpers
     * ---------------------------------------------------------------------- */

    /**
     * Event IDs checked in the posted form.
     *
     * @return array<int, int>
     */
    private static function postedShowIds(object $field): array
    {
        $ids = [];

        foreach ((array) ($field->inputs ?? []) as $input) {
            $key = 'input_'.str_replace('.', '_', (string) $input['id']);
            $value = isset($_POST[$key]) ? absint(wp_unslash($_POST[$key])) : 0; // phpcs:ignore WordPress.Security.NonceVerification -- Gravity Forms handles the submission nonce.
            if ($value > 0) {
                $ids[] = $value;
            }
        }

        return array_values(array_unique($ids));
    }

    /** Same email already has a pending/approved/paid application for this show. */
    private static function hasActiveApplication(string $email, int $eventId): bool
    {
        $existing = get_posts([
            'post_type' => Vendors::POST_TYPE,
            'post_status' => 'any',
            'posts_per_page' => 1,
            'fields' => 'ids',
            'no_found_rows' => true,
            'meta_query' => [
                ['key' => Vendors::FIELD_EMAIL, 'value' => $email],
                ['key' => Vendors::FIELD_EVENT, 'value' => $eventId],
                ['key' => Vendors::FIELD_STATUS, 'value' => [Vendors::STATUS_PENDING, Vendors::STATUS_APPROVED, Vendors::STATUS_PAID], 'compare' => 'IN'],
            ],
        ]);

        return (bool) $existing;
    }

    /** Write through ACF when available so field reference keys are stored too. */
    private static function update(string $fieldKey, $value, int $postId): void
    {
        if (function_exists('update_field')) {
            update_field($fieldKey, $value, $postId);

            return;
        }

        $name = substr($fieldKey, strlen('field_'));
        update_post_meta($postId, $name === 'vendor_type_term' ? 'vendor_type_term' : $name, $value);
    }
}
