<?php

/**
 * File path + filename: app/Support/Vendors/VendorApplicationForm.php
 *
 * Purpose:
 * - Wire the Gravity Forms "Vendor Application" (new vendors, 4 pages) to the
 *   Vendor post type:
 *   1) Fill the "Which show?" field with open club shows.
 *   2) Validate: one vendor per email (returning vendors are pointed to the
 *      returning-vendor option), the show must still be open, and the food
 *      rule — food vendors cannot apply to a show that doesn't allow food.
 *   3) After submission, create the Vendor (status: pending), copy every
 *      answer into its fields, move the logo/photos into the Media Library,
 *      and add a "pending" row for the chosen show to the show history.
 *
 * How the form is recognised:
 * - CSS class `sccc-vendor-application`; fields by Admin Field Label (the F_*
 *   constants below, used by VendorForms when it builds the form).
 */

namespace App\Support\Vendors;

defined('ABSPATH') || exit;

final class VendorApplicationForm
{
    public const F_BUSINESS = 'vendor_business_name';

    public const F_DISPLAY_NAME = 'vendor_display_name';

    public const F_TYPE = 'vendor_type';

    public const F_BLURB = 'vendor_blurb';

    public const F_OFFERINGS = 'vendor_offerings';

    public const F_WEBSITE = 'vendor_website';

    public const F_INSTAGRAM = 'vendor_instagram';

    public const F_FACEBOOK = 'vendor_facebook';

    public const F_TIKTOK = 'vendor_tiktok';

    public const F_LOGO = 'vendor_logo';

    public const F_PHOTOS = 'vendor_photos';

    public const F_FIRST_NAME = 'vendor_first_name';

    public const F_LAST_NAME = 'vendor_last_name';

    public const F_ROLE = 'vendor_role';

    public const F_EMAIL = 'vendor_email';

    public const F_PHONE = 'vendor_phone';

    public const F_TEXT_OK = 'vendor_text_ok';

    public const F_ADDRESS = 'vendor_address';

    public const F_DAYOF_NAME = 'vendor_dayof_name';

    public const F_DAYOF_PHONE = 'vendor_dayof_phone';

    public const F_SHOW = 'vendor_show';

    public const F_BOOTH = 'vendor_booth_type';

    public const F_TRAILER = 'vendor_trailer_length';

    public const F_EQUIPMENT = 'vendor_equipment';

    public const F_POWER = 'vendor_power';

    public const F_GENERATOR = 'vendor_generator';

    public const F_OPEN_FLAME = 'vendor_open_flame';

    public const F_STAFF = 'vendor_staff_count';

    public const F_NOTES = 'vendor_notes';

    public const F_FEATURE_OK = 'vendor_feature_ok';

    public const F_OFFER = 'vendor_member_offer';

    public const F_HEARD = 'vendor_heard_about';

    public const F_MARKETING = 'vendor_marketing_optin';

    public const F_AGREEMENT = 'vendor_agreement';

    public const F_SIGNATURE = 'vendor_signature';

    public const F_REFERRAL = 'vendor_referral_source';

    public static function register(): void
    {
        foreach (['gform_pre_render', 'gform_pre_validation', 'gform_pre_submission_filter', 'gform_admin_pre_render'] as $hook) {
            add_filter($hook, [self::class, 'populateShows']);
        }

        add_filter('gform_get_form_filter', [self::class, 'maybeShowClosedMessage'], 10, 2);
        add_filter('gform_validation', [self::class, 'validate']);
        add_action('gform_after_submission', [self::class, 'createVendor'], 10, 2);
    }

    /* -------------------------------------------------------------------------
     * Rendering
     * ---------------------------------------------------------------------- */

    /**
     * @param  array<string, mixed>|mixed  $form
     * @return array<string, mixed>|mixed
     */
    public static function populateShows($form)
    {
        if (! Vendors::formHasClass($form, Vendors::APPLICATION_FORM_CLASS)) {
            return $form;
        }

        $field = Vendors::field($form, self::F_SHOW);
        if (! $field) {
            return $form;
        }

        // Links from a car-show event page pre-select that show (?vendor_show=ID).
        $preselect = isset($_GET['vendor_show']) ? absint($_GET['vendor_show']) : 0; // phpcs:ignore WordPress.Security.NonceVerification

        $choices = [];
        foreach (Vendors::openShows() as $eventId) {
            $label = Vendors::eventLabel($eventId);
            $fee = Vendors::showFee($eventId);

            if ((string) get_post_meta($eventId, Vendors::EVENT_PRICING_MODE, true) !== 'booth' && $fee > 0) {
                $label .= ' — '.sprintf(__('vendor fee $%s', 'sccc'), number_format($fee, 2));
            }

            if (! Vendors::showAllowsFood($eventId)) {
                $label .= ' '.__('(no food vendors)', 'sccc');
            }

            $choices[] = ['text' => $label, 'value' => (string) $eventId, 'isSelected' => $preselect === $eventId, 'price' => ''];
        }

        if ($choices) {
            if (count($choices) === 1) {
                $choices[0]['isSelected'] = true;
            }
            $field->choices = $choices;
        }

        return $form;
    }

