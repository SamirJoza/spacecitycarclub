<?php

namespace App\Support\FeaturedVehicles;

use WP_Query;

/**
 * File: app/Support/FeaturedVehicles/RegisterFeaturedVehiclePostType.php
 *
 * Monthly Featured Vehicle Archive CPT
 * ====================================
 *
 * PURPOSE
 * -------
 * Registers the internal custom post type that stores one saved
 * "featured vehicle" archive record per month.
 *
 * WHY THIS FILE EXISTS
 * --------------------
 * The homepage feature should not re-pick a winner on every page load.
 * Instead, the site should:
 *
 * 1. Resolve one winning member + vehicle for the current month.
 * 2. Save that result as a monthly archive record.
 * 3. Read from that saved archive record for the rest of the month.
 *
 * This file only handles the archive container and its admin presentation.
 *
 * IMPORTANT DESIGN DECISIONS
 * --------------------------
 * 1. This CPT is admin-facing only.
 *    It is not meant to be publicly queried or exposed as front-end content.
 *
 * 2. Manual creation is disabled.
 *    These records are intended to be created by the monthly resolver/service,
 *    not by editors clicking "Add New" in wp-admin.
 *
 * 3. The admin list table is the primary management view.
 *    Since these records are system-generated, the list screen should show
 *    the important snapshot data directly in columns.
 *
 * 4. The single edit screen remains available.
 *    This is still useful for inspecting a generated record, featured image,
 *    and any future meta boxes / debug information.
 *
 * WHAT THE RESOLVER WILL EVENTUALLY SAVE
 * --------------------------------------
 * Each monthly archive record is expected to store post meta like:
 *
 * - featured_month_key              Example: 2026-03
 * - featured_month_label            Example: March 2026
 * - featured_year                   Example: 2026
 * - source_user_id                  Original user/member ID
 * - source_vehicle_id               Original vehicle ID
 * - owner_display_name_snapshot     Member display name at selection time
 * - owner_username_snapshot         Member username at selection time
 * - member_number_snapshot          Membership number snapshot
 * - vehicle_year_snapshot           Vehicle year
 * - vehicle_make_snapshot           Vehicle make
 * - vehicle_model_snapshot          Vehicle model
 * - vehicle_nickname_snapshot       Vehicle nickname
 * - generated_at                    When the record was created
 *
 * The column renderer below is already prepared to read those meta keys.
 * Until the resolver is added, these cells may be blank.
 */
final class RegisterFeaturedVehiclePostType
{
    /**
     * Post type slug.
     *
     * NOTE:
     * WordPress post type keys should stay short and lowercase.
     */
    public const POST_TYPE = 'sccc_feature_vehicle';

    /**
     * Meta key used for year-based admin sorting.
     *
     * WHY:
     * Storing a dedicated year snapshot avoids having to parse year values
     * from a formatted month label in the admin query.
     */
    public const META_FEATURED_YEAR = 'featured_year';

    /**
     * Admin-post action used by the force reset button.
     *
     * WHY:
     * WordPress routes authenticated admin requests through admin-post.php using
     * the action value. Keeping this as a constant avoids typos between the URL
     * generator and the request handler.
     */
    protected const FORCE_RESET_ACTION = 'sccc_force_reset_featured_vehicle';

    /**
     * Nonce action used for the force reset link.
     *
     * WHY:
     * The reset changes saved archive data, so the request needs an intent check
     * in addition to the explicit admin capability check.
     */
    protected const FORCE_RESET_NONCE_ACTION = 'sccc_force_reset_featured_vehicle_current_month';

    /**
     * Query vars used to pass force reset feedback back to the list screen.
     *
     * WHY:
     * The admin-post handler redirects after processing, so these keys let the
     * target list screen render a normal WordPress admin notice.
     */
    protected const FORCE_RESET_NOTICE_STATUS_KEY  = 'sccc_fv_reset_status';
    protected const FORCE_RESET_NOTICE_MESSAGE_KEY = 'sccc_fv_reset_message';

