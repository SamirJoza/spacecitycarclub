<?php
/**
 * File: app/Support/Admin/UserRoles/SecondaryRoleAssignment.php
 *
 * Adds a “Secondary operational roles” checkbox section on the Edit User screen
 * (Users → Edit User). This mirrors the common WPFront-style flow:
 *
 * - Core continues to own the primary role `<select>` (“Role” dropdown).
 * - This UI only toggles SCCC operational roles (`sccc_site_manager`, …).
 * - Protected membership / commerce roles (`sccc_member`, `customer`) are never
 *   edited here; they are snapshotted before core saves and restored afterward.
 * - Saving uses `edit_user_profile_update` to stash intent, then `profile_update`
 *   (which fires from `wp_update_user()` inside `edit_user()`) to re-apply
 *   operational + protected roles after WordPress applies the primary role.
 *
 * Safety rules implemented here
 * - No UI on “Profile” self-edit for secondary roles.
 * - Users cannot modify their own secondary roles from this screen.
 * - `administrator` is never assignable as a secondary role.
 * - `sccc_member` and `customer` are never free-form checkboxes; they are shown
 *   read-only when present and restored from a snapshot if core `set_role()` drops
 *   them while changing the primary role.
 * - Role changes use `add_role()` / `remove_role()` only for operational slugs.
 * - `set_role()` is never used by this class.
 *
 * Important interaction with WordPress core
 * - When the primary role dropdown saves, core may replace the user’s role list in
 *   a way that temporarily removes additional roles. The `profile_update` pass
 *   repairs `sccc_member` / `customer` and applies operational selections.
 */

namespace App\Support\Admin\UserRoles;

defined('ABSPATH') || exit;

final class SecondaryRoleAssignment
{
    private const NONCE_ACTION = 'sccc_secondary_operational_roles';

    private const NONCE_FIELD = 'sccc_secondary_operational_roles_nonce';

    private const FIELD_OPERATIONAL = 'sccc_secondary_operational_roles';

    private const META_PENDING_OPERATIONAL = '_sccc_pending_secondary_operational';

    private const META_PENDING_PROTECTED = '_sccc_pending_secondary_protected';

    public static function register(): void
    {
        if (!is_admin()) {
            return;
        }

        add_action('edit_user_profile', [self::class, 'render'], 20);

        // Capture intent before core `edit_user()` runs on the same request.
        add_action('edit_user_profile_update', [self::class, 'stagePendingChanges'], 1);

        // After `wp_update_user()` completes inside `edit_user()`, repair roles.
        add_action('profile_update', [self::class, 'applyPendingChanges'], 9999, 2);
    }