    /**
     * @param  array<string, mixed>|mixed  $form
     */
    public static function maybeShowClosedMessage(string $formString, $form): string
    {
        if (is_admin() || ! Vendors::formHasClass($form, Vendors::APPLICATION_FORM_CLASS) || Vendors::openShows()) {
            return $formString;
        }

        return '<div class="sccc-vendor-message gform_confirmation_message">'.esc_html(Vendors::closedMessage()).'</div>';
    }

    /* -------------------------------------------------------------------------
     * Validation (runs per page on multi-page forms)
     * ---------------------------------------------------------------------- */

    /**
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    public static function validate(array $result): array
    {
        $form = $result['form'] ?? null;

        if (! Vendors::formHasClass($form, Vendors::APPLICATION_FORM_CLASS)) {
            return $result;
        }

        $type = sanitize_title(Vendors::postedValue($form, self::F_TYPE));

        // Page 1: a food vendor when no open show allows food → stop right away.
        $typeField = Vendors::field($form, self::F_TYPE);
        if ($typeField && self::isOnCurrentPage($form, $typeField) && $type === Vendors::FOOD_TYPE_SLUG) {
            $open = Vendors::openShows();
            $foodShows = array_filter($open, [Vendors::class, 'showAllowsFood']);

            if ($open && ! $foodShows) {
                self::fail($result, $typeField, Vendors::foodMessage((int) $open[0]));
            }
        }

        // Page 2: one vendor record per email address.
        $emailField = Vendors::field($form, self::F_EMAIL);
        if ($emailField && self::isOnCurrentPage($form, $emailField)) {
            $email = Vendors::postedValue($form, self::F_EMAIL);
            if ($email !== '' && Vendors::findByEmail($email)) {
                self::fail($result, $emailField, __('We already have a vendor record for this email address. Please go back and choose "I\'ve been a vendor with you before" — no need to fill out the full application again.', 'sccc'));
            }
        }

        // Page 3: the show must be open, and the food rule must pass.
        $showField = Vendors::field($form, self::F_SHOW);
        if ($showField && self::isOnCurrentPage($form, $showField)) {
            $eventId = absint(Vendors::postedValue($form, self::F_SHOW));

            if (! $eventId || ! Vendors::showIsOpen($eventId)) {
                self::fail($result, $showField, __('Please choose a show that is open for vendors.', 'sccc'));
            } elseif (! Vendors::showAllowsType($eventId, $type)) {
                self::fail($result, $showField, Vendors::foodMessage($eventId));
            }
        }

        $result['form'] = $form;

        return $result;
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private static function fail(array &$result, object $field, string $message): void
    {
        $field->failed_validation = true;
        $field->validation_message = $message;
        $result['is_valid'] = false;
    }

    /**
     * @param  array<string, mixed>  $form
     */
    private static function isOnCurrentPage(array $form, object $field): bool
    {
        $page = class_exists('GFFormDisplay') ? (int) \GFFormDisplay::get_source_page((int) $form['id']) : 0;

        // 0 = final submission (all pages validated together).
        return $page === 0 || (int) ($field->pageNumber ?? 1) === $page;
    }

    /* -------------------------------------------------------------------------
     * Create the vendor
     * ---------------------------------------------------------------------- */