    /**
     * Register the hooks required for this CPT.
     *
     * WHY:
     * - register() runs on init because WordPress expects CPT registration there.
     * - admin list column hooks are attached once at boot time.
     */
    public static function boot(): void
    {
        add_action('init', [self::class, 'register']);

        /**
         * Admin list-table customization:
         * show meaningful snapshot data directly in the CPT archive screen.
         */
        add_filter('manage_' . self::POST_TYPE . '_posts_columns', [self::class, 'filterAdminColumns']);
        add_action('manage_' . self::POST_TYPE . '_posts_custom_column', [self::class, 'renderAdminColumn'], 10, 2);

        /**
         * Sortable admin columns:
         * "Year" should remain useful once the archive spans multiple years.
         */
        add_filter('manage_edit-' . self::POST_TYPE . '_sortable_columns', [self::class, 'filterSortableAdminColumns']);
        add_action('pre_get_posts', [self::class, 'handleAdminSorting']);

        /**
         * Remove the Add New submenu item explicitly as a UX safeguard.
         *
         * Even though creation is blocked by capability, this keeps the
         * admin menu cleaner and reinforces that these are generated records.
         */
        add_action('admin_menu', [self::class, 'removeAddNewSubmenu'], 99);

        /**
         * Admin-only force reset workflow:
         * - render a reset button on the CPT list screen
         * - handle the signed admin-post request
         * - show a redirect-safe notice after the reset completes
         */
        add_action('restrict_manage_posts', [self::class, 'renderForceResetControl']);
        add_action('admin_post_' . self::FORCE_RESET_ACTION, [self::class, 'handleForceResetCurrentMonth']);
        add_action('admin_notices', [self::class, 'renderForceResetNotice']);
    }

    /**
     * Register the monthly featured vehicle archive post type.
     */
    public static function register(): void
    {
        /**
         * Guard against duplicate registration in case this file is loaded twice
         * or another module tries to register the same post type.
         */
        if (post_type_exists(self::POST_TYPE)) {
            return;
        }

        register_post_type(self::POST_TYPE, [
            /**
             * Labels
             * ------
             * This is still a normal CPT in wp-admin, but the wording stays
             * archive-oriented because records are script-generated.
             */
            'labels' => [
                'name'                     => 'Featured Vehicles',
                'singular_name'            => 'Featured Vehicle',
                'menu_name'                => 'Featured Vehicles',
                'name_admin_bar'           => 'Featured Vehicle',
                'add_new'                  => 'Add New',
                'add_new_item'             => 'Add New Featured Vehicle',
                'new_item'                 => 'New Featured Vehicle',
                'edit_item'                => 'View / Edit Featured Vehicle',
                'view_item'                => 'View Featured Vehicle',
                'view_items'               => 'View Featured Vehicles',
                'search_items'             => 'Search Featured Vehicles',
                'not_found'                => 'No featured vehicles found.',
                'not_found_in_trash'       => 'No featured vehicles found in Trash.',
                'all_items'                => 'All Featured Vehicles',
                'archives'                 => 'Featured Vehicle Archives',
                'attributes'               => 'Featured Vehicle Attributes',
                'insert_into_item'         => 'Insert into featured vehicle',
                'uploaded_to_this_item'    => 'Uploaded to this featured vehicle',
                'featured_image'           => 'Featured vehicle image',
                'set_featured_image'       => 'Set featured vehicle image',
                'remove_featured_image'    => 'Remove featured vehicle image',
                'use_featured_image'       => 'Use as featured vehicle image',
                'filter_items_list'        => 'Filter featured vehicles list',
                'items_list_navigation'    => 'Featured vehicles list navigation',
                'items_list'               => 'Featured vehicles list',
                'item_published'           => 'Featured vehicle archive record saved.',
                'item_updated'             => 'Featured vehicle archive record updated.',
            ],

            /**
             * Visibility / query behavior
             * ---------------------------
             * Internal/admin-facing only.
             */
            'public'                => false,
            'publicly_queryable'    => false,
            'exclude_from_search'   => true,
            'show_ui'               => true,
            'show_in_menu'          => true,
            'show_in_admin_bar'     => false,
            'show_in_nav_menus'     => false,
            'show_in_rest'          => false,

            /**
             * Structural behavior
             * -------------------
             * No archive URL, no rewrite rules, and no hierarchy needed.
             */
            'has_archive'           => false,
            'rewrite'               => false,
            'query_var'             => false,
            'hierarchical'          => false,

            /**
             * Admin editing support
             * ---------------------
             * - title:
             *   Used for a clear monthly record title such as "March 2026"
             *
             * - thumbnail:
             *   Shows the selected vehicle image in admin and preserves a visual
             *   record for later reuse, such as a printed calendar project.
             *
             * We intentionally do NOT support editor/content because these posts
             * are meta-driven snapshot records, not editorial content entries.
             */
            'supports'              => ['title', 'thumbnail'],

            /**
             * Capability mapping
             * ------------------
             * Key decision:
             * - 'create_posts' => 'do_not_allow'
             *
             * This blocks manual creation of archive entries in wp-admin.
             * The resolver/service layer will create these records programmatically.
             */
            'capability_type'       => 'post',
            'map_meta_cap'          => true,
            'capabilities'          => [
                'create_posts' => 'do_not_allow',
            ],

            /**
             * Admin presentation
             * ------------------
             * Calendar icon fits the monthly archive purpose.
             */
            'menu_icon'             => 'dashicons-calendar-alt',

            /**
             * Data portability / housekeeping
             * -------------------------------
             * Keep historical archive records even if a user is deleted later.
             */
            'can_export'            => true,
            'delete_with_user'      => false,
        ]);
    }

