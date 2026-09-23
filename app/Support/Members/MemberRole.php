<?php

/**
 * SCCC Member Role + PMPro Sync + One-time Backfill trigger.
 *
 * What this does:
 * 1) Ensures a dedicated role "sccc_member" exists.
 * 2) Syncs role on PMPro membership changes going forward.
 * 3) Provides a one-time backfill you can run from wp-admin by appending:
 *      ?sccc_backfill_member_role=1
 *
 * Why:
 * - Filtering the ACF User picker to a single role removes the confusing grouping.
 * - Backfill is needed so existing active members immediately get the role.
 */

namespace App;

defined('ABSPATH') || exit;

const SCCC_MEMBER_ROLE = 'sccc_member';

// 1) Ensure role exists.
add_action('init', __NAMESPACE__ . '\\ensure_sccc_member_role', 5);

// 2) Keep it in sync when memberships change.
add_action('pmpro_after_change_membership_level', __NAMESPACE__ . '\\sync_member_role_after_pmpro_change', 10, 3);

// 3) Optional: one-time backfill (admin-triggered).
add_action('admin_init', __NAMESPACE__ . '\\maybe_backfill_member_role_once');

function ensure_sccc_member_role(): void
{
  if (get_role(SCCC_MEMBER_ROLE)) return;

  add_role(
    SCCC_MEMBER_ROLE,
    __('SCCC Member', 'sccc'),
    ['read' => true]
  );
}

/**
 * PMPro hook: runs when membership level changes.
 */
function sync_member_role_after_pmpro_change(int $level_id, int $user_id, bool $cancel_level): void
{
  $user = get_user_by('id', $user_id);
  if (!$user instanceof \WP_User) return;

  ensure_sccc_member_role();

  // If they have a level and it's not a cancellation, add role.
  if ($level_id > 0 && !$cancel_level) {
    if (!in_array(SCCC_MEMBER_ROLE, (array) $user->roles, true)) {
      $user->add_role(SCCC_MEMBER_ROLE);
    }
    return;
  }

  // Otherwise remove role.
  if (in_array(SCCC_MEMBER_ROLE, (array) $user->roles, true)) {
    $user->remove_role(SCCC_MEMBER_ROLE);
  }
}

/**
 * One-time backfill:
 * - Adds sccc_member role to all ACTIVE PMPro members right now
 * - Trigger by visiting wp-admin with: ?sccc_backfill_member_role=1
 *
 * Safe guards:
 * - Admin only
 * - Runs only when explicitly triggered
 */
function maybe_backfill_member_role_once(): void
{
  if (!is_admin()) return;
  if (!current_user_can('manage_options')) return;
  if (empty($_GET['sccc_backfill_member_role'])) return;

  // PMPro not active? Don’t do anything.
  if (!function_exists('pmpro_hasMembershipLevel')) return;

  global $wpdb;

  $mu_table = $wpdb->prefix . 'pmpro_memberships_users';
  $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $mu_table));
  if (!$table_exists) return;

  ensure_sccc_member_role();

  $now = current_time('mysql');

  // All ACTIVE members (status + date window).
  $ids = $wpdb->get_col($wpdb->prepare("
    SELECT DISTINCT user_id
    FROM {$mu_table}
    WHERE status = 'active'
      AND (startdate IS NULL OR startdate <= %s)
      AND (enddate   IS NULL OR enddate   >= %s)
  ", $now, $now));

  $ids = array_values(array_filter(array_map('intval', (array) $ids)));

  foreach ($ids as $user_id) {
    $user = get_user_by('id', $user_id);
    if (!$user instanceof \WP_User) continue;

    if (!in_array(SCCC_MEMBER_ROLE, (array) $user->roles, true)) {
      $user->add_role(SCCC_MEMBER_ROLE);
    }
  }

  // Quick admin notice via redirect (prevents reruns on refresh).
  wp_safe_redirect(remove_query_arg('sccc_backfill_member_role'));
  exit;
}