    /**
     * @param  array<string, mixed>  $entry
     * @param  array<string, mixed>  $form
     */
    public static function createVendor($entry, $form): void
    {
        if (! is_array($entry) || ! is_array($form) || ! Vendors::formHasClass($form, Vendors::APPLICATION_FORM_CLASS)) {
            return;
        }

        $v = static fn (string $label, string $input = ''): string => Vendors::entryValue($form, $entry, $label, $input);

        $email = sanitize_email($v(self::F_EMAIL));
        $business = sanitize_text_field($v(self::F_BUSINESS));
        $eventId = absint($v(self::F_SHOW));

        if (! is_email($email) || $business === '') {
            return;
        }

        $vendorId = Vendors::findByEmail($email);
        $isNew = ! $vendorId;

        if ($isNew) {
            $vendorId = (int) wp_insert_post([
                'post_type' => Vendors::POST_TYPE,
                'post_status' => 'publish',
                'post_title' => $business,
            ]);

            if (! $vendorId) {
                return;
            }
        }

        $set = static function (string $name, $value) use ($vendorId): void {
            if (function_exists('update_field')) {
                update_field('field_'.$name, $value, $vendorId);
            } else {
                update_post_meta($vendorId, $name, $value);
            }
        };

        $type = get_term_by('slug', sanitize_title($v(self::F_TYPE)), Vendors::TAXONOMY_TYPE);
        if ($type && ! is_wp_error($type)) {
            wp_set_object_terms($vendorId, (int) $type->term_id, Vendors::TAXONOMY_TYPE);
            $set(Vendors::FIELD_TYPE, (int) $type->term_id);
        }

        $set(Vendors::FIELD_DISPLAY_NAME, sanitize_text_field($v(self::F_DISPLAY_NAME)));
        $set(Vendors::FIELD_BLURB, sanitize_textarea_field($v(self::F_BLURB)));
        $set(Vendors::FIELD_OFFERINGS, sanitize_textarea_field($v(self::F_OFFERINGS)));
        $set(Vendors::FIELD_WEBSITE, esc_url_raw($v(self::F_WEBSITE)));
        $set(Vendors::FIELD_INSTAGRAM, sanitize_text_field($v(self::F_INSTAGRAM)));
        $set(Vendors::FIELD_FACEBOOK, sanitize_text_field($v(self::F_FACEBOOK)));
        $set(Vendors::FIELD_TIKTOK, sanitize_text_field($v(self::F_TIKTOK)));

        $set(Vendors::FIELD_FIRST_NAME, sanitize_text_field($v(self::F_FIRST_NAME)));
        $set(Vendors::FIELD_LAST_NAME, sanitize_text_field($v(self::F_LAST_NAME)));
        $set(Vendors::FIELD_ROLE, sanitize_text_field($v(self::F_ROLE)));
        $set(Vendors::FIELD_EMAIL, $email);
        $set(Vendors::FIELD_PHONE, sanitize_text_field($v(self::F_PHONE)));
        $set(Vendors::FIELD_TEXT_OK, $v(self::F_TEXT_OK) !== '' ? 1 : 0);
        $set(Vendors::FIELD_ADDRESS, trim(sanitize_text_field($v(self::F_ADDRESS, '1')).' '.sanitize_text_field($v(self::F_ADDRESS, '2'))));
        $set(Vendors::FIELD_CITY, sanitize_text_field($v(self::F_ADDRESS, '3')));
        $set(Vendors::FIELD_STATE, sanitize_text_field($v(self::F_ADDRESS, '4')));
        $set(Vendors::FIELD_ZIP, sanitize_text_field($v(self::F_ADDRESS, '5')));
        $set(Vendors::FIELD_DAYOF_NAME, sanitize_text_field($v(self::F_DAYOF_NAME)));
        $set(Vendors::FIELD_DAYOF_PHONE, sanitize_text_field($v(self::F_DAYOF_PHONE)));

        $booth = sanitize_key($v(self::F_BOOTH));
        $set(Vendors::FIELD_BOOTH_TYPE, array_key_exists($booth, Vendors::boothTypes()) ? $booth : '');
        $set(Vendors::FIELD_TRAILER_LENGTH, sanitize_text_field($v(self::F_TRAILER)));
        $set(Vendors::FIELD_EQUIPMENT, array_values(array_intersect(explode(',', $v(self::F_EQUIPMENT)), ['tent', 'tables', 'chairs'])));
        $set(Vendors::FIELD_POWER, $v(self::F_POWER) === 'yes' ? 'yes' : 'no');
        $set(Vendors::FIELD_GENERATOR, $v(self::F_GENERATOR) === 'yes' ? 'yes' : 'no');
        $set(Vendors::FIELD_OPEN_FLAME, $v(self::F_OPEN_FLAME) === 'yes' ? 'yes' : 'no');
        $set(Vendors::FIELD_STAFF_COUNT, absint($v(self::F_STAFF)));
        $set(Vendors::FIELD_NOTES, sanitize_textarea_field($v(self::F_NOTES)));

        $set(Vendors::FIELD_FEATURE_OK, $v(self::F_FEATURE_OK) === 'yes' ? 1 : 0);
        $set(Vendors::FIELD_OFFER, sanitize_text_field($v(self::F_OFFER)));

        if ($isNew) {
            $set(Vendors::FIELD_STATUS, Vendors::STATUS_PENDING);
            $set(Vendors::FIELD_SEND_EMAIL, 1);
        }

        // Logo + product photos → Media Library, attached to the vendor.
        $logoId = self::sideload(self::uploadedUrls($v(self::F_LOGO))[0] ?? '', $vendorId, $business.' logo');
        if ($logoId) {
            $set(Vendors::FIELD_LOGO, $logoId);
            set_post_thumbnail($vendorId, $logoId);
        }

        $photoIds = [];
        foreach (array_slice(self::uploadedUrls($v(self::F_PHOTOS)), 0, 3) as $url) {
            $photoId = self::sideload($url, $vendorId, $business.' photo');
            if ($photoId) {
                $photoIds[] = $photoId;
            }
        }
        if ($photoIds) {
            $set(Vendors::FIELD_PHOTOS, $photoIds);
        }

        update_post_meta($vendorId, Vendors::META_ENTRY_ID, (int) rgar($entry, 'id'));
        update_post_meta($vendorId, Vendors::META_FORM_ID, (int) rgar($form, 'id'));
        update_post_meta($vendorId, Vendors::META_MARKETING_OPTIN, $v(self::F_MARKETING) !== '' ? '1' : '0');
        update_post_meta($vendorId, Vendors::META_REFERRAL_SOURCE, sanitize_text_field($v(self::F_REFERRAL)) ?: 'Direct');
        update_post_meta($vendorId, Vendors::META_HEARD_ABOUT, sanitize_text_field($v(self::F_HEARD)));
        update_post_meta($vendorId, Vendors::META_SIGNATURE, sanitize_text_field($v(self::F_SIGNATURE)).' — '.current_time('M j, Y g:i a'));

        // Show history: pending row for the chosen show.
        if ($eventId && Vendors::isClubShow($eventId)) {
            $rows = Vendors::history($vendorId);
            if (Vendors::historyIndex($rows, $eventId) < 0) {
                $rows[] = [
                    Vendors::ROW_EVENT => $eventId,
                    Vendors::ROW_STATUS => Vendors::SHOW_PENDING,
                    Vendors::ROW_FEE => Vendors::showFee($eventId, $booth),
                ];
            }
            Vendors::saveHistory($vendorId, $rows);
        } else {
            Vendors::syncIndexes($vendorId);
        }

        if (class_exists('GFAPI')) {
            \GFAPI::add_note((int) rgar($entry, 'id'), 0, 'SCCC Vendors', sprintf(
                $isNew ? __('Vendor #%1$d created (pending review) for %2$s.', 'sccc') : __('Existing vendor #%1$d updated for %2$s.', 'sccc'),
                $vendorId,
                $eventId ? Vendors::eventTitle($eventId) : '—'
            ));
        }
    }

