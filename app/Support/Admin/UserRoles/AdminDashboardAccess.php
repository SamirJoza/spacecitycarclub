<?php
/**
 * File: app/Support/Admin/UserRoles/AdminDashboardAccess.php
 *
 * Centralises “who may load wp-admin / see the admin bar” rules for Space City
 * Car Club operational accounts.
 *
 * Goals for this first deliverable
 * - Block “pure member portal” accounts (`subscriber` + `sccc_member` [+ optional
 *   `customer`] only, with no operational marker caps) from wp-admin and the
 *   admin bar, including `profile.php` (no wp-admin surface for regular members).
 * - Allow accounts that hold `sccc_view_dashboard` (operational roles) to reach
 *   wp-admin and keep the admin bar enabled on the front end.
 * - Cooperate with WooCommerce’s “prevent admin access” behaviour so operational
 *   staff are not bounced out of `/wp-admin/` solely because they also carry the
 *   `customer` role.
 * - After WooCommerce (or core) login, send operational staff to **My Account** by
 *   default so wp-admin is optional; if `redirect_to` explicitly targets wp-admin
 *   (or another URL), that destination is preserved.
 * - Mitigate edge/CDN full-page caches that incorrectly cache a 302 from
 *   `/wp-admin/` to My Account: WooCommerce bypass filters run at maximum priority,
 *   and the front-end admin bar “Dashboard” link gets a cache-busting query arg for staff.
 *
 * Explicit non-goals
 * - Menu hiding and direct URL enforcement live in `AdminMenus.php` and
 *   `AdminAccess.php` respectively; this class only handles entry / admin-bar
 *   visibility vs pure members.
 * - No changes to PMPro membership state, member numbers, timestamps, or
 *   `MemberRoleSync.php`.
 */

namespace App\Support\Admin\UserRoles;

defined('ABSPATH') || exit;

final class AdminDashboardAccess
{
    public static function register(): void
    {
        add_action('admin_init', [self::class, 'maybeRedirectPureMembersFromAdmin'], 1);

        /**
         * Run late so WooCommerce `wc_disable_admin_bar` (priority 10) and user
         * preference (`show_admin_bar_front` meta) cannot leave the bar hidden for
         * operational accounts.
         */
        add_filter('show_admin_bar', [self::class, 'filterShowAdminBar'], 999);

        if (class_exists('\WooCommerce')) {
            /*
             * Maximum priority so another plugin cannot re-enable WooCommerce’s
             * redirect of “non-editor” users to My Account after our bypass.
             */
            add_filter('woocommerce_prevent_admin_access', [self::class, 'filterWooPreventAdminAccess'], PHP_INT_MAX, 2);
            add_filter('woocommerce_disable_admin_bar', [self::class, 'filterWooDisableAdminBar'], PHP_INT_MAX, 2);
            /*
             * WooCommerce login: operational staff default to My Account; explicit
             * wp-admin (or other) redirects from WooCommerce are left intact.
             */
            add_filter('woocommerce_login_redirect', [self::class, 'filterWooCommerceLoginRedirect'], PHP_INT_MAX, 2);
        }

        add_filter('login_redirect', [self::class, 'filterLoginRedirect'], PHP_INT_MAX, 3);

        add_action('admin_bar_menu', [self::class, 'cacheBustAdminBarDashboardLink'], 99999);
    }

    /**
     * Redirect pure member-portal users away from wp-admin screens.
     */
    public static function maybeRedirectPureMembersFromAdmin(): void
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

        $pagenow = $GLOBALS['pagenow'] ?? '';
        if ($pagenow === 'admin-post.php' || $pagenow === 'async-upload.php') {
            return;
        }

        $user = wp_get_current_user();
        if (!$user instanceof \WP_User || !$user->exists()) {
            return;
        }

        if (!OperationalRoles::isPureMemberPortalUser($user)) {
            return;
        }

