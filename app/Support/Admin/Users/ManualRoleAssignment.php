<?php
/**
 * app/Support/Admin/Users/ManualRoleAssignment.php
 * File: app/Support/Admin/Users/ManualRoleAssignment.php
 *
 * Adds a checkbox role selector to the WP Admin User Editor:
 * - Users > Edit User (user-edit.php)
 * - Users > Profile (profile.php)
 *
 * Goals
 * ------------------------------------------------------------------------------
 * - Show ALL WordPress roles available to the current admin (default + custom).
 * - Allow freely checking/unchecking roles for a user (multi-role supported).
 * - Save safely using core profile hooks + nonce + capability checks.
 *
 * IMPORTANT FIX (avatar / role-sync safety)
 * ------------------------------------------------------------------------------
 * We DO NOT "reset" all roles anymore.
 * We now do a minimal diff:
 *   - remove only roles that were unchecked
 *   - add only roles that were checked
 *
 * This avoids triggering role-removed hooks for roles that didn't actually change,
 * which can break systems that react to role removal (like local avatar/member sync).
 */

namespace App\Support\Admin\Users;

final class ManualRoleAssignment
{
    /**
     * Local alias name (string) for your existing global constant.
     * We do NOT define a role key here and we do not treat any role specially.
     */
    private const ROLE_KEY_CLUB_MEMBER = 'SCCC_MEMBER_ROLE';

    private const NONCE_ACTION = 'sccc_manual_role_assignment';
    private const NONCE_FIELD  = 'sccc_manual_role_assignment_nonce';
    private const FIELD_ROLES  = 'sccc_manual_roles';

    public static function boot(): void
    {
        if (!is_admin()) {
            return;
        }

        // Render UI
        add_action('show_user_profile', [__CLASS__, 'render']);
        add_action('edit_user_profile', [__CLASS__, 'render']);

        // Save UI
        add_action('personal_options_update', [__CLASS__, 'save']);
        add_action('edit_user_profile_update', [__CLASS__, 'save']);
    }

    /**
     * Render checkboxes on user profile / edit user screen.
     *
     * @param \WP_User $user
     */
    public static function render($user): void
    {
        if (!$user instanceof \WP_User) {
            return;
        }

        // Must be allowed to edit this user.
        if (!current_user_can('edit_user', $user->ID)) {
            return;
        }

        // Role assignment requires promote capability.
        if (!current_user_can('promote_user', $user->ID) && !current_user_can('promote_users')) {
            return;
        }

        $editableRoles = self::getEditableRoles(); // default + custom roles
        $editableKeys  = array_keys($editableRoles);
        $currentRoles  = is_array($user->roles) ? $user->roles : [];

        // Keep roles that aren't editable in this context (rare, but safe).
        $lockedRoles = array_values(array_diff($currentRoles, $editableKeys));

        ?>
        <h2>Manual Role Assignment</h2>

        <table class="form-table" role="presentation">
            <tr>
                <th><label for="sccc-manual-roles">Roles</label></th>
                <td>
                    <?php wp_nonce_field(self::NONCE_ACTION, self::NONCE_FIELD); ?>

                    <div id="sccc-manual-roles"
                         style="display:grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap:8px 18px; max-width:760px;">
                        <?php foreach ($editableRoles as $roleKey => $roleData) : ?>
                            <?php
                            $roleName = isset($roleData['name'])
                                ? translate_user_role($roleData['name'])
                                : (string) $roleKey;

                            $checked = in_array($roleKey, $currentRoles, true);
                            $id      = 'sccc-role-' . esc_attr($roleKey);
                            ?>
                            <label for="<?php echo $id; ?>" style="display:flex; align-items:center; gap:8px;">
                                <input
                                    type="checkbox"
                                    id="<?php echo $id; ?>"
                                    name="<?php echo esc_attr(self::FIELD_ROLES); ?>[]"
                                    value="<?php echo esc_attr($roleKey); ?>"
                                    <?php checked($checked); ?>
                                />
                                <span><?php echo esc_html($roleName); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>

                    <p class="description" style="margin-top:10px;">
                        Check/uncheck any roles you want for this user (supports multiple roles). Includes default and custom roles.
                    </p>

                    <?php if (!empty($lockedRoles)) : ?>
                        <p class="description" style="margin-top:10px;">
                            <strong>Roles currently on this user that are not editable in this context:</strong>
                            <?php echo esc_html(implode(', ', $lockedRoles)); ?>
                        </p>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Save selected roles.
     */
    public static function save(int $userId): void
    {
        // Only run if our UI was on the page.
        if (!isset($_POST[self::NONCE_FIELD])) {
            return;
        }

        if (!current_user_can('edit_user', $userId)) {
            return;
        }

        if (!current_user_can('promote_user', $userId) && !current_user_can('promote_users')) {
            return;
        }

        $nonce = (string) wp_unslash($_POST[self::NONCE_FIELD]);
        if (!wp_verify_nonce($nonce, self::NONCE_ACTION)) {
            return;
        }

        $editableRoles = self::getEditableRoles();
        $editableKeys  = array_keys($editableRoles);

        // Grab selected roles from checkboxes
        $selected = $_POST[self::FIELD_ROLES] ?? [];
        if (!is_array($selected)) {
            $selected = [];
        }

        $selected = array_values(array_unique(array_map('sanitize_key', array_map('strval', $selected))));

        // Only allow roles the current admin is allowed to assign
        $selectedEditable = array_values(array_intersect($selected, $editableKeys));

        $user = new \WP_User($userId);
        $currentRoles = is_array($user->roles) ? $user->roles : [];

        // Preserve roles that aren't editable in this context
        $lockedRoles = array_values(array_diff($currentRoles, $editableKeys));

        // Final roles = locked + selected
        $finalRoles = array_values(array_unique(array_merge($lockedRoles, $selectedEditable)));

        // Ensure the user ends up with at least one role.
        if (empty($finalRoles)) {
            $defaultRole = sanitize_key((string) get_option('default_role', 'subscriber'));
            $finalRoles  = [$defaultRole ?: 'subscriber'];
        }

        /**
         * CRITICAL FIX:
         * Do NOT reset roles (remove all + re-add).
         * Only apply the diff so we don't trigger role-removed hooks unnecessarily.
         */
        $toRemove = array_values(array_diff($currentRoles, $finalRoles));
        $toAdd    = array_values(array_diff($finalRoles, $currentRoles));

        foreach ($toRemove as $role) {
            $user->remove_role($role);
        }

        foreach ($toAdd as $role) {
            $user->add_role($role);
        }

        // Safety: if somehow no roles remain, set a default role.
        $user = new \WP_User($userId);
        if (empty($user->roles)) {
            $defaultRole = sanitize_key((string) get_option('default_role', 'subscriber'));
            $user->set_role($defaultRole ?: 'subscriber');
        }
    }

    /**
     * Fetch editable roles (includes default + custom).
     */
    private static function getEditableRoles(): array
    {
        if (!function_exists('get_editable_roles')) {
            require_once ABSPATH . 'wp-admin/includes/user.php';
        }

        $roles = get_editable_roles();

        return is_array($roles) ? $roles : [];
    }
}

ManualRoleAssignment::boot();