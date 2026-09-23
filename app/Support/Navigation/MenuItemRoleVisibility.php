<?php

declare(strict_types=1);

namespace App\Support\Navigation;

use WP_Post;

/**
 * |--------------------------------------------------------------------------
 * | File path + filename: app/Support/Navigation/MenuItemRoleVisibility.php
 * |--------------------------------------------------------------------------
 * |
 * | Purpose:
 * | - Add a native-looking permissions UI to each WordPress menu item.
 * | - Save a single allowed role against each menu item.
 * | - Remove restricted items before they ever reach the front-end menu markup.
 * |
 * | Why this file exists:
 * | - The site needs menu-item level visibility rules without replacing the
 * |   normal WordPress menu editor.
 * | - The user specifically wants protected items to never be rendered into
 * |   the DOM for visitors who are not logged in with the permitted role.
 * | - The project uses Log1x/Navi for menu rendering, so the filtering must
 * |   happen at the menu-items retrieval layer, not only at wp_nav_menu().
 * |
 * | How this works:
 * | - In wp-admin > Appearance > Menus, each menu item gets:
 * |   1) a checkbox to enable role-based visibility
 * |   2) a role dropdown
 * | - The selections are stored in nav_menu_item post meta.
 * | - On the front end, restricted items are removed from the menu items array
 * |   before Navi or WordPress render the menu tree.
 * |
 * | Important notes:
 * | - This controls navigation visibility only. It does not secure the target
 * |   page or endpoint. Keep PMPro/page restrictions in place for real access
 * |   control.
 * | - If restriction is enabled and no role is selected, the item is hidden on
 * |   the front end. This is intentional and safer than failing open.
 * |
 * | Loading pattern:
 * | - This file is written to work well with the project's preferred theme-
 * |   contained require_once loading pattern.
 * | - Because the class self-boots at the bottom of the file, your bootstrap
 * |   only needs to require this file once.
 * |
 */
class MenuItemRoleVisibility
{
    /**
     * Meta key: whether role-based visibility is enabled for this menu item.
     */
    private const META_ENABLED = '_sccc_menu_role_visibility_enabled';

    /**
     * Meta key: the single allowed WordPress role slug for this menu item.
     */
    private const META_ROLE = '_sccc_menu_role_visibility_role';

    /**
     * Register all admin and front-end hooks.
     *
     * Why this method exists:
     * - It keeps hook registration in one predictable place.
     * - It makes the file easy to require once from the theme bootstrap.
     */
    public static function boot(): void
    {
        add_filter('wp_setup_nav_menu_item', [static::class, 'decorateMenuItem']);
        add_action('wp_nav_menu_item_custom_fields', [static::class, 'renderMenuItemFields'], 10, 5);
        add_action('wp_update_nav_menu_item', [static::class, 'saveMenuItemFields'], 10, 3);
        add_filter('wp_get_nav_menu_items', [static::class, 'filterFrontEndMenuItems'], 20, 3);
    }

    /**
     * Add our saved values to the menu item object.
     *
     * Why this method exists:
     * - It makes the saved values easily available when WordPress renders the
     *   menu editor form.
     * - It keeps the admin UI logic simple and readable.
     *
     * @param object $menuItem The menu item object being prepared by WordPress.
     *
     * @return object
     */
    public static function decorateMenuItem(object $menuItem): object
    {
        $menuItem->sccc_role_visibility_enabled = static::isRestrictionEnabled((int) $menuItem->ID);
        $menuItem->sccc_role_visibility_role = static::getSavedRole((int) $menuItem->ID);

        return $menuItem;
    }