        wp_safe_redirect(self::frontendRedirectDestination());
        exit;
    }

    /**
     * On the front end only, append a query arg to the admin bar “Dashboard” link so
     * edge/CDN caches cannot reuse a stale redirect response for `/wp-admin/`.
     */
    public static function cacheBustAdminBarDashboardLink(\WP_Admin_Bar $bar): void
    {
        if (is_admin()) {
            return;
        }

        if (!is_user_logged_in()) {
            return;
        }

        if (!OperationalRoles::userHasOperationalBackendAccess((int) get_current_user_id())) {
            return;
        }

        $node = $bar->get_node('dashboard');
        if (!is_array($node) || empty($node['href'])) {
            return;
        }

        $href = (string) $node['href'];
        if (stripos($href, 'wp-admin') === false) {
            return;
        }

        $node['href'] = add_query_arg('sccc_cb', (string) wp_rand(), $href);
        $bar->add_node($node);
    }

    /**
     * Ensure operational users keep the admin bar on the front end while pure
     * members stay front-end clean.
     *
     * @param  bool|string  $show
     * @return bool|string
     */
    public static function filterShowAdminBar($show)
    {
        if (!is_user_logged_in()) {
            return $show;
        }

        $user = wp_get_current_user();
        if (!$user instanceof \WP_User) {
            return $show;
        }

        if (OperationalRoles::userHasOperationalBackendAccess((int) $user->ID)) {
            return true;
        }

        if (OperationalRoles::isPureMemberPortalUser($user)) {
            return false;
        }

        return (bool) $show;
    }

    /**
     * WooCommerce: do not force operational staff away from wp-admin solely
     * because they are also customers.
     *
     * @param  bool     $prevent
     * @param  int|null $userId  Optional; omitted on some WooCommerce code paths (e.g. storefront admin bar check).
     */
    public static function filterWooPreventAdminAccess($prevent, $userId = null): bool
    {
        $userId = self::resolveWooFilterUserId($userId);
        if ($userId <= 0) {
            return (bool) $prevent;
        }

        if (OperationalRoles::userHasOperationalBackendAccess($userId)) {
            return false;
        }

        return (bool) $prevent;
    }

    /**
     * WooCommerce: allow the admin bar for operational users even when Woo would
     * normally suppress it for shoppers.
     *
     * @param  bool     $disabled
     * @param  int|null $userId  Optional; WooCommerce sometimes applies this filter with a single argument only.
     */
    public static function filterWooDisableAdminBar($disabled, $userId = null): bool
    {
        $userId = self::resolveWooFilterUserId($userId);
        if ($userId <= 0) {
            return (bool) $disabled;
        }

        if (OperationalRoles::userHasOperationalBackendAccess($userId)) {
            return false;
        }

        return (bool) $disabled;
    }

    /**
     * After WooCommerce login, send operational staff to My Account unless Woo (or
     * the request) already chose a concrete destination such as wp-admin.
     *
     * @param  string|\WP_Error  $redirect
     * @param  \WP_User|\WP_Error  $user
     * @return string|\WP_Error
     */
    public static function filterWooCommerceLoginRedirect($redirect, $user)
    {
        if (is_wp_error($redirect)) {
            return $redirect;
        }

        if (! $user instanceof \WP_User) {
            return $redirect;
        }

        if (! OperationalRoles::userHasOperationalBackendAccess((int) $user->ID)) {
            return $redirect;
        }

        $myAccount = self::operationalPreferredLoginLandingUrl();

        if (! is_string($redirect) || trim($redirect) === '') {
            return $myAccount;
        }

        $validated = wp_validate_redirect($redirect, '');
        if ($validated === '') {
            return $myAccount;
        }

        if (str_contains($validated, '/wp-admin') || str_contains($validated, 'admin.php')) {
            return $redirect;
        }

        if (function_exists('wc_get_page_permalink')) {
            $wcMy = wc_get_page_permalink('myaccount');
            if (is_string($wcMy) && $wcMy !== '' && self::urlsMatchUnslashed($validated, $wcMy)) {
                return $redirect;
            }
        }

        return $redirect;
    }

    /**
     * Core / wp-login.php: when no `redirect_to` query parameter is present, WordPress
     * defaults to {@see admin_url()}; send operational staff to My Account unless they
     * explicitly requested another URL.
     *
     * @param  mixed  $redirectTo
     * @param  mixed  $requestedRedirectTo
     * @param  \WP_User|\WP_Error  $user
     */
    public static function filterLoginRedirect($redirectTo, $requestedRedirectTo, $user): string
    {
        $redirectTo = is_string($redirectTo) ? $redirectTo : '';
        $requestedRedirectTo = is_string($requestedRedirectTo) ? $requestedRedirectTo : '';

        if (! $user instanceof \WP_User) {
            return $redirectTo;
        }

        if (! OperationalRoles::userHasOperationalBackendAccess((int) $user->ID)) {
            return $redirectTo;
        }

        if ($requestedRedirectTo !== '') {
            return $redirectTo;
        }

        $myAccount = self::operationalPreferredLoginLandingUrl();

        if (self::isDefaultAdminLandingUrl($redirectTo)) {
            return wp_validate_redirect($myAccount, home_url('/'));
        }

        if ($myAccount !== '' && self::urlsMatchUnslashed($redirectTo, $myAccount)) {
            return $redirectTo;
        }

        return $redirectTo;
    }

    private static function operationalPreferredLoginLandingUrl(): string
    {
        if (function_exists('wc_get_page_permalink')) {
            $my = wc_get_page_permalink('myaccount');
            if (is_string($my) && $my !== '') {
                return $my;
            }
        }

        return home_url('/my-account/');
    }

    private static function isDefaultAdminLandingUrl(string $url): bool
    {
        $normalized = untrailingslashit(wp_validate_redirect($url, ''));
        if ($normalized === '') {
            return true;
        }

        $candidates = array_filter([
            untrailingslashit(admin_url()),
            untrailingslashit(admin_url('index.php')),
            function_exists('network_admin_url') ? untrailingslashit(network_admin_url()) : '',
            function_exists('user_admin_url') ? untrailingslashit(user_admin_url()) : '',
        ]);

        foreach ($candidates as $candidate) {
            if ($candidate !== '' && $normalized === $candidate) {
                return true;
            }
        }

        return false;
    }

    private static function urlsMatchUnslashed(string $a, string $b): bool
    {
        return untrailingslashit($a) === untrailingslashit($b);
    }

    /**
     * WooCommerce filter callbacks may omit the user id; use the logged-in user when safe.
     */
    private static function resolveWooFilterUserId($userId): int
    {
        $userId = (int) $userId;
        if ($userId > 0) {
            return $userId;
        }

        return is_user_logged_in() ? (int) get_current_user_id() : 0;
    }

    private static function frontendRedirectDestination(): string
    {
        $url = home_url('/');

        /**
         * Filters where pure member-portal users are sent when wp-admin is blocked.
         *
         * @param  string  $url
         */
        return (string) apply_filters('sccc_pure_member_admin_redirect', $url);
    }
}
