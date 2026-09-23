<?php
/**
 * File: app/Support/Admin/UserRoles/AdminAccess.php
 *
 * Blocks direct navigation to wp-admin screens that operational roles must not use.
 * Menu hiding alone is not sufficient; this runs on `admin_init` for logged-in users
 * who are operational but not full administrators.
 *
 * Allowed by default: dashboard (`index.php`), own profile (`profile.php`), and
 * `admin-post.php` / `async-upload.php` (media flows). AJAX is left untouched.
 */

namespace App\Support\Admin\UserRoles;

defined('ABSPATH') || exit;

final class AdminAccess
{
    private const CPT_MINUTES = 'minutes';

    private const CPT_SPONSOR = 'sponsor';

    private const CPT_FAQ = 'faq';

    private const TAX_FAQ_CATEGORY = 'faq_category';

    private const CPT_TESTIMONIAL = 'testimonial';

    private const CPT_FEATURED_VEHICLE = 'sccc_feature_vehicle';

    private const CPT_LEADERSHIP = 'leadership';

    private const CPT_MEP_EVENTS = 'mep_events';

    private const CPT_MEP_ATTENDEES = 'mep_events_attendees';

    private const TAX_MEETING_TYPE = 'meeting_type';

    private const TAX_SPONSOR_TIER = 'sponsor_tier';

    private const TAX_LEADERSHIP_GROUP = 'leadership_group';

    /**
     * PMPro screens that must never load for restricted operational accounts.
     *
     * @var list<string>
     */
    private const PMPRO_SENSITIVE_ADMIN_PAGES = [
        'pmpro-membershiplevels',
        'pmpro-discountcodes',
        'pmpro-pagesettings',
        'pmpro-paymentsettings',
        'pmpro-securitysettings',
        'pmpro-emailsettings',
        'pmpro-emailtemplates',
        'pmpro-userfields',
        'pmpro-designsettings',
        'pmpro-advancedsettings',
        'pmpro-addons',
        'pmpro-license',
        'pmpro-wizard',
        'pmpro-updates',
    ];

    public static function register(): void
    {
        add_action('admin_init', [self::class, 'guardAdminRequest'], 2);
    }

    public static function guardAdminRequest(): void
    {
        if (defined('WP_CLI') && WP_CLI) {
            return;
        }

        if (!is_user_logged_in()) {
            return;
        }

        if (wp_doing_ajax()) {
            return;
        }

        if (defined('DOING_CRON') && DOING_CRON) {
            return;
        }

        if (current_user_can('manage_options')) {
            return;
        }

        if (!OperationalRoles::currentUserIsRestrictedOperational()) {
            return;
        }

        $pagenow = isset($GLOBALS['pagenow']) ? (string) $GLOBALS['pagenow'] : '';

        if ($pagenow === 'admin-post.php' || $pagenow === 'async-upload.php') {
            return;
        }

        if (self::isCurrentAdminRequestAllowed($pagenow)) {
            return;
        }

        wp_safe_redirect(admin_url('index.php'));
        exit;
    }

