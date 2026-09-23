<?php
/**
 * File: app/Support/Admin/UserRoles/OperationalRoles.php
 *
 * Registers and maintains SCCC operational WordPress roles (separate from
 * `sccc_member`, which remains PMPro-controlled).
 *
 * - Merges missing capabilities onto existing roles (safe upgrades).
 * - Ensures `administrator` receives SCCC marker caps so dashboard widgets that
 *   check `current_user_can('sccc_use_dashboard_widgets')` still work for full
 *   admins without requiring `manage_options` in widget code.
 * - Helpers for “pure member portal”, operational access, and admin-bar priority.
 * - Maps Minutes, Sponsors, Testimonials, and Leadership CPTs to
 *   `sccc_edit_shared_club_content`, Featured Vehicles to
 *   `sccc_manage_featured_vehicles`, and FAQ (`faq` + `faq_category`) to
 *   `sccc_manage_faq`, via `register_post_type_args` / `register_taxonomy_args`
 *   so staff can be granted club CPT access without blanket `edit_posts`.
 *
 * Non-goals: no PMPro sync, no `set_role()`, no customer/sccc_member assignment.
 */

namespace App\Support\Admin\UserRoles;

defined('ABSPATH') || exit;

final class OperationalRoles
{
    public const ROLE_SITE_MANAGER = 'sccc_site_manager';

    public const ROLE_SECRETARY = 'sccc_secretary';

    public const ROLE_MEMBERSHIP_MANAGER = 'sccc_membership_manager';

    public const ROLE_TREASURER = 'sccc_treasurer';

    public const ROLE_EVENT_MANAGER = 'sccc_event_manager';

    public const ROLE_CONTENT_MANAGER = 'sccc_content_manager';

    public const ROLE_REPORT_VIEWER = 'sccc_report_viewer';

    public const CAP_VIEW_DASHBOARD = 'sccc_view_dashboard';

    public const CAP_USE_DASHBOARD_WIDGETS = 'sccc_use_dashboard_widgets';

    public const CAP_VIEW_REPORTS = 'sccc_view_reports';

    public const CAP_MANAGE_CONTENT = 'sccc_manage_content';

    public const CAP_MANAGE_MINUTES = 'sccc_manage_minutes';

    public const CAP_MANAGE_MEMBERS = 'sccc_manage_members';

    public const CAP_VIEW_FINANCE = 'sccc_view_finance';

    public const CAP_MANAGE_EVENTS = 'sccc_manage_events';

    public const CAP_MANAGE_SHOP = 'sccc_manage_shop';

    /**
     * Gates Minutes, Sponsors, Testimonials, and Leadership (see sharedClubContentOperationalRoles()).
     */
    public const CAP_EDIT_SHARED_CLUB_CONTENT = 'sccc_edit_shared_club_content';

    /**
     * Gates Featured Vehicles CPT (`sccc_feature_vehicle`) — site manager + administrators only.
     */
    public const CAP_MANAGE_FEATURED_VEHICLES = 'sccc_manage_featured_vehicles';

    /**
     * Gates FAQ CPT (`faq`) and `faq_category` taxonomy — all operational roles.
     */
    public const CAP_MANAGE_FAQ = 'sccc_manage_faq';

    /**
     * Mage EventPress: list / read all events in wp-admin (all operational roles).
     */
    public const CAP_VIEW_EVENTS = 'sccc_view_events';

    /**
     * Mage EventPress: edit or trash existing events and attendees (not report viewer).
     */
    public const CAP_EDIT_EVENTS = 'sccc_edit_events';

    /**
     * Mage EventPress: create new `mep_events` posts (club site manager, event manager, treasurer only).
     */
    public const CAP_CREATE_EVENTS = 'sccc_create_events';

    /**
     * Highest operational role wins for admin-bar colour (administrator handled separately).
     *
     * @var list<string>
     */
    private const OPERATIONAL_BAR_PRIORITY = [
        self::ROLE_SITE_MANAGER,
        self::ROLE_TREASURER,
        self::ROLE_MEMBERSHIP_MANAGER,
        self::ROLE_SECRETARY,
        self::ROLE_EVENT_MANAGER,
        self::ROLE_CONTENT_MANAGER,
        self::ROLE_REPORT_VIEWER,
    ];

