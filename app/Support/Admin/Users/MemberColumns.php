<?php

namespace App\Support\Admin\Users;

/**
 * MemberColumns
 * File: app/Support/Admin/Users/MemberColumns.php
 * =================================================
 *
 * WHAT THIS FILE DOES
 * -------------------
 * Adds a "Phone" column to the WordPress admin Users list.
 *
 * Data source:
 * - sccc_member_phone
 *
 * Why this is separate
 * --------------------
 * The member phone field itself is registered in:
 * - app/Fields/MemberProfile.php
 *
 * The frontend edit/save behavior is handled in:
 * - app/Support/Woo/AccountEditExtras.php
 *
 * This file only handles the admin Users table display so the member field
 * logic does not get bloated with admin-list concerns.
 *
 * Privacy note
 * ------------
 * The phone number is shown only in wp-admin to users who can access the Users
 * list. It is not intended for the future public/member directory unless we add
 * a separate explicit opt-in later.
 *
 * Loading note
 * ------------
 * This file self-boots once it is required by the theme. Load it the same way
 * your other theme-contained support files are loaded.
 */

class MemberColumns
{
    /**
     * Column key used internally by the WordPress Users list table.
     */
    protected const PHONE_COLUMN = 'sccc_member_phone';

    /**
     * Prevent duplicate hook registration if this file is required twice.
     */
    protected static bool $booted = false;

    /**
     * Register admin hooks.
     */
    public static function boot(): void
    {
        if (static::$booted) {
            return;
        }

        static::$booted = true;

        add_filter('manage_users_columns', [static::class, 'addPhoneColumn']);
        add_filter('manage_users_custom_column', [static::class, 'renderPhoneColumn'], 10, 3);

        /**
         * Small scoped styling for the Users list table only.
         * Keeps the column readable without changing unrelated admin screens.
         */
        add_action('admin_head-users.php', [static::class, 'renderAdminStyles']);
    }

    /**
     * Add the Phone column after Email when possible.
     *
     * We insert after the native "email" column so admins see it near the core
     * contact info instead of at the far right of the table.
     */
    public static function addPhoneColumn(array $columns): array
    {
        $updated = [];
        $inserted = false;

        foreach ($columns as $key => $label) {
            $updated[$key] = $label;

            if ($key === 'email') {
                $updated[static::PHONE_COLUMN] = __('Phone', 'sccc');
                $inserted = true;
            }
        }

        if (! $inserted) {
            $updated[static::PHONE_COLUMN] = __('Phone', 'sccc');
        }

        return $updated;
    }

    /**
     * Render the Phone column value.
     *
     * WordPress calls this for every custom column in the Users list table.
     * We return the existing output for all columns except our phone column.
     */
    public static function renderPhoneColumn(string $output, string $column_name, int $user_id): string
    {
        if ($column_name !== static::PHONE_COLUMN) {
            return $output;
        }

        if (! current_user_can('list_users')) {
            return static::emptyValue();
        }

        $phone = static::getMemberPhone($user_id);

        if ($phone === '') {
            return static::emptyValue();
        }

        $tel_href = static::phoneToTelHref($phone);

        if ($tel_href === '') {
            return esc_html($phone);
        }

        return sprintf(
            '<a class="sccc-admin-user-phone" href="%s">%s</a>',
            esc_url($tel_href),
            esc_html($phone)
        );
    }

    /**
     * Get the member phone value.
     *
     * ACF user fields are ultimately stored as user meta, but when ACF is active
     * we read through get_field() first so the admin list remains consistent
     * with how the field group is managed.
     */
    protected static function getMemberPhone(int $user_id): string
    {
        if ($user_id <= 0) {
            return '';
        }

        $phone = '';

        if (function_exists('get_field')) {
            $phone = (string) (get_field('sccc_member_phone', 'user_' . $user_id) ?: '');
        }

        if ($phone === '') {
            $phone = (string) get_user_meta($user_id, 'sccc_member_phone', true);
        }

        return static::normalizePhone($phone);
    }

    /**
     * Normalize phone display.
     *
     * The admin list should show a clean, consistent phone number instead of
     * whatever punctuation the member entered.
     *
     * Rules:
     * - Strip all non-digits.
     * - Format 10-digit US numbers as: (555) 123-4567
     * - Format 11-digit US numbers beginning with 1 as: +1 (555) 123-4567
     * - Format 7-digit local numbers as: 123-4567
     * - If the digit count does not match one of those known patterns, show the
     *   digits only instead of guessing.
     */
    protected static function normalizePhone(string $phone): string
    {
        $phone = sanitize_text_field($phone);
        $digits = static::phoneToDigits($phone);

        return static::formatUsPhoneNumber($digits);
    }

    /**
     * Strip everything except digits from a phone value.
     *
     * This intentionally removes spaces, dashes, parentheses, periods, plus
     * signs, and any other non-digit characters before display formatting.
     */
    protected static function phoneToDigits(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone);

        return is_string($digits) ? $digits : '';
    }

    /**
     * Format a digits-only phone value for display in the admin Users table.
     *
     * We format only clear US-style patterns. Anything else returns as digits
     * only so the admin still sees the saved number without us inventing a
     * potentially incorrect format.
     */
    protected static function formatUsPhoneNumber(string $digits): string
    {
        $digits = trim($digits);

        if ($digits === '') {
            return '';
        }

        if (strlen($digits) === 11 && str_starts_with($digits, '1')) {
            return sprintf(
                '+1 (%s) %s-%s',
                substr($digits, 1, 3),
                substr($digits, 4, 3),
                substr($digits, 7, 4)
            );
        }

        if (strlen($digits) === 10) {
            return sprintf(
                '(%s) %s-%s',
                substr($digits, 0, 3),
                substr($digits, 3, 3),
                substr($digits, 6, 4)
            );
        }

        if (strlen($digits) === 7) {
            return sprintf(
                '%s-%s',
                substr($digits, 0, 3),
                substr($digits, 3, 4)
            );
        }

        return $digits;
    }

    /**
     * Convert a readable phone value into a safe tel: href.
     *
     * The href uses the same digits-only rule as the display logic. For US
     * numbers saved with a leading 1, we include the international +1 prefix.
     * For 10-digit numbers, we keep the href as a plain domestic number.
     */
    protected static function phoneToTelHref(string $phone): string
    {
        $digits = static::phoneToDigits($phone);

        if ($digits === '') {
            return '';
        }

        if (strlen($digits) < 7) {
            return '';
        }

        if (strlen($digits) === 11 && str_starts_with($digits, '1')) {
            return 'tel:+' . $digits;
        }

        return 'tel:' . $digits;
    }

    /**
     * Consistent empty-state display for users without a saved phone number.
     */
    protected static function emptyValue(): string
    {
        return '<span class="sccc-admin-user-phone--empty" aria-hidden="true">—</span>';
    }

    /**
     * Users list-only admin styles.
     *
     * Kept inline and tiny because this is a one-column admin enhancement.
     */
    public static function renderAdminStyles(): void
    {
        ?>
        <style>
            .users-php .column-<?php echo esc_attr(static::PHONE_COLUMN); ?> {
                width: 150px;
            }

            .users-php .sccc-admin-user-phone {
                font-weight: 600;
                text-decoration: none;
                white-space: nowrap;
            }

            .users-php .sccc-admin-user-phone:hover {
                text-decoration: underline;
            }

            .users-php .sccc-admin-user-phone--empty {
                color: #8c8f94;
            }
        </style>
        <?php
    }
}

MemberColumns::boot();