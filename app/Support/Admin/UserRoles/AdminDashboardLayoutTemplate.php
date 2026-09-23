<?php
/**
 * File: app/Support/Admin/UserRoles/AdminDashboardLayoutTemplate.php
 *
 * Seeds the main Dashboard (wp-admin/index.php) widget layout for operational
 * users the first time they open the Dashboard, using a designated “template”
 * user’s saved layout (typically the club owner / lead administrator).
 *
 * Behaviour
 * - Runs on `load-index.php` before WordPress finishes loading index.php and
 *   calling `wp_dashboard_setup()`, so the first paint uses the copied order.
 * - Only users who pass `OperationalRoles::userHasOperationalBackendAccess()` are
 *   eligible (same notion of “backend operational” access as elsewhere).
 * - For each layout meta key, we copy **only if** the current user has **never**
 *   had that key (`metadata_exists` is false). Once any layout has been stored,
 *   WordPress owns it; the user may rearrange freely afterward.
 * - Does nothing when the current user **is** the template user (no self-copy).
 *
 * Configuration
 * - Option `sccc_dashboard_layout_template_user_id` (integer user ID). Default
 *   when unset: **1** (often the first admin account). Override with:
 *     `update_option( 'sccc_dashboard_layout_template_user_id', YOUR_USER_ID );`
 * - Filter `sccc_dashboard_layout_template_user_id` may override the resolved ID
 *   at runtime (runs after the option default is applied).
 *
 * Meta keys (WordPress core)
 * - `meta-box-order_dashboard` — column / box order
 * - `closedpostboxes_dashboard` — collapsed boxes
 * - `screen_layout_dashboard` — 1 vs 2 columns
 *
 * Non-goals
 * - Does not sync on every login (only fills missing keys once per key).
 * - Does not push layout changes to users who already customized (no overwrite).
 */

namespace App\Support\Admin\UserRoles;

defined('ABSPATH') || exit;

final class AdminDashboardLayoutTemplate
{
    /**
     * User meta keys used by core for the Dashboard screen.
     *
     * @var list<string>
     */
    private const LAYOUT_META_KEYS = [
        'meta-box-order_dashboard',
        'closedpostboxes_dashboard',
        'screen_layout_dashboard',
    ];

    public static function register(): void
    {
        add_action('load-index.php', [self::class, 'maybeSeedLayoutFromTemplate'], 1);
    }

    public static function maybeSeedLayoutFromTemplate(): void
    {
        if (!is_user_logged_in()) {
            return;
        }

        $userId = (int) get_current_user_id();
        if ($userId <= 0) {
            return;
        }

        if (!OperationalRoles::userHasOperationalBackendAccess($userId)) {
            return;
        }

        $templateId = self::resolveTemplateUserId();
        if ($templateId <= 0 || $templateId === $userId) {
            return;
        }

        $templateUser = get_userdata($templateId);
        if (!$templateUser instanceof \WP_User) {
            return;
        }

        $copied = false;
        foreach (self::LAYOUT_META_KEYS as $metaKey) {
            if (metadata_exists('user', $userId, $metaKey)) {
                continue;
            }

            if (!metadata_exists('user', $templateId, $metaKey)) {
                continue;
            }

            $value = get_user_meta($templateId, $metaKey, true);
            if ($value === false || $value === null) {
                continue;
            }

            /**
             * Allow empty string / empty array from template (valid “nothing closed”).
             */
            update_user_meta($userId, $metaKey, $value);
            $copied = true;
        }

        if ($copied) {
            clean_user_cache($userId);
        }
    }

    private static function resolveTemplateUserId(): int
    {
        $fromOption = (int) get_option('sccc_dashboard_layout_template_user_id', 1);

        /**
         * Filters the user ID whose Dashboard layout is copied for first-time
         * operational users.
         *
         * @param  int  $userId  From option, default 1 when option missing.
         */
        return (int) apply_filters('sccc_dashboard_layout_template_user_id', $fromOption);
    }
}
