<?php
/**
 * app/Support/Members/Garage.php
 * File: app/Support/Members/Garage.php
 *
 * What this file does
 * -----------------------------------------------------------------------------
 * Handles Garage vehicle CRUD (add/update/delete) from the My Account dashboard,
 * using the EXISTING ACF user repeater:
 *
 *   vehicles[] (repeater on user)
 *     - vehicle_id   (stable row id)
 *     - year
 *     - make_select
 *     - make_other
 *     - make_raw     (computed canonical make)
 *     - model
 *     - nickname
 *     - notes        (stored as plain text with real newlines; max 1,000 chars)
 *     - image        (attachment ID)
 *
 * Why this file avoids deep post-save comparison
 * -----------------------------------------------------------------------------
 * ACF/WP meta writes can return false in normal situations, including when there
 * is no detected change. Also, ACF can format field values differently when they
 * are read back for display. That makes strict post-save comparison unreliable
 * for this frontend Garage flow.
 *
 * The important rule here:
 * - Validate before save.
 * - Normalize textarea/newline data before save.
 * - Let ACF update the complete repeater array.
 * - After adding a new vehicle, only confirm the generated vehicle_id exists.
 * - After updating an existing vehicle, trust the write once validation/upload
 *   passed, because the row already exists and ACF updates the whole repeater.
 *
 * This prevents false “ACF failed to save” messages when the user meta is
 * actually saved correctly.
 *
 * Notes are stored as plain text and rendered in the Blade template with:
 *
 *   nl2br(e($notes))
 */

namespace App\Support\Members;

class Garage
{
    /**
     * Maximum plain-text vehicle description length.
     *
     * This keeps member Garage cards readable while still allowing enough room
     * for year/trim/package/mod notes and the story behind the build.
     */
    protected const VEHICLE_NOTES_MAX_LENGTH = 1000;

    public static function boot(): void
    {
        add_action('template_redirect', [static::class, 'maybeHandleGaragePost'], 20);
    }

    public static function maybeHandleGaragePost(): void
    {
        if (!is_user_logged_in()) {
            return;
        }

        if (!function_exists('is_account_page') || !is_account_page()) {
            return;
        }

        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            return;
        }

        $action = isset($_POST['sccc_garage_action'])
            ? sanitize_text_field(wp_unslash($_POST['sccc_garage_action']))
            : '';

        if (!in_array($action, ['add_vehicle', 'update_vehicle', 'delete_vehicle'], true)) {
            return;
        }

        $nonce = isset($_POST['sccc_garage_nonce'])
            ? sanitize_text_field(wp_unslash($_POST['sccc_garage_nonce']))
            : '';

        $nonceAction = match ($action) {
            'add_vehicle'    => 'sccc_garage_add_vehicle',
            'update_vehicle' => 'sccc_garage_update_vehicle',
            'delete_vehicle' => 'sccc_garage_delete_vehicle',
            default          => 'sccc_garage_add_vehicle',
        };

        if (!$nonce || !wp_verify_nonce($nonce, $nonceAction)) {
            static::addNotice('Security check failed. Please try again.', 'error');
            static::safeRedirectBackToGarage();
        }

        $user_id = (int) get_current_user_id();

        if (!function_exists('get_field') || !function_exists('update_field')) {
            static::addNotice('Garage requires ACF (get_field / update_field). ACF was not available.', 'error');
            static::safeRedirectBackToGarage();
        }

        $acf_user_key = 'user_' . $user_id;

        /**
         * Load the repeater in normal ACF row shape.
         *
         * Do not load the whole repeater with raw/unformatted mode for mutation,
         * because repeater storage can come back in a shape that is not ideal for
         * matching rows by vehicle_id.
         */
        $vehicles = static::getVehiclesForMutation($acf_user_key);

        if ($action === 'add_vehicle') {
            static::handleAddVehicle($vehicles, $acf_user_key);
        }

        if ($action === 'update_vehicle') {
            static::handleUpdateVehicle($vehicles, $acf_user_key);
        }

        if ($action === 'delete_vehicle') {
            static::handleDeleteVehicle($vehicles, $acf_user_key);
        }