    private static function isCurrentAdminRequestAllowed(string $pagenow): bool
    {
        $user = wp_get_current_user();
        if (!$user instanceof \WP_User) {
            return false;
        }

        if ($pagenow === 'index.php' || $pagenow === '') {
            return true;
        }

        if ($pagenow === 'profile.php') {
            return true;
        }

        if (in_array($pagenow, ['load-scripts.php', 'load-styles.php'], true)) {
            return true;
        }

        if (in_array($pagenow, ['about.php', 'contribute.php', 'credits.php', 'freedoms.php'], true)) {
            return true;
        }

        if ($pagenow === 'user-edit.php') {
            $uid = isset($_GET['user_id']) ? (int) $_GET['user_id'] : 0;

            return $uid > 0 && $uid === (int) $user->ID;
        }

        if (self::isGloballyForbiddenCoreScreen($pagenow)) {
            return false;
        }

        if ($pagenow === 'admin.php') {
            $page = isset($_GET['page']) ? sanitize_key((string) wp_unslash($_GET['page'])) : '';

            if ($page === '') {
                return true;
            }

            if ($page === 'wc-admin') {
                return self::isWcAdminPathAllowed($user, self::wcAdminPathFromRequest());
            }

            if (self::isForbiddenWooOrPmproAdminPageForUser($user, $page)) {
                return false;
            }

            if (str_starts_with($page, 'gf_') || str_starts_with($page, 'gravityforms')) {
                return false;
            }

            if (str_starts_with($page, 'acf-') || $page === 'acf-settings-updates') {
                return false;
            }

            if (str_starts_with($page, 'wpseo') || $page === 'wpseo_workouts') {
                return false;
            }

            if (OperationalRoles::userHasClubSiteManagerRole($user)) {
                return true;
            }

            if ($page === 'wc-reports') {
                return $user->has_cap('view_woocommerce_reports');
            }

            if ($page === 'wc-orders' || str_starts_with($page, 'wc-orders')) {
                return $user->has_cap('edit_shop_orders');
            }

            $pmproPageCaps = [
                'pmpro-dashboard' => 'pmpro_dashboard',
                'pmpro-memberslist' => 'pmpro_memberslist',
                'pmpro-orders' => 'pmpro_orders',
                'pmpro-reports' => 'pmpro_reports',
                'pmpro-member' => 'pmpro_edit_members',
                'pmpro-subscriptions' => 'pmpro_edit_members',
            ];

            if (isset($pmproPageCaps[$page])) {
                return $user->has_cap($pmproPageCaps[$page]);
            }

            return false;
        }

        if ($pagenow === 'edit.php') {
            return self::isPostTypeListAllowed($user, self::resolvePostTypeFromEditList());
        }

        if ($pagenow === 'post.php') {
            $postId = isset($_GET['post']) ? (int) $_GET['post'] : 0;
            $pt = $postId > 0 ? get_post_type($postId) : '';

            return $pt !== '' && self::isPostTypeEditAllowed($user, $pt);
        }

        if ($pagenow === 'post-new.php') {
            $pt = isset($_GET['post_type']) ? sanitize_key((string) wp_unslash($_GET['post_type'])) : 'post';

            if ($pt === self::CPT_MEP_EVENTS) {
                return $user->has_cap(OperationalRoles::CAP_CREATE_EVENTS);
            }

            if ($pt === self::CPT_MEP_ATTENDEES) {
                return $user->has_cap(OperationalRoles::CAP_EDIT_EVENTS);
            }

            return self::isPostTypeEditAllowed($user, $pt);
        }

        if ($pagenow === 'edit-tags.php') {
            return self::isTaxonomyScreenAllowed($user);
        }

        if ($pagenow === 'upload.php' || $pagenow === 'media-new.php') {
            return self::isMediaLibraryAllowed($user);
        }

        if (self::isFallbackPageAllowed($user, $pagenow)) {
            return true;
        }

        return false;
    }

    private static function isGloballyForbiddenCoreScreen(string $pagenow): bool
    {
        $denyExact = [
            'themes.php',
            'site-editor.php',
            'theme-install.php',
            'theme-editor.php',
            'plugins.php',
            'plugin-install.php',
            'plugin-editor.php',
            'tools.php',
            'import.php',
            'export.php',
            'export-personal-data.php',
            'erase-personal-data.php',
            'users.php',
            'user-new.php',
            'site-health.php',
            'update-core.php',
            'options-general.php',
            'options-writing.php',
            'options-reading.php',
            'options-discussion.php',
            'options-media.php',
            'options-permalink.php',
            'options-privacy.php',
            'nav-menus.php',
            'widgets.php',
            'customize.php',
            'edit-comments.php',
        ];

        if (in_array($pagenow, $denyExact, true)) {
            return true;
        }

        if (str_starts_with($pagenow, 'options-')) {
            return true;
        }

        return false;
    }

