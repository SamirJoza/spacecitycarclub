<?php
/**
 * File: app/Support/Admin/UserRoles/AdminBarAppearance.php
 *
 * Subtle admin-bar styling by highest operational role (or Administrator) and a
 * small text label so staff can see which “mode” they are in.
 *
 * Colours follow the Space City Car Club operational colour map. When multiple
 * operational roles are present, `OperationalRoles::getHighestOperationalRoleSlug()`
 * defines precedence.
 *
 * Non-goals: does not change capabilities or menus; cosmetic + label only.
 */

namespace App\Support\Admin\UserRoles;

defined('ABSPATH') || exit;

final class AdminBarAppearance
{
    /**
     * @var array<string, array{bg: string, fg: string, label: string}>
     */
    private const PALETTE = [
        'administrator' => [
            'bg' => '#7f1d1d',
            'fg' => '#fecaca',
            'label' => 'Administrator',
        ],
        'sccc_site_manager' => [
            'bg' => '#1e3a5f',
            'fg' => '#dbeafe',
            'label' => 'Club Site Manager',
        ],
        'sccc_treasurer' => [
            'bg' => '#713f12',
            'fg' => '#fef9c3',
            'label' => 'Treasurer',
        ],
        'sccc_membership_manager' => [
            'bg' => '#14532d',
            'fg' => '#dcfce7',
            'label' => 'Membership Manager',
        ],
        'sccc_secretary' => [
            'bg' => '#4c1d95',
            'fg' => '#ede9fe',
            'label' => 'Club Secretary',
        ],
        'sccc_event_manager' => [
            'bg' => '#7c2d12',
            'fg' => '#ffedd5',
            'label' => 'Event Manager',
        ],
        'sccc_content_manager' => [
            'bg' => '#115e59',
            'fg' => '#ccfbf1',
            'label' => 'Content Manager',
        ],
        'sccc_report_viewer' => [
            'bg' => '#374151',
            'fg' => '#e5e7eb',
            'label' => 'Report Viewer',
        ],
    ];

    public static function register(): void
    {
        add_action('admin_head', [self::class, 'printAdminBarStyles'], 5);
        add_action('wp_head', [self::class, 'printAdminBarStyles'], 5);
        add_action('admin_bar_menu', [self::class, 'addRoleBadge'], 100);
    }

    public static function printAdminBarStyles(): void
    {
        if (!is_admin_bar_showing() || !is_user_logged_in()) {
            return;
        }

        $user = wp_get_current_user();
        if (!$user instanceof \WP_User) {
            return;
        }

        $key = self::resolvePaletteKey($user);
        if ($key === null) {
            return;
        }

        $col = self::PALETTE[$key] ?? null;
        if (!is_array($col)) {
            return;
        }

        $bg = esc_attr($col['bg']);
        $fg = esc_attr($col['fg']);

        echo '<style id="sccc-admin-bar-role-style">'
            . "#wpadminbar { background: {$bg} !important; color: {$fg} !important; }\n"
            . "#wpadminbar .ab-item, #wpadminbar a.ab-item, #wpadminbar > #wp-toolbar span.ab-label, #wpadminbar > #wp-toolbar span.noticon { color: {$fg} !important; }\n"
            . "#wpadminbar .menupop .ab-sub-wrapper { background: {$bg} !important; }\n"
            . "#wpadminbar .ab-submenu .ab-item { color: {$fg} !important; }\n"
            . '#wpadminbar .quicklinks > ul > li > a .ab-icon:before { color: ' . $fg . ' !important; }'
            . "</style>\n";
    }

    /**
     * @return string|null  Palette key or null when no styling applies.
     */
    private static function resolvePaletteKey(\WP_User $user): ?string
    {
        if ($user->has_cap('manage_options')) {
            return 'administrator';
        }

        $slug = OperationalRoles::getHighestOperationalRoleSlug($user);

        return $slug ?? null;
    }

    public static function addRoleBadge(\WP_Admin_Bar $bar): void
    {
        if (!is_user_logged_in()) {
            return;
        }

        $user = wp_get_current_user();
        if (!$user instanceof \WP_User) {
            return;
        }

        $key = self::resolvePaletteKey($user);
        if ($key === null) {
            return;
        }

        $col = self::PALETTE[$key] ?? null;
        if (!is_array($col)) {
            return;
        }

        $title = $key === 'administrator'
            ? (string) ($col['label'] ?? $key)
            : (OperationalRoles::getHighestOperationalRoleLabel($user) ?? (string) ($col['label'] ?? $key));

        $bar->add_node([
            'id' => 'sccc-role-context',
            'title' => '<span class="sccc-ab-role-chip" style="'
                . 'display:inline-flex;align-items:center;gap:6px;'
                . 'padding:2px 10px;border-radius:999px;'
                . 'font-size:11px;font-weight:600;letter-spacing:.02em;'
                . 'border:1px solid rgba(255,255,255,.35);'
                . 'opacity:.95;">'
                . esc_html($title)
                . '</span>',
            'href' => false,
            'meta' => [
                'class' => 'sccc-role-badge',
            ],
        ]);
    }
}