        static::safeRedirectBackToGarage();
    }

    /**
     * ADD VEHICLE
     */
    protected static function handleAddVehicle(array $vehicles, string $acf_user_key): void
    {
        $year       = isset($_POST['sccc_vehicle_year']) ? sanitize_text_field(wp_unslash($_POST['sccc_vehicle_year'])) : '';
        $makeSelect = isset($_POST['sccc_vehicle_make_select']) ? sanitize_text_field(wp_unslash($_POST['sccc_vehicle_make_select'])) : '';
        $makeOther  = isset($_POST['sccc_vehicle_make_other']) ? sanitize_text_field(wp_unslash($_POST['sccc_vehicle_make_other'])) : '';
        $model      = isset($_POST['sccc_vehicle_model']) ? sanitize_text_field(wp_unslash($_POST['sccc_vehicle_model'])) : '';
        $nickname   = isset($_POST['sccc_vehicle_nickname']) ? sanitize_text_field(wp_unslash($_POST['sccc_vehicle_nickname'])) : '';
        $notes      = isset($_POST['sccc_vehicle_notes']) ? static::sanitizeVehicleNotes(wp_unslash($_POST['sccc_vehicle_notes'])) : '';

        static::validateVehicleNotesLength($notes);

        $year = trim($year);
        $makeSelect = trim($makeSelect);
        $makeOther = trim($makeOther);
        $model = trim($model);
        $nickname = trim($nickname);

        if ($makeSelect === '' && $model === '' && $year === '') {
            static::addNotice('Please enter at least a Make/Model or a Year for the vehicle.', 'error');
            static::safeRedirectBackToGarage();
        }

        $makeRaw = ($makeSelect === 'Other' && $makeOther !== '') ? $makeOther : $makeSelect;
        $makeRaw = preg_replace('/\s+/', ' ', trim((string) $makeRaw));

        $existingIds = [];

        foreach ($vehicles as $row) {
            if (!is_array($row)) {
                continue;
            }

            $vid = isset($row['vehicle_id']) ? trim((string) $row['vehicle_id']) : '';

            if ($vid !== '') {
                $existingIds[$vid] = true;
            }
        }

        $vehicleId = static::generateVehicleId($existingIds);

        $imageAttachmentId = 0;

        if (!empty($_FILES['sccc_vehicle_image']) && !empty($_FILES['sccc_vehicle_image']['name'])) {
            $uploaded = static::handleImageUpload('sccc_vehicle_image');

            if (is_wp_error($uploaded)) {
                static::addNotice($uploaded->get_error_message(), 'error');
                static::safeRedirectBackToGarage();
            }

            if (is_int($uploaded) && $uploaded > 0) {
                $imageAttachmentId = (int) $uploaded;
            }
        }

        $vehicles[] = [
            'vehicle_id'  => $vehicleId,
            'year'        => $year,
            'make_select' => $makeSelect,
            'make_other'  => $makeOther,
            'make_raw'    => $makeRaw,
            'model'       => $model,
            'nickname'    => $nickname,
            'notes'       => $notes,
            'image'       => $imageAttachmentId,
        ];

        $vehicles = array_values($vehicles);
        $vehicles = static::normalizeVehiclesForStorage($vehicles);

        update_field('vehicles', $vehicles, $acf_user_key);

        /**
         * Add-only verification:
         * Confirm the generated vehicle_id exists after the save.
         *
         * We intentionally do not compare every field because ACF can format
         * text/image values differently when read back.
         */
        if (!static::vehicleExists($acf_user_key, $vehicleId)) {
            static::addNotice('ACF failed to save the vehicle. Please try again.', 'error');
            static::safeRedirectBackToGarage();
        }

        static::addNotice('Vehicle added to your Garage.', 'success');
        static::safeRedirectBackToGarage();
    }

    /**
     * UPDATE VEHICLE
     */
    protected static function handleUpdateVehicle(array $vehicles, string $acf_user_key): void
    {
        $vehicleId = isset($_POST['sccc_vehicle_id'])
            ? trim(sanitize_text_field(wp_unslash($_POST['sccc_vehicle_id'])))
            : '';

        if ($vehicleId === '') {
            static::addNotice('Missing vehicle id.', 'error');
            static::safeRedirectBackToGarage();
        }

        $idx = static::findVehicleIndexById($vehicles, $vehicleId);

        if ($idx === null) {
            static::addNotice('Vehicle not found.', 'error');
            static::safeRedirectBackToGarage();
        }

        $year       = isset($_POST['sccc_vehicle_year']) ? sanitize_text_field(wp_unslash($_POST['sccc_vehicle_year'])) : '';
        $makeSelect = isset($_POST['sccc_vehicle_make_select']) ? sanitize_text_field(wp_unslash($_POST['sccc_vehicle_make_select'])) : '';
        $makeOther  = isset($_POST['sccc_vehicle_make_other']) ? sanitize_text_field(wp_unslash($_POST['sccc_vehicle_make_other'])) : '';
        $model      = isset($_POST['sccc_vehicle_model']) ? sanitize_text_field(wp_unslash($_POST['sccc_vehicle_model'])) : '';
        $nickname   = isset($_POST['sccc_vehicle_nickname']) ? sanitize_text_field(wp_unslash($_POST['sccc_vehicle_nickname'])) : '';
        $notes      = isset($_POST['sccc_vehicle_notes']) ? static::sanitizeVehicleNotes(wp_unslash($_POST['sccc_vehicle_notes'])) : '';

        static::validateVehicleNotesLength($notes);

        $year = trim($year);
        $makeSelect = trim($makeSelect);
        $makeOther = trim($makeOther);
        $model = trim($model);
        $nickname = trim($nickname);

        if ($makeSelect === '' && $model === '' && $year === '') {
            static::addNotice('Please enter at least a Make/Model or a Year for the vehicle.', 'error');
            static::safeRedirectBackToGarage();
        }

        $makeRaw = ($makeSelect === 'Other' && $makeOther !== '') ? $makeOther : $makeSelect;
        $makeRaw = preg_replace('/\s+/', ' ', trim((string) $makeRaw));

        $removeImage = !empty($_POST['sccc_vehicle_image_remove'])
            && (string) sanitize_text_field(wp_unslash($_POST['sccc_vehicle_image_remove'])) === '1';

        $currentRow = isset($vehicles[$idx]) && is_array($vehicles[$idx]) ? $vehicles[$idx] : [];
        $imageAttachmentId = isset($currentRow['image']) ? static::normalizeImageId($currentRow['image']) : 0;

        if ($removeImage) {
            $imageAttachmentId = 0;
        } elseif (!empty($_FILES['sccc_vehicle_image']) && !empty($_FILES['sccc_vehicle_image']['name'])) {
            $uploaded = static::handleImageUpload('sccc_vehicle_image');

            if (is_wp_error($uploaded)) {
                static::addNotice($uploaded->get_error_message(), 'error');
                static::safeRedirectBackToGarage();
            }

            if (is_int($uploaded) && $uploaded > 0) {
                $imageAttachmentId = (int) $uploaded;
            }
        }

        $stableId = isset($currentRow['vehicle_id'])
            ? (string) $currentRow['vehicle_id']
            : $vehicleId;

        $vehicles[$idx] = array_merge($currentRow, [
            'vehicle_id'  => $stableId,
            'year'        => $year,
            'make_select' => $makeSelect,
            'make_other'  => $makeOther,
            'make_raw'    => $makeRaw,
            'model'       => $model,
            'nickname'    => $nickname,
            'notes'       => $notes,
            'image'       => $imageAttachmentId,
        ]);

        $vehicles = array_values($vehicles);
        $vehicles = static::normalizeVehiclesForStorage($vehicles);

        update_field('vehicles', $vehicles, $acf_user_key);

        /**
         * Update verification:
         * The row was already found before save, and validation/upload passed.
         * Do not deep-compare values after update_field(), because ACF can return
         * formatted values when read back and WordPress meta updates do not provide
         * a reliable failure signal for unchanged/same-value writes.
         */
        static::addNotice('Vehicle updated.', 'success');
        static::safeRedirectBackToGarage();
    }

    /**
     * DELETE VEHICLE
     */
    protected static function handleDeleteVehicle(array $vehicles, string $acf_user_key): void
    {
        $vehicleId = isset($_POST['sccc_vehicle_id'])
            ? trim(sanitize_text_field(wp_unslash($_POST['sccc_vehicle_id'])))
            : '';

        if ($vehicleId === '') {
            static::addNotice('Missing vehicle id.', 'error');
            static::safeRedirectBackToGarage();
        }

        $before = count($vehicles);

        $vehicles = array_values(array_filter($vehicles, function ($row) use ($vehicleId) {
            if (!is_array($row)) {
                return true;
            }

            $vid = isset($row['vehicle_id']) ? trim((string) $row['vehicle_id']) : '';

            if ($vid === '') {
                return true;
            }

            return !hash_equals($vid, $vehicleId);
        }));

        if (count($vehicles) === $before) {
            static::addNotice('Vehicle not found.', 'error');
            static::safeRedirectBackToGarage();
        }

        $vehicles = static::normalizeVehiclesForStorage($vehicles);

        update_field('vehicles', $vehicles, $acf_user_key);

        $after = static::getVehiclesForMutation($acf_user_key);
        $stillThere = static::findVehicleIndexById($after, $vehicleId) !== null;

        if ($stillThere) {
            static::addNotice('ACF failed to remove the vehicle. Please try again.', 'error');
            static::safeRedirectBackToGarage();
        }

        static::addNotice('Vehicle removed from your Garage.', 'success');
        static::safeRedirectBackToGarage();
    }

    protected static function findVehicleIndexById(array $vehicles, string $vehicleId): ?int
    {
        foreach ($vehicles as $i => $row) {
            if (!is_array($row)) {
                continue;
            }

            $vid = isset($row['vehicle_id']) ? trim((string) $row['vehicle_id']) : '';

            if ($vid !== '' && hash_equals($vid, $vehicleId)) {
                return (int) $i;
            }
        }

        return null;
    }

    /**
     * Light add-save verification.
     *
     * This is intentionally limited to row existence by vehicle_id.
     */
    protected static function vehicleExists(string $acf_user_key, string $vehicleId): bool
    {
        $vehicles = static::getVehiclesForMutation($acf_user_key);

        return static::findVehicleIndexById($vehicles, $vehicleId) !== null;
    }

    /**
     * Load the vehicles repeater for add/update/delete operations.
     *
     * We use the normal ACF field value so repeaters come back as row arrays that
     * can be searched by vehicle_id. Then we normalize any display-formatted
     * values before passing the array back into update_field().
     */
    protected static function getVehiclesForMutation(string $acf_user_key): array
    {
        $vehicles = get_field('vehicles', $acf_user_key);
        $vehicles = is_array($vehicles) ? $vehicles : [];
        $vehicles = array_values($vehicles);

        return static::normalizeVehiclesForStorage($vehicles);
    }

    /**
     * Normalize every vehicle row before saving the whole repeater back to ACF.
     *
     * This protects older rows from being re-saved with formatted/escaped <br>
     * strings when only one row is being edited.
     */
    protected static function normalizeVehiclesForStorage(array $vehicles): array
    {
        $textKeys = [
            'vehicle_id',
            'year',
            'make_select',
            'make_other',
            'make_raw',
            'model',
            'nickname',
        ];

        foreach ($vehicles as $i => $row) {
            if (!is_array($row)) {
                unset($vehicles[$i]);
                continue;
            }

            foreach ($textKeys as $key) {
                if (array_key_exists($key, $row)) {
                    $vehicles[$i][$key] = static::normalizeScalarForStorage($row[$key]);
                }
            }

            if (isset($row['notes'])) {
                $vehicles[$i]['notes'] = static::normalizeTextareaForStorage(
                    static::normalizeScalarForStorage($row['notes'])
                );
            }

            if (isset($row['image'])) {
                $vehicles[$i]['image'] = static::normalizeImageId($row['image']);
            }
        }

        return array_values($vehicles);
    }

    /**
     * Normalize scalar-ish values that may come back from ACF display formatting.
     */
    protected static function normalizeScalarForStorage($value): string
    {
        if (is_scalar($value)) {
            return trim((string) $value);
        }

        if ($value === null) {
            return '';
        }

        return '';
    }

    /**
     * Normalize an ACF image value to the attachment ID stored by ACF.
     *
     * Depending on the field's return format, ACF may load the image as an ID,
     * array, or object. update_field() can safely receive the attachment ID.
     */
    protected static function normalizeImageId($value): int
    {
        if (is_numeric($value)) {
            return (int) $value;
        }

        if (is_array($value)) {
            if (isset($value['ID']) && is_numeric($value['ID'])) {
                return (int) $value['ID'];
            }

            if (isset($value['id']) && is_numeric($value['id'])) {
                return (int) $value['id'];
            }
        }

        if (is_object($value) && isset($value->ID) && is_numeric($value->ID)) {
            return (int) $value->ID;
        }

        return 0;
    }

    /**
     * Sanitize textarea input while preserving real newlines.
     *
     * WordPress' textarea sanitizer preserves legitimate multiline text while
     * still removing unsafe input.
     */
    protected static function sanitizeVehicleNotes($raw): string
    {
        return static::normalizeTextareaForStorage(sanitize_textarea_field((string) $raw));
    }

    /**
     * Stop saves when the description exceeds the agreed plain-text limit.
     *
     * The frontend counter should catch this first, but server-side validation is
     * required because users can bypass browser JavaScript.
     */
    protected static function validateVehicleNotesLength(string $notes): void
    {
        $length = static::vehicleNotesLength($notes);

        if ($length <= static::VEHICLE_NOTES_MAX_LENGTH) {
            return;
        }

        static::addNotice(
            sprintf(
                'Vehicle description is %d/%d characters. Please shorten it before saving.',
                $length,
                static::VEHICLE_NOTES_MAX_LENGTH
            ),
            'error'
        );

        static::safeRedirectBackToGarage();
    }

    /**
     * Count user-facing characters for the description limit.
     */
    protected static function vehicleNotesLength(string $value): int
    {
        if (function_exists('mb_strlen')) {
            return (int) mb_strlen($value, get_option('blog_charset') ?: 'UTF-8');
        }

        return (int) strlen($value);
    }

    /**
     * Convert legacy stored/posted line-break markup back into real newlines.
     *
     * This handles:
     * - <br>, <br/>, <br />
     * - escaped entities like &lt;br /&gt;
     * - Windows/Mac newlines
     * - excessive blank lines created by repeated br formatting
     */
    protected static function normalizeTextareaForStorage(string $value): string
    {
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, get_option('blog_charset'));
        $value = preg_replace('/<br\s*\/?>/i', "\n", $value) ?? $value;
        $value = preg_replace("/\r\n?|\n/", "\n", $value) ?? $value;
        $value = preg_replace("/\n{3,}/", "\n\n", $value) ?? $value;

        return trim($value);
    }

    protected static function handleImageUpload(string $file_key)
    {
        if (!function_exists('media_handle_upload')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';
        }

        $type = !empty($_FILES[$file_key]['type']) ? (string) $_FILES[$file_key]['type'] : '';

        if ($type && strpos($type, 'image/') !== 0) {
            return new \WP_Error('sccc_vehicle_image_type', __('Please upload a valid image file.', 'sccc'));
        }

        $attachment_id = media_handle_upload($file_key, 0);

        if (is_wp_error($attachment_id)) {
            return $attachment_id;
        }

        if (!wp_attachment_is_image($attachment_id)) {
            wp_delete_attachment($attachment_id, true);

            return new \WP_Error('sccc_vehicle_image_not_image', __('Please upload a valid image file.', 'sccc'));
        }

        return (int) $attachment_id;
    }

    protected static function generateVehicleId(array $seen = [], int $maxAttempts = 25): string
    {
        for ($i = 0; $i < $maxAttempts; $i++) {
            $hex = strtoupper(bin2hex(random_bytes(4)));
            $id = 'VH-' . $hex;

            if (!isset($seen[$id])) {
                return $id;
            }
        }

        return 'VH-' . strtoupper(bin2hex(random_bytes(4)));
    }

    protected static function addNotice(string $message, string $type = 'success'): void
    {
        if (function_exists('wc_add_notice')) {
            wc_add_notice($message, $type);
        }
    }

    protected static function safeRedirectBackToGarage(): void
    {
        $ref = wp_get_referer();

        $target = $ref
            ? $ref
            : (function_exists('wc_get_page_permalink') ? wc_get_page_permalink('myaccount') : home_url('/my-account/'));

        $target = preg_replace('/#.*$/', '', (string) $target);
        $target = $target . '#sccc-garage';

        wp_safe_redirect($target);
        exit;
    }
}

add_action('after_setup_theme', [Garage::class, 'boot']);