    /**
     * WooCommerce + PMPro admin pages that restricted operational roles must not load.
     * Club Site Manager bypasses these (full Woo + PMPro); GF / ACF / Yoast stay fenced elsewhere.
     */
    private static function isForbiddenWooOrPmproAdminPageForUser(\WP_User $user, string $page): bool
    {
        if (OperationalRoles::userHasClubSiteManagerRole($user)) {
            return false;
        }

        if ($page === 'wc-settings' || $page === 'wc-status' || $page === 'wc-addons') {
            return true;
        }

        return in_array($page, self::PMPRO_SENSITIVE_ADMIN_PAGES, true);
    }

    private static function wcAdminPathFromRequest(): string
    {
        if (!isset($_GET['path'])) {
            return '';
        }

        return (string) wp_unslash($_GET['path']);
    }

    private static function isWcAdminPathAllowed(\WP_User $user, string $path): bool
    {
        if (OperationalRoles::userHasClubSiteManagerRole($user)) {
            return true;
        }

        $path = '/' . ltrim($path, '/');

        if ($path === '/' || $path === '') {
            return self::userMayAccessWooCommerceAdminSurfaces($user);
        }

        $blockedPrefixes = [
            '/settings',
            '/extensions',
            '/marketing',
            '/wc-pay',
            '/wc-subscriptions',
            '/coupons',
            '/setup-wizard',
        ];

        foreach ($blockedPrefixes as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return false;
            }
        }

        if (str_contains($path, '/analytics/settings')) {
            return false;
        }

        if (str_starts_with($path, '/products')) {
            if (self::userHasAnyRole($user, [OperationalRoles::ROLE_TREASURER, OperationalRoles::ROLE_MEMBERSHIP_MANAGER, OperationalRoles::ROLE_SECRETARY, OperationalRoles::ROLE_REPORT_VIEWER])) {
                return false;
            }

            return self::userMayAccessWooCommerceAdminSurfaces($user);
        }

        if (str_starts_with($path, '/orders') || str_starts_with($path, '/analytics') || str_starts_with($path, '/customers')) {
            return self::userMayAccessWooCommerceAdminSurfaces($user);
        }