    /**
     * @var array<string, string>
     */
    private const ROLE_LABELS = [
        self::ROLE_SITE_MANAGER => 'Club Site Manager',
        self::ROLE_SECRETARY => 'Club Secretary',
        self::ROLE_MEMBERSHIP_MANAGER => 'Membership Manager',
        self::ROLE_TREASURER => 'Treasurer',
        self::ROLE_EVENT_MANAGER => 'Event Manager',
        self::ROLE_CONTENT_MANAGER => 'Content Manager',
        self::ROLE_REPORT_VIEWER => 'Report Viewer',
    ];

    /**
     * Marker capabilities granted to `administrator` so theme checks stay consistent.
     *
     * @var list<string>
     */
    private const ADMINISTRATOR_MARKER_CAPS = [
        self::CAP_VIEW_DASHBOARD,
        self::CAP_USE_DASHBOARD_WIDGETS,
        self::CAP_VIEW_REPORTS,
        self::CAP_MANAGE_CONTENT,
        self::CAP_MANAGE_MINUTES,
        self::CAP_MANAGE_MEMBERS,
        self::CAP_VIEW_FINANCE,
        self::CAP_MANAGE_EVENTS,
        self::CAP_MANAGE_SHOP,
        self::CAP_EDIT_SHARED_CLUB_CONTENT,
        self::CAP_MANAGE_FEATURED_VEHICLES,
        self::CAP_MANAGE_FAQ,
        self::CAP_VIEW_EVENTS,
        self::CAP_EDIT_EVENTS,
        self::CAP_CREATE_EVENTS,
    ];

    /**
     * Operational roles that may edit Minutes, Sponsors, Testimonials, and Leadership.
     *
     * @return list<string>
     */
    public static function sharedClubContentOperationalRoles(): array
    {
        return [
            self::ROLE_SITE_MANAGER,
            self::ROLE_SECRETARY,
            self::ROLE_MEMBERSHIP_MANAGER,
            self::ROLE_TREASURER,
            self::ROLE_EVENT_MANAGER,
            self::ROLE_CONTENT_MANAGER,
        ];
    }

    public static function userHasClubSiteManagerRole(\WP_User $user): bool
    {
        return in_array(self::ROLE_SITE_MANAGER, (array) $user->roles, true);
    }