    /**
     * Render the custom fields inside the native WordPress menu item editor.
     *
     * Why this method exists:
     * - The goal is to preserve the normal menu editor UX while extending it
     *   with one small permissions section per item.
     *
     * @param string      $itemId          Menu item ID as a string.
     * @param WP_Post     $menuItem        The current nav menu item post.
     * @param int         $depth           Item depth in the menu tree.
     * @param object|null $args            Not used here, but provided by WP.
     * @param int         $currentObjectId Not used here, but provided by WP.
     */
    public static function renderMenuItemFields(
        string $itemId,
        WP_Post $menuItem,
        int $depth,
        ?object $args,
        int $currentObjectId
    ): void {
        unset($depth, $args, $currentObjectId);

        if (! current_user_can('edit_theme_options')) {
            return;
        }

        $enabled = ! empty($menuItem->sccc_role_visibility_enabled);
        $selectedRole = (string) ($menuItem->sccc_role_visibility_role ?? '');
        $roles = static::getRoleOptions();
        ?>

        <div class="description-wide sccc-menu-item-role-visibility" style="margin: 12px 0 0; padding-top: 12px; border-top: 1px solid #dcdcde;">
            <p style="margin: 0 0 8px; font-weight: 600;">Visibility Permissions</p>

            <p class="description description-wide" style="margin: 0 0 10px;">
                <label for="edit-menu-item-sccc-role-visibility-enabled-<?php echo esc_attr($itemId); ?>">
                    <input
                        type="checkbox"
                        id="edit-menu-item-sccc-role-visibility-enabled-<?php echo esc_attr($itemId); ?>"
                        name="menu-item-sccc-role-visibility-enabled[<?php echo esc_attr($itemId); ?>]"
                        value="1"
                        <?php checked($enabled); ?>
                    />
                    Restrict this menu item by role
                </label>
            </p>

            <p class="description description-wide" style="margin: 0;">
                <label for="edit-menu-item-sccc-role-visibility-role-<?php echo esc_attr($itemId); ?>">
                    <span style="display: block; margin-bottom: 6px;">Allowed role</span>
                    <select
                        id="edit-menu-item-sccc-role-visibility-role-<?php echo esc_attr($itemId); ?>"
                        name="menu-item-sccc-role-visibility-role[<?php echo esc_attr($itemId); ?>]"
                        class="widefat code edit-menu-item-custom"
                    >
                        <option value="">— Select a role —</option>
                        <?php foreach ($roles as $roleKey => $roleLabel) : ?>
                            <option value="<?php echo esc_attr($roleKey); ?>" <?php selected($selectedRole, $roleKey); ?>>
                                <?php echo esc_html($roleLabel); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </p>

            <p class="description" style="margin: 8px 0 0; color: #646970;">
                When enabled, this item is shown only to logged-in users who have the selected WordPress role.
                If no role is selected, the item stays hidden on the front end.
            </p>
        </div>
        <?php
    }

    /**
     * Save the custom menu-item fields.
     *
     * Why this method exists:
     * - WordPress saves each menu item individually during a menu update.
     * - Saving here keeps our custom fields tied directly to the nav_menu_item.
     *
     * @param int   $menuId       The menu term ID.
     * @param int   $menuItemDbId The nav_menu_item post ID.
     * @param array $args         Core menu item arguments from WordPress.
     */
    public static function saveMenuItemFields(int $menuId, int $menuItemDbId, array $args): void
    {
        unset($menuId, $args);

        if (! current_user_can('edit_theme_options')) {
            return;
        }

        $enabled = isset($_POST['menu-item-sccc-role-visibility-enabled'][$menuItemDbId])
            ? '1'
            : '';

        $role = isset($_POST['menu-item-sccc-role-visibility-role'][$menuItemDbId])
            ? static::sanitizeRole(wp_unslash((string) $_POST['menu-item-sccc-role-visibility-role'][$menuItemDbId]))
            : '';

        if ($enabled === '1') {
            update_post_meta($menuItemDbId, static::META_ENABLED, '1');
        } else {
            delete_post_meta($menuItemDbId, static::META_ENABLED);
        }

        if ($role !== '') {
            update_post_meta($menuItemDbId, static::META_ROLE, $role);
        } else {
            delete_post_meta($menuItemDbId, static::META_ROLE);
        }
    }