        return self::userMayAccessWooCommerceAdminSurfaces($user);
    }

    private static function userMayAccessWooCommerceAdminSurfaces(\WP_User $user): bool
    {
        if (self::userHasAnyRole($user, [OperationalRoles::ROLE_SITE_MANAGER, OperationalRoles::ROLE_TREASURER, OperationalRoles::ROLE_EVENT_MANAGER])) {
            return true;
        }

        return $user->has_cap('edit_others_shop_orders') || $user->has_cap('manage_woocommerce');
    }

    private static function resolvePostTypeFromEditList(): string
    {
        if (isset($_GET['post_type'])) {
            return sanitize_key((string) wp_unslash($_GET['post_type']));
        }

        global $typenow;

        return is_string($typenow) ? sanitize_key($typenow) : 'post';
    }

    private static function isPostTypeListAllowed(\WP_User $user, string $postType): bool
    {
        if ($postType === '' || $postType === 'post') {
            return self::userHasAnyRole($user, [OperationalRoles::ROLE_SITE_MANAGER, OperationalRoles::ROLE_CONTENT_MANAGER]);
        }

        if ($postType === 'page') {
            return self::userHasAnyRole($user, [OperationalRoles::ROLE_SITE_MANAGER, OperationalRoles::ROLE_CONTENT_MANAGER]);
        }

        if ($postType === self::CPT_MEP_EVENTS || $postType === self::CPT_MEP_ATTENDEES) {
            return $user->has_cap(OperationalRoles::CAP_VIEW_EVENTS);
        }

        return self::isPostTypeEditAllowed($user, $postType);
    }

    private static function isPostTypeEditAllowed(\WP_User $user, string $postType): bool
    {
        if ($postType === 'post') {
            return self::userHasAnyRole($user, [OperationalRoles::ROLE_SITE_MANAGER, OperationalRoles::ROLE_CONTENT_MANAGER]);
        }

        if ($postType === 'page') {
            return self::userHasAnyRole($user, [OperationalRoles::ROLE_SITE_MANAGER, OperationalRoles::ROLE_CONTENT_MANAGER]);
        }

        if ($postType === self::CPT_MINUTES
            || $postType === self::CPT_SPONSOR
            || $postType === self::CPT_TESTIMONIAL
            || $postType === self::CPT_LEADERSHIP) {
            return self::userHasAnyRole($user, OperationalRoles::sharedClubContentOperationalRoles());
        }

        if ($postType === self::CPT_FAQ) {
            return $user->has_cap(OperationalRoles::CAP_MANAGE_FAQ);
        }

        if ($postType === self::CPT_FEATURED_VEHICLE) {
            return self::userHasAnyRole($user, [OperationalRoles::ROLE_SITE_MANAGER]);
        }

        if ($postType === self::CPT_MEP_EVENTS || $postType === self::CPT_MEP_ATTENDEES) {
            return $user->has_cap(OperationalRoles::CAP_EDIT_EVENTS);
        }

        if ($postType === 'product') {
            return self::userHasAnyRole($user, [OperationalRoles::ROLE_SITE_MANAGER, OperationalRoles::ROLE_EVENT_MANAGER]);
        }

        if ($postType === 'shop_order') {
            return self::userHasAnyRole($user, [OperationalRoles::ROLE_SITE_MANAGER, OperationalRoles::ROLE_TREASURER, OperationalRoles::ROLE_EVENT_MANAGER]);
        }

        return false;
    }

    private static function isTaxonomyScreenAllowed(\WP_User $user): bool
    {
        $taxonomy = isset($_GET['taxonomy']) ? sanitize_key((string) wp_unslash($_GET['taxonomy'])) : '';
        $postType = isset($_GET['post_type']) ? sanitize_key((string) wp_unslash($_GET['post_type'])) : '';

        if ($taxonomy === self::TAX_MEETING_TYPE && $postType === self::CPT_MINUTES) {
            return self::userHasAnyRole($user, OperationalRoles::sharedClubContentOperationalRoles());
        }

        if ($taxonomy === self::TAX_SPONSOR_TIER && $postType === self::CPT_SPONSOR) {
            return self::userHasAnyRole($user, OperationalRoles::sharedClubContentOperationalRoles());
        }

        if ($taxonomy === self::TAX_LEADERSHIP_GROUP && $postType === self::CPT_LEADERSHIP) {
            return self::userHasAnyRole($user, OperationalRoles::sharedClubContentOperationalRoles());
        }

        if ($taxonomy === self::TAX_FAQ_CATEGORY && $postType === self::CPT_FAQ) {
            return $user->has_cap(OperationalRoles::CAP_MANAGE_FAQ);
        }

        if ($taxonomy !== '' && str_starts_with($taxonomy, 'product_')) {
            return self::userHasAnyRole($user, [OperationalRoles::ROLE_SITE_MANAGER]);
        }

        return false;
    }

    private static function isMediaLibraryAllowed(\WP_User $user): bool
    {
        if (!$user->has_cap('upload_files')) {
            return false;
        }

        return self::userHasAnyRole($user, [
            OperationalRoles::ROLE_SITE_MANAGER,
            OperationalRoles::ROLE_CONTENT_MANAGER,
            OperationalRoles::ROLE_SECRETARY,
            OperationalRoles::ROLE_EVENT_MANAGER,
            OperationalRoles::ROLE_MEMBERSHIP_MANAGER,
            OperationalRoles::ROLE_TREASURER,
        ]);
    }

    /**
     * Screens that are not explicitly modelled but are required by vendor plugins.
     */
    private static function isFallbackPageAllowed(\WP_User $user, string $pagenow): bool
    {
        if ($pagenow === 'revision.php') {
            return true;
        }

        return false;
    }

    /**
     * @param  list<string>  $slugs
     */
    private static function userHasAnyRole(\WP_User $user, array $slugs): bool
    {
        $roles = is_array($user->roles) ? $user->roles : [];
        foreach ($slugs as $slug) {
            if (in_array($slug, $roles, true)) {
                return true;
            }
        }

        return false;
    }
}