    /* -------------------------------------------------------------------------
     * Uploads
     * ---------------------------------------------------------------------- */

    /**
     * Gravity Forms stores a single upload as a URL and multiple uploads as a
     * JSON array of URLs.
     *
     * @return array<int, string>
     */
    private static function uploadedUrls(string $value): array
    {
        $value = trim($value);

        if ($value === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return array_values(array_filter(array_map('strval', is_array($decoded) ? $decoded : [$value])));
    }

    /**
     * Copy a Gravity Forms upload into the Media Library (image files only).
     */
    private static function sideload(string $url, int $postId, string $title): int
    {
        if ($url === '') {
            return 0;
        }

        $path = '';
        if (class_exists('GFFormsModel') && method_exists('GFFormsModel', 'get_physical_file_path')) {
            $path = (string) \GFFormsModel::get_physical_file_path($url);
        }

        if ($path === '' || ! file_exists($path)) {
            $uploads = wp_get_upload_dir();
            $path = str_replace($uploads['baseurl'], $uploads['basedir'], strtok($url, '?'));
        }

        if (! file_exists($path)) {
            return 0;
        }

        $check = wp_check_filetype_and_ext($path, basename($path));
        if (! in_array($check['type'] ?? '', ['image/jpeg', 'image/png', 'image/webp'], true)) {
            return 0;
        }

        require_once ABSPATH.'wp-admin/includes/file.php';
        require_once ABSPATH.'wp-admin/includes/media.php';
        require_once ABSPATH.'wp-admin/includes/image.php';

        $tmp = wp_tempnam(basename($path));
        if (! $tmp || ! copy($path, $tmp)) {
            return 0;
        }

        $id = media_handle_sideload(['name' => basename($path), 'tmp_name' => $tmp], $postId, $title);

        if (is_wp_error($id)) {
            @unlink($tmp); // phpcs:ignore WordPress.PHP.NoSilencedErrors

            return 0;
        }

        return (int) $id;
    }
}