    /**
     * Remove restricted items from the front-end menu items array.
     *
     * Why this method exists:
     * - The site renders menus through Log1x/Navi, which builds its menu tree
     *   from wp_get_nav_menu_items().
     * - Filtering here ensures the restricted items never make it into Navi's
     *   tree and therefore never make it into the DOM.
     *
     * @param array  $items Array of menu item post objects.
     * @param object $menu  Menu object.
     * @param array  $args  Retrieval arguments.
     *
     * @return array
     */
    public static function filterFrontEndMenuItems(array $items, object $menu, array $args): array
    {
        unset($menu, $args);

        /**
         * Do not interfere with wp-admin menu management screens.
         *
         * Why:
         * - The menu editor itself relies on retrieving the full menu structure.
         * - Restrictions should only affect front-end rendering behavior.
         */
        if (is_admin() || empty($items)) {
            return $items;
        }

        $currentUser = wp_get_current_user();
        $isLoggedIn = is_user_logged_in();
        $userRoles = array_map('sanitize_key', (array) ($currentUser->roles ?? []));

        /**
         * Build a parent -> children lookup first so we can remove descendants
         * of any hidden parent item in one pass.
         */
        $childrenByParent = [];

        foreach ($items as $item) {
            $parentId = (int) $item->menu_item_parent;
            $childrenByParent[$parentId][] = (int) $item->ID;
        }

        /**
         * Determine the directly restricted items first.
         */
        $hiddenIds = [];

        foreach ($items as $item) {
            if (! static::userCanSeeItem((int) $item->ID, $isLoggedIn, $userRoles)) {
                $hiddenIds[(int) $item->ID] = true;
            }
        }

        /**
         * Cascade hidden state down the menu tree.
         *
         * Why:
         * - If a parent item is hidden, its children should not be hoisted or
         *   accidentally left behind as orphaned items.
         */
        $queue = array_keys($hiddenIds);

        while (! empty($queue)) {
            $parentId = (int) array_shift($queue);

            foreach ($childrenByParent[$parentId] ?? [] as $childId) {
                if (! isset($hiddenIds[$childId])) {
                    $hiddenIds[$childId] = true;
                    $queue[] = $childId;
                }
            }
        }

        return array_values(
            array_filter(
                $items,
                static fn ($item): bool => ! isset($hiddenIds[(int) $item->ID])
            )
        );
    }

    /**
     * Determine whether the current visitor may see a given menu item.
     *
     * @param int   $menuItemId The nav_menu_item post ID.
     * @param bool  $isLoggedIn Whether the current visitor is logged in.
     * @param array $userRoles  The current user's role slugs.
     *
     * @return bool
     */
    private static function userCanSeeItem(int $menuItemId, bool $isLoggedIn, array $userRoles): bool
    {
        if (! static::isRestrictionEnabled($menuItemId)) {
            return true;
        }

        if (! $isLoggedIn) {
            return false;
        }

        $allowedRole = static::getSavedRole($menuItemId);

        if ($allowedRole === '') {
            return false;
        }

        return in_array($allowedRole, $userRoles, true);
    }

    /**
     * Read the saved enabled flag for a menu item.
     */
    private static function isRestrictionEnabled(int $menuItemId): bool
    {
        return get_post_meta($menuItemId, static::META_ENABLED, true) === '1';
    }

    /**
     * Read and sanitize the saved role for a menu item.
     */
    private static function getSavedRole(int $menuItemId): string
    {
        return static::sanitizeRole((string) get_post_meta($menuItemId, static::META_ROLE, true));
    }

    /**
     * Return the available roles for the admin dropdown.
     *
     * Why this method exists:
     * - It keeps role retrieval and formatting in one place.
     * - It respects WordPress' editable roles list in the admin when possible.
     *
     * @return array<string, string>
     */
    private static function getRoleOptions(): array
    {
        if (! function_exists('get_editable_roles')) {
            require_once ABSPATH . 'wp-admin/includes/user.php';
        }

        $editableRoles = function_exists('get_editable_roles')
            ? get_editable_roles()
            : [];

        if (! empty($editableRoles)) {
            $options = [];

            foreach ($editableRoles as $roleKey => $roleConfig) {
                $options[sanitize_key((string) $roleKey)] = (string) ($roleConfig['name'] ?? $roleKey);
            }

            return $options;
        }

        $roleNames = wp_roles()->get_names();

        return is_array($roleNames)
            ? array_combine(
                array_map('sanitize_key', array_keys($roleNames)),
                array_values($roleNames)
            ) ?: []
            : [];
    }

    /**
     * Sanitize a role slug and ensure it exists.
     *
     * Why this method exists:
     * - It prevents invalid or tampered values from being saved or used.
     *
     * @param string $role Role slug from post meta or request data.
     *
     * @return string
     */
    private static function sanitizeRole(string $role): string
    {
        $role = sanitize_key($role);

        if ($role === '') {
            return '';
        }

        $allRoles = wp_roles()->roles;

        return isset($allRoles[$role]) ? $role : '';
    }
}

MenuItemRoleVisibility::boot();