    /**
     * Remove the default Add New submenu item for this CPT.
     *
     * WHY:
     * The capability block already prevents creation, but this removes
     * the redundant menu item so wp-admin better reflects the intended workflow.
     */
    public static function removeAddNewSubmenu(): void
    {
        remove_submenu_page(
            'edit.php?post_type=' . self::POST_TYPE,
            'post-new.php?post_type=' . self::POST_TYPE
        );
    }

    /**
     * Render the Force Reset control on the Featured Vehicles list screen.
     *
     * WHY THIS IS A LINK IN THE FILTER BAR
     * ------------------------------------
     * The CPT intentionally does not allow manual "Add New" records. This gives
     * admins one clear management action without opening up manual creation or
     * adding a separate settings page.
     *
     * SECURITY
     * --------
     * - Only admins with manage_options see the button.
     * - The URL includes a nonce that is verified in the request handler.
     */
    public static function renderForceResetControl(string $postType = ''): void
    {
        $currentPostType = $postType !== '' ? $postType : (string) ($_GET['post_type'] ?? '');

        if ($currentPostType !== self::POST_TYPE) {
            return;
        }

        if (!current_user_can('manage_options')) {
            return;
        }

        $resetUrl = wp_nonce_url(
            admin_url('admin-post.php?action=' . self::FORCE_RESET_ACTION),
            self::FORCE_RESET_NONCE_ACTION
        );

        echo ' <a href="' . esc_url($resetUrl) . '" class="button button-secondary" style="margin-left:8px;" onclick="return confirm(\'' . esc_js('Force reset this month\'s featured vehicle? The current archive record will be moved to Trash and a replacement will be generated.') . '\');">Force Reset Current Month</a>';
    }

    /**
     * Handle the admin-only force reset request.
     *
     * SECURITY LAYERS
     * ---------------
     * 1. Capability check: only admins with manage_options may run this.
     * 2. Nonce check: verifies the request came from the intended admin UI.
     * 3. Safe redirect: returns to the CPT list screen after processing.
     *
     * IMPORTANT:
     * WordPress nonces verify intent, not authorization, so current_user_can()
     * must stay in place even though check_admin_referer() is also used.
     */
    public static function handleForceResetCurrentMonth(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(
                esc_html__('You do not have permission to reset the featured vehicle.', 'sage'),
                esc_html__('Permission denied', 'sage'),
                ['response' => 403]
            );
        }

        check_admin_referer(self::FORCE_RESET_NONCE_ACTION);

        $result = FeaturedVehicleResolver::forceResetCurrentMonthArchive();

        $status = !empty($result['success']) ? 'success' : 'error';
        $message = trim((string) ($result['message'] ?? ''));

        if ($message === '') {
            $message = $status === 'success'
                ? 'Featured vehicle force reset complete.'
                : 'Featured vehicle force reset failed.';
        }