    public static function register(): void
    {
        add_filter('register_post_type_args', [self::class, 'filterRegisterPostTypeArgs'], 5, 2);
        add_filter('register_taxonomy_args', [self::class, 'filterRegisterTaxonomyArgs'], 5, 3);
        add_action('after_setup_theme', [self::class, 'registerRoles'], 1);
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    public static function filterRegisterPostTypeArgs(array $args, string $postType): array
    {
        $shared = ['minutes', 'sponsor', 'testimonial', 'leadership', 'thank_you'];
        if (in_array($postType, $shared, true)) {
            $args['capabilities'] = array_merge(
                self::mapPrimitiveCapsTo(self::CAP_EDIT_SHARED_CLUB_CONTENT),
                (array) ($args['capabilities'] ?? [])
            );
            /*
             * Do not use map_meta_cap here: mapping edit_post / read_post / delete_post
             * to the same primitive cap registers a single reverse entry in
             * $post_type_meta_caps, so checks like current_user_can('sccc_edit_shared_club_content')
             * can recurse into map_meta_cap('delete_post') without a post ID (WP 6.1+).
             */
            $args['map_meta_cap'] = false;
        }

        if ($postType === 'sccc_feature_vehicle') {
            $args['capabilities'] = array_merge(
                self::mapPrimitiveCapsTo(self::CAP_MANAGE_FEATURED_VEHICLES),
                (array) ($args['capabilities'] ?? [])
            );
            $args['map_meta_cap'] = false;
        }

        if ($postType === 'faq') {
            /*
             * Same rationale as shared club CPTs: avoid map_meta_cap collisions when
             * all primitive caps map to one marker (`sccc_manage_faq`).
             */
            $args['capabilities'] = array_merge(
                (array) ($args['capabilities'] ?? []),
                self::mapPrimitiveCapsTo(self::CAP_MANAGE_FAQ)
            );
            $args['map_meta_cap'] = false;
        }

        if ($postType === 'mep_events') {
            $args['capabilities'] = array_merge(
                self::mageEventPostTypeCapabilities(),
                (array) ($args['capabilities'] ?? [])
            );
            $args['map_meta_cap'] = false;
        }

        if ($postType === 'mep_events_attendees') {
            $args['capabilities'] = array_merge(
                self::mageEventAttendeePostTypeCapabilities(),
                (array) ($args['capabilities'] ?? [])
            );
            $args['map_meta_cap'] = false;
        }

        return $args;
    }

    /**
     * @param  array<string, mixed>  $args
     * @param  list<string>  $objectType
     * @return array<string, mixed>
     */
    public static function filterRegisterTaxonomyArgs(array $args, string $taxonomy, $objectType): array
    {
        unset($objectType);

        if ($taxonomy === 'faq_category') {
            $faqCap = self::CAP_MANAGE_FAQ;
            $args['capabilities'] = array_merge(
                (array) ($args['capabilities'] ?? []),
                [
                    'manage_terms' => $faqCap,
                    'edit_terms' => $faqCap,
                    'delete_terms' => $faqCap,
                    'assign_terms' => $faqCap,
                ]
            );

            return $args;
        }

        $sharedTaxonomies = ['meeting_type', 'sponsor_tier', 'leadership_group'];
        if (!in_array($taxonomy, $sharedTaxonomies, true)) {
            return $args;
        }

        $caps = (array) ($args['capabilities'] ?? []);
        $caps['assign_terms'] = self::CAP_EDIT_SHARED_CLUB_CONTENT;
        $args['capabilities'] = $caps;

        return $args;
    }

    /**
     * @return array<string, string>
     */
    private static function mapPrimitiveCapsTo(string $capability): array
    {
        $keys = [
            'edit_post',
            'read_post',
            'delete_post',
            'edit_posts',
            'edit_others_posts',
            'delete_posts',
            'publish_posts',
            'read_private_posts',
            'delete_private_posts',
            'delete_published_posts',
            'delete_others_posts',
            'edit_private_posts',
            'edit_published_posts',
            'create_posts',
        ];

        $out = [];
        foreach ($keys as $key) {
            $out[$key] = $capability;
        }

        return $out;
    }

    /**
     * @return array<string, string>
     */
    private static function mageEventPostTypeCapabilities(): array
    {
        $v = self::CAP_VIEW_EVENTS;
        $e = self::CAP_EDIT_EVENTS;
        $c = self::CAP_CREATE_EVENTS;

        return [
            'edit_post' => $e,
            'read_post' => $v,
            'delete_post' => $e,
            'edit_posts' => $v,
            'edit_others_posts' => $v,
            'delete_posts' => $e,
            'publish_posts' => $e,
            'read_private_posts' => $v,
            'delete_private_posts' => $e,
            'delete_published_posts' => $e,
            'delete_others_posts' => $e,
            'edit_private_posts' => $e,
            'edit_published_posts' => $e,
            'create_posts' => $c,
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function mageEventAttendeePostTypeCapabilities(): array
    {
        $v = self::CAP_VIEW_EVENTS;
        $e = self::CAP_EDIT_EVENTS;

        return [
            'edit_post' => $e,
            'read_post' => $v,
            'delete_post' => $e,
            'edit_posts' => $v,
            'edit_others_posts' => $v,
            'delete_posts' => $e,
            'publish_posts' => $e,
            'read_private_posts' => $v,
            'delete_private_posts' => $e,
            'delete_published_posts' => $e,
            'delete_others_posts' => $e,
            'edit_private_posts' => $e,
            'edit_published_posts' => $e,
            'create_posts' => $e,
        ];
    }

    public static function registerRoles(): void
    {
        if (!function_exists('get_role') || !function_exists('add_role')) {
            return;
        }

        foreach (self::roleDefinitions() as $slug => $definition) {
            if (!(get_role($slug) instanceof \WP_Role)) {
                add_role($slug, $definition['label'], $definition['caps']);
            }

            $role = get_role($slug);
            if (!$role instanceof \WP_Role) {
                continue;
            }

            foreach ($definition['caps'] as $cap => $granted) {
                if (!$granted) {
                    continue;
                }

                $cap = (string) $cap;
                if (!$role->has_cap($cap)) {
                    $role->add_cap($cap);
                }
            }
        }

        self::syncMembershipManagerEventCreateCap();

        self::ensureAdministratorMarkerCaps();
    }

    /**
     * `registerRoles()` only adds capabilities; remove legacy caps no longer in role definitions.
     */
    private static function syncMembershipManagerEventCreateCap(): void
    {
        $role = get_role(self::ROLE_MEMBERSHIP_MANAGER);
        if (!$role instanceof \WP_Role) {
            return;
        }

        if ($role->has_cap(self::CAP_CREATE_EVENTS)) {
            $role->remove_cap(self::CAP_CREATE_EVENTS);
        }
    }

    private static function ensureAdministratorMarkerCaps(): void
    {
        $role = get_role('administrator');
        if (!$role instanceof \WP_Role) {
            return;
        }

        foreach (self::ADMINISTRATOR_MARKER_CAPS as $cap) {
            if (!$role->has_cap($cap)) {
                $role->add_cap($cap);
            }
        }
    }

    /**
     * @return array<string, array{label: string, caps: array<string, bool>}>
     */
    private static function roleDefinitions(): array
    {
        $base = [
            'read' => true,
            self::CAP_VIEW_DASHBOARD => true,
            self::CAP_USE_DASHBOARD_WIDGETS => true,
        ];

        $merge = static function (array ...$parts): array {
            $out = [];
            foreach ($parts as $part) {
                foreach ($part as $k => $v) {
                    $out[(string) $k] = (bool) $v;
                }
            }

            return $out;
        };

        return [
            self::ROLE_SITE_MANAGER => [
                'label' => self::ROLE_LABELS[self::ROLE_SITE_MANAGER],
                'caps' => $merge(
                    $base,
                    [
                        'edit_posts' => true,
                        'edit_pages' => true,
                        'edit_others_posts' => true,
                        'edit_others_pages' => true,
                        'publish_posts' => true,
                        'publish_pages' => true,
                        'upload_files' => true,
                        'edit_products' => true,
                        'edit_others_products' => true,
                        'read_private_products' => true,
                        'edit_others_shop_orders' => true,
                        'edit_shop_orders' => true,
                        'read_private_shop_orders' => true,
                        'view_woocommerce_reports' => true,
                        'manage_woocommerce' => true,
                        'pmpro_memberships_menu' => true,
                        'pmpro_dashboard' => true,
                        'pmpro_memberslist' => true,
                        'pmpro_edit_members' => true,
                        'pmpro_orders' => true,
                        'pmpro_reports' => true,
                        'pmpro_membershiplevels' => true,
                        'pmpro_discountcodes' => true,
                        'pmpro_pagesettings' => true,
                        'pmpro_paymentsettings' => true,
                        'pmpro_securitysettings' => true,
                        'pmpro_emailsettings' => true,
                        'pmpro_emailtemplates' => true,
                        'pmpro_userfields' => true,
                        'pmpro_designsettings' => true,
                        'pmpro_advancedsettings' => true,
                        'pmpro_addons' => true,
                        'pmpro_updates' => true,
                        'pmpro_manage_pause_mode' => true,
                        'pmpro_wizard' => true,
                        self::CAP_VIEW_REPORTS => true,
                        self::CAP_MANAGE_CONTENT => true,
                        self::CAP_MANAGE_MINUTES => true,
                        self::CAP_MANAGE_MEMBERS => true,
                        self::CAP_VIEW_FINANCE => true,
                        self::CAP_MANAGE_EVENTS => true,
                        self::CAP_MANAGE_SHOP => true,
                        self::CAP_EDIT_SHARED_CLUB_CONTENT => true,
                        self::CAP_MANAGE_FEATURED_VEHICLES => true,
                        self::CAP_MANAGE_FAQ => true,
                        self::CAP_VIEW_EVENTS => true,
                        self::CAP_EDIT_EVENTS => true,
                        self::CAP_CREATE_EVENTS => true,
                    ]
                ),
            ],
            self::ROLE_SECRETARY => [
                'label' => self::ROLE_LABELS[self::ROLE_SECRETARY],
                'caps' => $merge(
                    $base,
                    [
                        'upload_files' => true,
                        self::CAP_MANAGE_MINUTES => true,
                        self::CAP_EDIT_SHARED_CLUB_CONTENT => true,
                        self::CAP_MANAGE_FAQ => true,
                        self::CAP_VIEW_EVENTS => true,
                        self::CAP_EDIT_EVENTS => true,
                    ]
                ),
            ],
            self::ROLE_MEMBERSHIP_MANAGER => [
                'label' => self::ROLE_LABELS[self::ROLE_MEMBERSHIP_MANAGER],
                'caps' => $merge(
                    $base,
                    [
                        'upload_files' => true,
                        'pmpro_memberships_menu' => true,
                        'pmpro_dashboard' => true,
                        'pmpro_memberslist' => true,
                        'pmpro_edit_members' => true,
                        self::CAP_MANAGE_MEMBERS => true,
                        self::CAP_EDIT_SHARED_CLUB_CONTENT => true,
                        self::CAP_MANAGE_FAQ => true,
                        self::CAP_VIEW_EVENTS => true,
                        self::CAP_EDIT_EVENTS => true,
                    ]
                ),
            ],
            self::ROLE_TREASURER => [
                'label' => self::ROLE_LABELS[self::ROLE_TREASURER],
                'caps' => $merge(
                    $base,
                    [
                        'upload_files' => true,
                        'edit_others_shop_orders' => true,
                        'edit_shop_orders' => true,
                        'read_private_shop_orders' => true,
                        'view_woocommerce_reports' => true,
                        'pmpro_memberships_menu' => true,
                        'pmpro_dashboard' => true,
                        'pmpro_orders' => true,
                        'pmpro_reports' => true,
                        self::CAP_VIEW_REPORTS => true,
                        self::CAP_VIEW_FINANCE => true,
                        self::CAP_EDIT_SHARED_CLUB_CONTENT => true,
                        self::CAP_MANAGE_FAQ => true,
                        self::CAP_VIEW_EVENTS => true,
                        self::CAP_EDIT_EVENTS => true,
                        self::CAP_CREATE_EVENTS => true,
                    ]
                ),
            ],
            self::ROLE_EVENT_MANAGER => [
                'label' => self::ROLE_LABELS[self::ROLE_EVENT_MANAGER],
                'caps' => $merge(
                    $base,
                    [
                        'edit_posts' => true,
                        'upload_files' => true,
                        'manage_woocommerce' => true,
                        'edit_others_shop_orders' => true,
                        'edit_shop_orders' => true,
                        'read_private_shop_orders' => true,
                        self::CAP_MANAGE_EVENTS => true,
                        self::CAP_EDIT_SHARED_CLUB_CONTENT => true,
                        self::CAP_MANAGE_FAQ => true,
                        self::CAP_VIEW_EVENTS => true,
                        self::CAP_EDIT_EVENTS => true,
                        self::CAP_CREATE_EVENTS => true,
                    ]
                ),
            ],
            self::ROLE_CONTENT_MANAGER => [
                'label' => self::ROLE_LABELS[self::ROLE_CONTENT_MANAGER],
                'caps' => $merge(
                    $base,
                    [
                        'edit_posts' => true,
                        'edit_pages' => true,
                        'edit_others_posts' => true,
                        'edit_others_pages' => true,
                        'publish_posts' => true,
                        'publish_pages' => true,
                        'upload_files' => true,
                        'delete_posts' => true,
                        'delete_pages' => true,
                        'delete_others_posts' => true,
                        'delete_others_pages' => true,
                        'read_private_posts' => true,
                        'read_private_pages' => true,
                        'edit_published_posts' => true,
                        'edit_published_pages' => true,
                        'delete_published_posts' => true,
                        'delete_published_pages' => true,
                        self::CAP_MANAGE_CONTENT => true,
                        self::CAP_EDIT_SHARED_CLUB_CONTENT => true,
                        self::CAP_MANAGE_FAQ => true,
                        self::CAP_VIEW_EVENTS => true,
                        self::CAP_EDIT_EVENTS => true,
                        'wpseo_edit_advanced_metadata' => true,
                    ]
                ),
            ],
            self::ROLE_REPORT_VIEWER => [
                'label' => self::ROLE_LABELS[self::ROLE_REPORT_VIEWER],
                'caps' => $merge(
                    $base,
                    [
                        self::CAP_VIEW_REPORTS => true,
                        self::CAP_VIEW_EVENTS => true,
                        self::CAP_MANAGE_FAQ => true,
                    ]
                ),
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function operationalRoleSlugs(): array
    {
        return array_keys(self::ROLE_LABELS);
    }

    public static function isOperationalRole(string $role): bool
    {
        return isset(self::ROLE_LABELS[$role]);
    }

    public static function userHasOperationalBackendAccess(int $userId): bool
    {
        $user = get_userdata($userId);
        if (!$user instanceof \WP_User) {
            return false;
        }

        /*
         * Any of these mean “club / commerce / PMPro staff” for wp-admin + WooCommerce
         * bypasses. Role slugs are checked next as a fallback if caps were stripped from the DB.
         */
        $staffCaps = [
            self::CAP_VIEW_DASHBOARD,
            self::CAP_USE_DASHBOARD_WIDGETS,
            self::CAP_MANAGE_MEMBERS,
            self::CAP_VIEW_REPORTS,
            self::CAP_VIEW_FINANCE,
            self::CAP_MANAGE_EVENTS,
            self::CAP_MANAGE_CONTENT,
            self::CAP_MANAGE_MINUTES,
            self::CAP_EDIT_SHARED_CLUB_CONTENT,
            self::CAP_MANAGE_FAQ,
            self::CAP_VIEW_EVENTS,
            self::CAP_EDIT_EVENTS,
            self::CAP_CREATE_EVENTS,
            'edit_shop_orders',
            'edit_others_shop_orders',
            'read_private_shop_orders',
            'view_woocommerce_reports',
            'pmpro_edit_members',
            'pmpro_memberslist',
            'pmpro_dashboard',
        ];
        foreach ($staffCaps as $cap) {
            if ($user->has_cap($cap)) {
                return true;
            }
        }

        $roles = is_array($user->roles) ? $user->roles : [];
        foreach (self::operationalRoleSlugs() as $slug) {
            if (in_array($slug, $roles, true)) {
                return true;
            }
        }

        return false;
    }

    public static function isPureMemberPortalUser(\WP_User $user): bool
    {
        if ($user->has_cap('manage_options')) {
            return false;
        }

        if (self::userHasOperationalBackendAccess((int) $user->ID)) {
            return false;
        }

        $elevatedCaps = [
            'edit_others_posts',
            'edit_published_posts',
            'publish_posts',
            'upload_files',
            'list_users',
            'promote_users',
            'manage_woocommerce',
            'edit_shop_orders',
            'edit_products',
            'read_private_posts',
            self::CAP_EDIT_SHARED_CLUB_CONTENT,
            self::CAP_MANAGE_FEATURED_VEHICLES,
            self::CAP_MANAGE_FAQ,
            self::CAP_VIEW_EVENTS,
            self::CAP_EDIT_EVENTS,
            self::CAP_CREATE_EVENTS,
            self::CAP_MANAGE_MEMBERS,
            'pmpro_edit_members',
            'pmpro_memberslist',
            'pmpro_dashboard',
        ];

        foreach ($elevatedCaps as $cap) {
            if ($user->has_cap($cap)) {
                return false;
            }
        }

        $consumerRoles = ['subscriber', 'sccc_member', 'customer'];
        $roles = is_array($user->roles) ? $user->roles : [];

        foreach ($roles as $role) {
            if (!in_array($role, $consumerRoles, true)) {
                return false;
            }
        }

        return !empty($roles);
    }

    /**
     * Logged-in user has an operational role but is not a full administrator.
     */
    public static function currentUserIsRestrictedOperational(): bool
    {
        if (!is_user_logged_in()) {
            return false;
        }

        if (current_user_can('manage_options')) {
            return false;
        }

        return self::userHasOperationalBackendAccess((int) get_current_user_id());
    }

    /**
     * Highest-priority operational role slug for the user, or null.
     */
    public static function getHighestOperationalRoleSlug(\WP_User $user): ?string
    {
        $roles = is_array($user->roles) ? $user->roles : [];
        foreach (self::OPERATIONAL_BAR_PRIORITY as $slug) {
            if (in_array($slug, $roles, true)) {
                return $slug;
            }
        }

        return null;
    }

    public static function getHighestOperationalRoleLabel(\WP_User $user): ?string
    {
        $slug = self::getHighestOperationalRoleSlug($user);

        return $slug ? (self::ROLE_LABELS[$slug] ?? $slug) : null;
    }
}