    /**
     * Render secondary operational role checkboxes below core profile fields.
     */
    public static function render(\WP_User $user): void
    {
        if (!$user instanceof \WP_User) {
            return;
        }

        // Never on “your own profile” screen — only when editing another account.
        if (get_current_user_id() === (int) $user->ID) {
            return;
        }

        if (!current_user_can('edit_user', $user->ID)) {
            return;
        }

        if (!current_user_can('promote_users') && !current_user_can('promote_user', $user->ID)) {
            return;
        }

        $operational = OperationalRoles::operationalRoleSlugs();
        $currentRoles = is_array($user->roles) ? $user->roles : [];

        $selectedOperational = array_values(array_intersect($operational, $currentRoles));

        $hasMemberRole = in_array('sccc_member', $currentRoles, true);
        $hasCustomerRole = in_array('customer', $currentRoles, true);

        ?>
        <h2><?php esc_html_e('Secondary operational roles', 'sccc'); ?></h2>
        <p class="description">
            <?php esc_html_e('These roles grant club backend access. They are separate from PMPro membership (`sccc_member`) and WooCommerce (`customer`), which are managed automatically and cannot be edited here.', 'sccc'); ?>
        </p>

        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><?php esc_html_e('Operational access', 'sccc'); ?></th>
                <td>
                    <?php wp_nonce_field(self::NONCE_ACTION, self::NONCE_FIELD); ?>

                    <fieldset>
                        <legend class="screen-reader-text"><?php esc_html_e('Operational roles', 'sccc'); ?></legend>

                        <div style="display:grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap:8px 18px; max-width:760px;">
                            <?php foreach ($operational as $slug) : ?>
                                <?php
                                $id = 'sccc-secondary-' . esc_attr($slug);
                                $label = self::roleLabel($slug);
                                $checked = in_array($slug, $selectedOperational, true);
                                ?>
                                <label for="<?php echo esc_attr($id); ?>" style="display:flex; align-items:center; gap:8px;">
                                    <input
                                        type="checkbox"
                                        id="<?php echo esc_attr($id); ?>"
                                        name="<?php echo esc_attr(self::FIELD_OPERATIONAL); ?>[]"
                                        value="<?php echo esc_attr($slug); ?>"
                                        <?php checked($checked); ?>
                                    />
                                    <span><?php echo esc_html($label); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </fieldset>

                    <p class="description" style="margin-top:12px;">
                        <?php esc_html_e('WordPress will continue to save the primary role from the standard “Role” dropdown above. Operational roles are applied immediately afterward.', 'sccc'); ?>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row"><?php esc_html_e('Automatic roles (read-only)', 'sccc'); ?></th>
                <td>
                    <ul style="margin:0; padding-left:1.1rem;">
                        <li>
                            <?php if ($hasMemberRole) : ?>
                                <strong><?php esc_html_e('Club member (`sccc_member`)', 'sccc'); ?></strong>
                                — <?php esc_html_e('managed by PMPro / membership sync', 'sccc'); ?>
                            <?php else : ?>
                                <?php esc_html_e('Club member (`sccc_member`): not currently present', 'sccc'); ?>
                            <?php endif; ?>
                        </li>
                        <li>
                            <?php if ($hasCustomerRole) : ?>
                                <strong><?php esc_html_e('Customer (`customer`)', 'sccc'); ?></strong>
                                — <?php esc_html_e('managed by WooCommerce', 'sccc'); ?>
                            <?php else : ?>
                                <?php esc_html_e('Customer (`customer`): not currently present', 'sccc'); ?>
                            <?php endif; ?>
                        </li>
                    </ul>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Store pending operational selections + protected roles before core saves.
     */
    public static function stagePendingChanges(int $userId): void
    {
        if ($userId <= 0) {
            return;
        }

        if (get_current_user_id() === $userId) {
            return;
        }

        if (!isset($_POST[self::NONCE_FIELD])) {
            delete_user_meta($userId, self::META_PENDING_OPERATIONAL);
            delete_user_meta($userId, self::META_PENDING_PROTECTED);

            return;
        }

        if (!current_user_can('edit_user', $userId)) {
            return;
        }

        if (!current_user_can('promote_users') && !current_user_can('promote_user', $userId)) {
            return;
        }

        $nonce = isset($_POST[self::NONCE_FIELD]) ? (string) wp_unslash($_POST[self::NONCE_FIELD]) : '';
        if (!wp_verify_nonce($nonce, self::NONCE_ACTION)) {
            return;
        }

        $posted = $_POST[self::FIELD_OPERATIONAL] ?? [];
        if (!is_array($posted)) {
            $posted = [];
        }

        $selected = [];
        foreach ($posted as $value) {
            $slug = sanitize_key((string) wp_unslash($value));
            if (OperationalRoles::isOperationalRole($slug)) {
                $selected[] = $slug;
            }
        }

        $selected = array_values(array_unique($selected));

        $subject = get_userdata($userId);
        if (!$subject instanceof \WP_User) {
            return;
        }

        $protected = [];
        if (in_array('sccc_member', (array) $subject->roles, true)) {
            $protected[] = 'sccc_member';
        }
        if (in_array('customer', (array) $subject->roles, true)) {
            $protected[] = 'customer';
        }

        update_user_meta($userId, self::META_PENDING_OPERATIONAL, $selected);
        update_user_meta($userId, self::META_PENDING_PROTECTED, $protected);
    }

    /**
     * Apply operational + protected roles after WordPress updates the user.
     *
     * @param  int|\WP_User  $userId
     * @param  array|\WP_User  $oldUserData
     */
    public static function applyPendingChanges($userId, $oldUserData = null): void
    {
        $userId = (int) $userId;
        if ($userId <= 0) {
            return;
        }

        $pending = get_user_meta($userId, self::META_PENDING_OPERATIONAL, true);
        if (!is_array($pending)) {
            return;
        }

        delete_user_meta($userId, self::META_PENDING_OPERATIONAL);

        $protected = get_user_meta($userId, self::META_PENDING_PROTECTED, true);
        delete_user_meta($userId, self::META_PENDING_PROTECTED);
        if (!is_array($protected)) {
            $protected = [];
        }

        $user = new \WP_User($userId);
        $operational = OperationalRoles::operationalRoleSlugs();

        foreach ($operational as $slug) {
            $shouldHave = in_array($slug, $pending, true);
            $has = in_array($slug, (array) $user->roles, true);

            if ($shouldHave && !$has) {
                $user->add_role($slug);
            }

            if (!$shouldHave && $has) {
                $user->remove_role($slug);
            }
        }

        foreach (['sccc_member', 'customer'] as $protectedRole) {
            if (!in_array($protectedRole, $protected, true)) {
                continue;
            }

            $user = new \WP_User($userId);
            if (!in_array($protectedRole, (array) $user->roles, true)) {
                $user->add_role($protectedRole);
            }
        }
    }

    private static function roleLabel(string $slug): string
    {
        $map = [
            OperationalRoles::ROLE_SITE_MANAGER => __('Club Site Manager', 'sccc'),
            OperationalRoles::ROLE_SECRETARY => __('Club Secretary', 'sccc'),
            OperationalRoles::ROLE_MEMBERSHIP_MANAGER => __('Membership Manager', 'sccc'),
            OperationalRoles::ROLE_TREASURER => __('Treasurer', 'sccc'),
            OperationalRoles::ROLE_EVENT_MANAGER => __('Event Manager', 'sccc'),
            OperationalRoles::ROLE_CONTENT_MANAGER => __('Content Manager', 'sccc'),
            OperationalRoles::ROLE_REPORT_VIEWER => __('Report Viewer', 'sccc'),
        ];

        return $map[$slug] ?? $slug;
    }
}
