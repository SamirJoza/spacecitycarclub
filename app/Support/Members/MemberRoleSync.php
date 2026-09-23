<?php

/**
 * PMPro Member Role Sync
 *
 * Goal:
 * - Ensure users with an ACTIVE PMPro membership also have the extra role: sccc_member
 * - Remove sccc_member when they no longer have an active membership
 *
 * Why:
 * - Lets the ACF User picker filter to a single “member role” for a clean alphabetical list
 * - Avoids confusing WP role grouping (Administrator/Subscriber/Customer/etc.)
 * - Works even if members later become WooCommerce customers
 *
 * Requirements:
 * - The role "sccc_member" must already exist (you created it via plugin — perfect).
 *
 * Save as:
 * - app/Support/Members/MemberRoleSync.php
 *
 * Load it:
 * - Ensure this file is required/loaded by your theme bootstrap just like other Support modules.
 */

namespace App;

defined('ABSPATH') || exit;

/**
 * IMPORTANT:
 * Use the exact role slug that your role plugin created.
 * If it differs, change it here.
 */
const SCCC_MEMBER_ROLE = 'sccc_member';

/**
 * Hook 1: Runs after a successful PMPro checkout.
 * This catches brand new signups immediately.
 *
 * Note: PMPro passes ($user_id, $morder).
 */
add_action('pmpro_after_checkout', __NAMESPACE__ . '\\sccc_sync_member_role_after_checkout', 20, 2);

/**
 * Hook 2: Runs after membership level changes.
 * This catches admin edits, cancellations, renewals, etc.
 *
 * IMPORTANT:
 * In some PMPro code paths (notably admin member edit screens),
 * the 3rd argument ($cancel_level) may be NULL.
 * So our callback MUST accept a nullable boolean to avoid PHP 8+ TypeErrors.
 *
 * Note: PMPro passes ($level_id, $user_id, $cancel_level).
 */
add_action('pmpro_after_change_membership_level', __NAMESPACE__ . '\\sccc_sync_member_role_after_level_change', 20, 3);

/**
 * Optional safety net:
 * If you ever bulk import users/memberships and need a place to resync,
 * you can also sync on profile_update. Disabled by default.
 */
// add_action('profile_update', __NAMESPACE__ . '\\sccc_sync_member_role_for_user', 20, 1);

/* ============================================================
 * Hook handlers
 * ============================================================ */

/**
 * After checkout: sync role for the purchasing user.
 *
 * @param int   $user_id
 * @param mixed $morder  PMPro order object (not used here)
 */
function sccc_sync_member_role_after_checkout(int $user_id, $morder = null): void
{
  sccc_sync_member_role_for_user($user_id);
}

/**
 * After membership level change: sync role for the affected user.
 *
 * NOTE:
 * - $cancel_level can be null depending on PMPro execution path.
 * - We do not rely on $level_id/$cancel_level; we compute live membership state.
 *
 * @param int        $level_id
 * @param int        $user_id
 * @param bool|null  $cancel_level
 */
function sccc_sync_member_role_after_level_change(int $level_id, int $user_id, ?bool $cancel_level = null): void
{
  sccc_sync_member_role_for_user($user_id);
}

/* ============================================================
 * Core sync
 * ============================================================ */

/**
 * Add/remove the member role depending on whether user currently has
 * ANY active PMPro membership.
 *
 * This function:
 * - Adds SCCC_MEMBER_ROLE if the user is an active member and doesn't have it
 * - Removes SCCC_MEMBER_ROLE if the user is not an active member and does have it
 * - Never touches any other roles (customer, subscriber, administrator, etc.)
 */
function sccc_sync_member_role_for_user(int $user_id): void
{
  $user = get_user_by('id', $user_id);
  if (!$user instanceof \WP_User) return;

  // If PMPro isn't active, don't do anything.
  // Keeps admin stable if PMPro is temporarily disabled.
  if (!function_exists('pmpro_hasMembershipLevel')) return;

  // True if user has ANY active membership level.
  $is_active_member = (bool) pmpro_hasMembershipLevel(null, $user_id);

  $roles = (array) $user->roles;
  $has_role = in_array(SCCC_MEMBER_ROLE, $roles, true);

  if ($is_active_member && !$has_role) {
    // ✅ Add role *in addition to* any existing roles.
    $user->add_role(SCCC_MEMBER_ROLE);
    return;
  }

  if (!$is_active_member && $has_role) {
    // ✅ Remove only the member role; leave all others untouched.
    $user->remove_role(SCCC_MEMBER_ROLE);
    return;
  }

  // No changes needed.
}