        $redirectUrl = add_query_arg(
            [
                'post_type' => self::POST_TYPE,
                self::FORCE_RESET_NOTICE_STATUS_KEY  => $status,
                self::FORCE_RESET_NOTICE_MESSAGE_KEY => rawurlencode($message),
            ],
            admin_url('edit.php')
        );

        wp_safe_redirect($redirectUrl);
        exit;
    }

    /**
     * Render a redirect-safe admin notice after a force reset attempt.
     *
     * WHY:
     * admin-post.php should redirect after completing its work. This method reads
     * the result from query vars on the redirected list screen and displays the
     * outcome using normal WordPress notice markup.
     */
    public static function renderForceResetNotice(): void
    {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;

        if (!$screen || ($screen->post_type ?? '') !== self::POST_TYPE) {
            return;
        }

        if (empty($_GET[self::FORCE_RESET_NOTICE_STATUS_KEY])) {
            return;
        }

        $status = sanitize_key((string) wp_unslash($_GET[self::FORCE_RESET_NOTICE_STATUS_KEY]));
        $message = isset($_GET[self::FORCE_RESET_NOTICE_MESSAGE_KEY])
            ? sanitize_text_field(rawurldecode((string) wp_unslash($_GET[self::FORCE_RESET_NOTICE_MESSAGE_KEY])))
            : '';

        if ($message === '') {
            return;
        }

        $noticeClass = $status === 'success' ? 'notice notice-success' : 'notice notice-error';

        echo '<div class="' . esc_attr($noticeClass) . ' is-dismissible"><p>' . esc_html($message) . '</p></div>';
    }
    /**
     * Customize the admin list table columns for this archive CPT.
     *
     * WHY:
     * Since these posts are generated records, the list screen should show
     * the useful snapshot data directly instead of relying on opening posts.
     *
     * COLUMN STRATEGY
     * ---------------
     * We intentionally replace the generic WordPress defaults with fields that
     * matter for this archive:
     *
     * - Image
     * - Month
     * - Year
     * - Member
     * - Vehicle
     * - Vehicle ID
     * - Generated
     */
    public static function filterAdminColumns(array $columns): array
    {
        return [
            'cb'              => $columns['cb'] ?? '<input type="checkbox" />',
            'thumbnail'       => 'Image',
            'title'           => 'Month',
            'featured_year'   => 'Year',
            'member'          => 'Member',
            'vehicle'         => 'Vehicle',
            'vehicle_id'      => 'Vehicle ID',
            'generated_at'    => 'Generated',
        ];
    }

    /**
     * Register sortable admin columns.
     *
     * WHY:
     * The archive will span multiple years, so admins should be able
     * to sort by the saved numeric year value directly in the list screen.
     */
    public static function filterSortableAdminColumns(array $columns): array
    {
        $columns['featured_year'] = 'featured_year';

        return $columns;
    }

    /**
     * Apply custom sorting behavior for sortable admin columns.
     *
     * WHY:
     * WordPress needs an explicit query adjustment when sorting custom columns
     * by post meta. Since year is numeric, we sort using meta_value_num.
     */
    public static function handleAdminSorting(WP_Query $query): void
    {
        if (!is_admin() || !$query->is_main_query()) {
            return;
        }

        $postType = $query->get('post_type');
        $orderby  = $query->get('orderby');

        if ($postType !== self::POST_TYPE) {
            return;
        }

        if ($orderby === 'featured_year') {
            $query->set('meta_key', self::META_FEATURED_YEAR);
            $query->set('orderby', 'meta_value_num');
        }
    }

    /**
     * Render the custom column values for the admin list table.
     *
     * IMPORTANT
     * ---------
     * The resolver has not been added yet, so these meta keys are being
     * prepared in advance. Once the resolver writes snapshot data, these
     * columns will populate automatically.
     */
    public static function renderAdminColumn(string $column, int $postId): void
    {
        switch ($column) {
            case 'thumbnail':
                self::renderThumbnailColumn($postId);
                break;

            case 'title':
                /**
                 * We let WordPress render the title column automatically.
                 * Do nothing here.
                 */
                break;

            case 'featured_year':
                self::renderFeaturedYearColumn($postId);
                break;

            case 'member':
                self::renderMemberColumn($postId);
                break;

            case 'vehicle':
                self::renderVehicleColumn($postId);
                break;

            case 'vehicle_id':
                $vehicleId = trim((string) get_post_meta($postId, 'source_vehicle_id', true));
                echo $vehicleId !== '' ? esc_html($vehicleId) : '—';
                break;

            case 'generated_at':
                $generatedAt = trim((string) get_post_meta($postId, 'generated_at', true));
                echo $generatedAt !== '' ? esc_html($generatedAt) : '—';
                break;
        }
    }

    /**
     * Render the featured image / thumbnail column.
     *
     * WHY:
     * The archive should be visually scannable in admin, especially if these
     * entries will later feed a calendar or other design output.
     */
    protected static function renderThumbnailColumn(int $postId): void
    {
        if (has_post_thumbnail($postId)) {
            echo get_the_post_thumbnail($postId, [72, 48], [
                'style' => 'width:72px;height:48px;object-fit:cover;border-radius:8px;',
            ]);
            return;
        }

        echo '—';
    }

    /**
     * Render the year column.
     *
     * EXPECTED META
     * -------------
     * - featured_year
     *
     * FALLBACK
     * --------
     * If the dedicated numeric year has not yet been saved, try deriving it
     * from featured_month_key (example: 2026-03) so older records still show
     * something useful.
     */
    protected static function renderFeaturedYearColumn(int $postId): void
    {
        $year = (string) get_post_meta($postId, self::META_FEATURED_YEAR, true);

        if ($year === '') {
            $monthKey = trim((string) get_post_meta($postId, 'featured_month_key', true));

            if (preg_match('/^(\d{4})-\d{2}$/', $monthKey, $matches)) {
                $year = $matches[1];
            }
        }

        echo $year !== '' ? esc_html($year) : '—';
    }

    /**
     * Render the member snapshot column.
     *
     * EXPECTED META
     * -------------
     * - owner_display_name_snapshot
     * - owner_username_snapshot
     * - member_number_snapshot
     */
    protected static function renderMemberColumn(int $postId): void
    {
        $displayName  = trim((string) get_post_meta($postId, 'owner_display_name_snapshot', true));
        $username     = trim((string) get_post_meta($postId, 'owner_username_snapshot', true));
        $memberNumber = trim((string) get_post_meta($postId, 'member_number_snapshot', true));

        if ($displayName === '' && $username === '' && $memberNumber === '') {
            echo '—';
            return;
        }

        if ($displayName !== '') {
            echo '<strong>' . esc_html($displayName) . '</strong>';
        } elseif ($username !== '') {
            echo '<strong>' . esc_html($username) . '</strong>';
        }

        if ($username !== '') {
            echo '<br><span style="opacity:.75;">@' . esc_html($username) . '</span>';
        }

        if ($memberNumber !== '') {
            echo '<br><span style="opacity:.75;">Member #: ' . esc_html($memberNumber) . '</span>';
        }
    }

    /**
     * Render the vehicle snapshot column.
     *
     * EXPECTED META
     * -------------
     * - vehicle_year_snapshot
     * - vehicle_make_snapshot
     * - vehicle_model_snapshot
     * - vehicle_nickname_snapshot
     */
    protected static function renderVehicleColumn(int $postId): void
    {
        $year     = trim((string) get_post_meta($postId, 'vehicle_year_snapshot', true));
        $make     = trim((string) get_post_meta($postId, 'vehicle_make_snapshot', true));
        $model    = trim((string) get_post_meta($postId, 'vehicle_model_snapshot', true));
        $nickname = trim((string) get_post_meta($postId, 'vehicle_nickname_snapshot', true));

        $vehicleTitle = trim(implode(' ', array_filter([$year, $make, $model])));

        if ($vehicleTitle === '' && $nickname === '') {
            echo '—';
            return;
        }

        if ($vehicleTitle !== '') {
            echo '<strong>' . esc_html($vehicleTitle) . '</strong>';
        }

        if ($nickname !== '') {
            echo '<br><span style="opacity:.75;">' . esc_html($nickname) . '</span>';
        }
    }
}

/**
 * Boot immediately when this support file is required by the theme.
 *
 * PROJECT NOTE
 * ------------
 * This matches the current project preference for theme-contained support
 * files that are loaded with require_once.
 */
RegisterFeaturedVehiclePostType::boot();