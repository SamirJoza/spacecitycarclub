<?php
/**
 * app/Support/Admin/Users/ReferralSourceField.php
 * File: app/Support/Admin/Users/ReferralSourceField.php
 *
 * Admin-only "Referral Source" field for users.
 *
 * Requirements covered
 * ------------------------------------------------------------------------------
 * - Display-only field in WP Admin user editor (cannot be changed there)
 * - Visible to admins/editors who can edit users
 * - Can be viewed as a column in Users list
 * - Can be filtered (and optionally sorted) by that field in Users list
 *
 * Storage
 * ------------------------------------------------------------------------------
 * - Stored in user meta under: sccc_referral_source
 * - This file does not set/populate the value; it only displays/filters it.
 *   (You can set it anywhere in your app via update_user_meta($userId, 'sccc_referral_source', '...');)
 */

namespace App\Support\Admin\Users;

final class ReferralSourceField
{
    /**
     * User meta key where the value is stored.
     */
    private const META_KEY = 'sccc_referral_source';

    /**
     * Query-string key for filtering on Users list screen.
     */
    private const FILTER_KEY = 'sccc_referral_source_filter';

    /**
     * Column key in the Users table.
     */
    private const COLUMN_KEY = 'sccc_referral_source';

    public static function boot(): void
    {
        if (!is_admin()) {
            return;
        }

        // --- User editor display (view-only) ---
        add_action('show_user_profile', [__CLASS__, 'renderUserField']);
        add_action('edit_user_profile', [__CLASS__, 'renderUserField']);

        // --- Users list column ---
        add_filter('manage_users_columns', [__CLASS__, 'addUsersColumn'], 20, 1);
        add_filter('manage_users_custom_column', [__CLASS__, 'renderUsersColumn'], 20, 3);

        // --- Users list filter UI + query handling ---
        add_action('restrict_manage_users', [__CLASS__, 'renderUsersListFilter']);
        add_action('pre_get_users', [__CLASS__, 'applyUsersListFilter']);

        // --- Optional: make the column sortable ---
        add_filter('manage_users_sortable_columns', [__CLASS__, 'makeUsersColumnSortable']);
        add_action('pre_get_users', [__CLASS__, 'applyUsersListSorting']);
    }

    /**
     * Render the "Referral Source" field on the user edit screen.
     * Display-only: no inputs that save, and no save hooks.
     *
     * @param \WP_User $user
     */
    public static function renderUserField($user): void
    {
        if (!$user instanceof \WP_User) {
            return;
        }

        if (!current_user_can('edit_user', $user->ID)) {
            return;
        }

        $value = (string) get_user_meta($user->ID, self::META_KEY, true);
        $value = trim($value);

        ?>
        <h2>Referral Source</h2>

        <table class="form-table" role="presentation">
            <tr>
                <th><label>Referral Source</label></th>
                <td>
                    <input
                        type="text"
                        class="regular-text"
                        value="<?php echo esc_attr($value); ?>"
                        readonly
                        aria-readonly="true"
                    />
                    <p class="description">
                        Display only. This value is stored in user meta as <code><?php echo esc_html(self::META_KEY); ?></code>.
                    </p>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Add a "Referral Source" column to the Users list table.
     */
    public static function addUsersColumn(array $columns): array
    {
        // Place it near the end, but before "Posts" if that exists.
        if (isset($columns['posts'])) {
            $new = [];

            foreach ($columns as $key => $label) {
                if ($key === 'posts') {
                    $new[self::COLUMN_KEY] = 'Referral Source';
                }
                $new[$key] = $label;
            }

            return $new;
        }

        $columns[self::COLUMN_KEY] = 'Referral Source';
        return $columns;
    }

    /**
     * Render the column value per user.
     */
    public static function renderUsersColumn($output, string $columnName, int $userId)
    {
        if ($columnName !== self::COLUMN_KEY) {
            return $output;
        }

        $value = (string) get_user_meta($userId, self::META_KEY, true);
        $value = trim($value);

        return $value !== '' ? esc_html($value) : '—';
    }

    /**
     * Render a filter field on the Users list screen.
     * This is a simple "contains" filter (LIKE).
     */
    public static function renderUsersListFilter(string $which): void
    {
        // Only on Users list screen.
        if (!self::isUsersListScreen()) {
            return;
        }

        // Only show for admins who can edit users.
        if (!current_user_can('list_users')) {
            return;
        }

        $current = isset($_GET[self::FILTER_KEY]) ? (string) wp_unslash($_GET[self::FILTER_KEY]) : '';
        $current = trim($current);

        echo '<label for="' . esc_attr(self::FILTER_KEY) . '" style="margin-left:8px;">Referral Source</label> ';
        echo '<input
                type="text"
                id="' . esc_attr(self::FILTER_KEY) . '"
                name="' . esc_attr(self::FILTER_KEY) . '"
                value="' . esc_attr($current) . '"
                placeholder="Filter…"
                style="max-width:220px; margin-left:6px;"
              /> ';

        submit_button('Filter', 'secondary', '', false);

        // Small "Clear" link if a filter is active.
        if ($current !== '') {
            $clearUrl = remove_query_arg(self::FILTER_KEY);
            echo ' <a href="' . esc_url($clearUrl) . '" class="button">Clear</a>';
        }
    }

    /**
     * Apply the filter to the Users list query.
     */
    public static function applyUsersListFilter(\WP_User_Query $query): void
    {
        if (!self::isUsersListScreen()) {
            return;
        }

        if (!current_user_can('list_users')) {
            return;
        }

        $filter = isset($_GET[self::FILTER_KEY]) ? (string) wp_unslash($_GET[self::FILTER_KEY]) : '';
        $filter = trim($filter);

        if ($filter === '') {
            return;
        }

        // Merge with any existing meta_query.
        $metaQuery = $query->get('meta_query');
        if (!is_array($metaQuery)) {
            $metaQuery = [];
        }

        $metaQuery[] = [
            'key'     => self::META_KEY,
            'value'   => $filter,
            'compare' => 'LIKE',
        ];

        $query->set('meta_query', $metaQuery);
    }

    /**
     * Make the column sortable.
     */
    public static function makeUsersColumnSortable(array $sortable): array
    {
        $sortable[self::COLUMN_KEY] = self::COLUMN_KEY;
        return $sortable;
    }

    /**
     * Apply sorting when admin clicks the column header.
     */
    public static function applyUsersListSorting(\WP_User_Query $query): void
    {
        if (!self::isUsersListScreen()) {
            return;
        }

        $orderby = $query->get('orderby');
        if ($orderby !== self::COLUMN_KEY) {
            return;
        }

        // Sort by meta value.
        $query->set('meta_key', self::META_KEY);
        $query->set('orderby', 'meta_value');
    }

    /**
     * Detect Users list screen reliably.
     */
    private static function isUsersListScreen(): bool
    {
        if (!is_admin()) {
            return false;
        }

        // Fast check
        global $pagenow;
        if (isset($pagenow) && $pagenow !== 'users.php') {
            return false;
        }

        // If available, use get_current_screen()
        if (function_exists('get_current_screen')) {
            $screen = get_current_screen();
            if ($screen && isset($screen->id) && $screen->id !== 'users') {
                return false;
            }
        }

        return true;
    }
}

ReferralSourceField::boot();