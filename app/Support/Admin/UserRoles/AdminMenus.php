<?php
/**
 * File: app/Support/Admin/UserRoles/AdminMenus.php
 *
 * Removes wp-admin menu entries that restricted operational roles should not see.
 * This is UX hardening only; `AdminAccess.php` enforces the same boundaries on
 * direct URL access.
 */

namespace App\Support\Admin\UserRoles;

defined('ABSPATH') || exit;

final class AdminMenus
{
    public static function register(): void
    {
        add_action('admin_menu', [self::class, 'onAdminMenu'], 9999);
    }

    public static function onAdminMenu(): void
    {
        if (!is_user_logged_in()) {
            return;
        }

        if (current_user_can('manage_options')) {
            return;
        }

        if (!OperationalRoles::currentUserIsRestrictedOperational()) {
            return;
        }

        $user = wp_get_current_user();
        if (!$user instanceof \WP_User) {
            return;
        }

        self::ensureMepEventsAllEventsSubmenu($user);

        self::removeCoreInfrastructureMenus();
        self::removeThirdPartyInfrastructureMenus();

        if (!OperationalRoles::userHasClubSiteManagerRole($user)) {
            self::removeWooCommerceSensitiveSubmenus();
            self::removePmproSensitiveSubmenus();
        }

        self::pruneOperationalRoleMenus($user);
    }

    /**
     * Mage EventPress removes the default CPT list when `mep_event_list_page_style` is `new`,
     * leaving only the "Event Lists" screen which is registered with `manage_woocommerce`.
     * Operational users with `sccc_view_events` need the standard `edit.php?post_type=mep_events`
     * entry so they can open the WordPress events list.
     */
    private static function ensureMepEventsAllEventsSubmenu(\WP_User $user): void
    {
        if (!post_type_exists('mep_events')) {
            return;
        }

        if (!$user->has_cap(OperationalRoles::CAP_VIEW_EVENTS)) {
            return;
        }

        global $submenu;

        $parent = 'edit.php?post_type=mep_events';

        if (!isset($submenu[$parent]) || !is_array($submenu[$parent])) {
            return;
        }

        foreach ($submenu[$parent] as $item) {
            if (is_array($item) && ($item[2] ?? '') === $parent) {
                return;
            }
        }

        add_submenu_page(
            $parent,
            __('Events', 'sccc'),
            __('All Events', 'sccc'),
            OperationalRoles::CAP_VIEW_EVENTS,
            $parent,
            '',
            0
        );

        if (!isset($submenu[$parent]) || !is_array($submenu[$parent])) {
            return;
        }

        $listItem = null;
        foreach ($submenu[$parent] as $key => $item) {
            if (is_array($item) && ($item[2] ?? '') === $parent) {
                $listItem = $item;
                unset($submenu[$parent][$key]);
                break;
            }
        }

        if ($listItem !== null) {
            array_unshift($submenu[$parent], $listItem);
        }
    }

    private static function removeCoreInfrastructureMenus(): void
    {
        remove_menu_page('themes.php');
        remove_menu_page('plugins.php');
        remove_menu_page('tools.php');
        remove_menu_page('options-general.php');
        remove_menu_page('users.php');
    }

    private static function removeThirdPartyInfrastructureMenus(): void
    {
        remove_menu_page('edit.php?post_type=acf-field-group');

        if (class_exists('\GFForms')) {
            foreach (['gf_edit_forms', 'gf_new_form', 'gf_entries', 'gf_settings', 'gf_export', 'gf_addons', 'gf_system_status', 'gf_help'] as $slug) {
                remove_menu_page($slug);
            }
        }

        foreach (['wpseo_dashboard', 'wpseo_workouts'] as $slug) {
            remove_menu_page($slug);
        }
    }

    private static function removeWooCommerceSensitiveSubmenus(): void
    {
        remove_submenu_page('woocommerce', 'wc-settings');
        remove_submenu_page('woocommerce', 'wc-status');
        remove_submenu_page('woocommerce', 'wc-addons');
    }

    private static function removePmproSensitiveSubmenus(): void
    {
        $items = [
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

        foreach ($items as $slug) {
            remove_submenu_page('pmpro-dashboard', $slug);
            remove_submenu_page('admin.php', $slug);
        }
    }

    private static function pruneOperationalRoleMenus(\WP_User $user): void
    {
        if (self::userHasAnyRole($user, [OperationalRoles::ROLE_SECRETARY])) {
            remove_menu_page('edit.php');
        }

        if (self::userHasAnyRole($user, [OperationalRoles::ROLE_EVENT_MANAGER])) {
            remove_menu_page('edit.php');
        }

        if (self::userHasAnyRole($user, [OperationalRoles::ROLE_MEMBERSHIP_MANAGER])) {
            remove_menu_page('edit.php');
            remove_submenu_page('pmpro-dashboard', 'pmpro-orders');
            remove_submenu_page('pmpro-dashboard', 'pmpro-reports');
        }

        if (self::userHasAnyRole($user, [OperationalRoles::ROLE_CONTENT_MANAGER])) {
            remove_menu_page('edit.php?post_type=product');
        }

        if (self::userHasAnyRole($user, [OperationalRoles::ROLE_TREASURER])) {
            remove_menu_page('edit.php?post_type=product');
        }
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
