<?php
/**
 * app/Admin/Dashboard/members-widget.php
 *
 * Dashboard Widget: Members Overview (PMPro)
 *
 * Shows (SCCC members only):
 *  - Total Active Members
 *  - Renewal-needed count (cumulative overdue + expiring this month)
 *  - Breakdown of Active Members by Membership Level (from PMPro)
 *
 * IMPORTANT CHANGE (your request)
 * -----------------------------------------------------------------------------
 * Your site has “shoppers” (non-members) in the WP users table.
 * We now ONLY count users who have the additional WP role: `sccc_member`.
 *
 * How we filter by role in SQL
 * -----------------------------------------------------------------------------
 * WordPress stores roles in the usermeta key: {$wpdb->prefix}capabilities
 * as a serialized array. We filter using:
 *   meta_key = '<prefix>capabilities'
 *   meta_value LIKE '%"sccc_member"%'
 *
 * This keeps the widget fast and avoids loading all users in PHP.
 *
 * Load it from your theme setup file, e.g.:
 *   require_once get_theme_file_path('app/Admin/Dashboard/members-widget.php');
 */

add_action('wp_dashboard_setup', function () {
  if (!current_user_can(\App\Support\Admin\UserRoles\OperationalRoles::CAP_USE_DASHBOARD_WIDGETS)) {
    return;
  }

  wp_add_dashboard_widget(
    'sccc_members_overview',
    'Members Overview',
    'sccc_render_members_overview_widget'
  );
});

function sccc_render_members_overview_widget(): void
{
  if (!current_user_can(\App\Support\Admin\UserRoles\OperationalRoles::CAP_USE_DASHBOARD_WIDGETS)) {
    echo '<p>You do not have permission to view this widget.</p>';
    return;
  }

  global $wpdb;

  // PMPro tables
  $mu_table     = $wpdb->prefix . 'pmpro_memberships_users';
  $levels_table = $wpdb->prefix . 'pmpro_membership_levels';

  // WP roles live here (serialized array)
  $cap_key      = $wpdb->prefix . 'capabilities';

  // Role gate: ONLY users with this role are considered “members” for this widget.
  $member_role  = 'sccc_member';

  // Current site time (WordPress timezone), formatted to MySQL datetime
  $now_ts  = current_time('timestamp');
  $now_sql = date('Y-m-d H:i:s', $now_ts);

  // Month boundaries in site timezone
  $month_start_ts = strtotime(date('Y-m-01 00:00:00', $now_ts));
  $next_month_ts  = strtotime('+1 month', $month_start_ts);

  $month_start_sql = date('Y-m-d H:i:s', $month_start_ts);
  $next_month_sql  = date('Y-m-d H:i:s', $next_month_ts);

  $month_label = date_i18n('F', $now_ts); // e.g. "January"

  /**
   * PMPro status + enddate notes:
   * - memberships_users.status is typically 'active' when current.
   * - enddate can be NULL/0000-00-00 00:00:00 for "no expiration".
   *
   * We treat "active" as:
   *   status='active' AND (enddate IS NULL OR enddate='0000-00-00 00:00:00' OR enddate >= now)
   *
   * MEMBER ROLE GATE:
   * - We join wp_usermeta on <prefix>capabilities and require it contains "sccc_member"
   * - This excludes shoppers/non-members completely from the counts.
   */

  // 1) Total Active Members (distinct users) — SCCC role only
  $total_active = (int) $wpdb->get_var($wpdb->prepare("
    SELECT COUNT(DISTINCT mu.user_id)
    FROM {$mu_table} mu
    INNER JOIN {$wpdb->usermeta} um
      ON um.user_id = mu.user_id
     AND um.meta_key = %s
     AND um.meta_value LIKE %s
    WHERE mu.status = 'active'
      AND (
        mu.enddate IS NULL
        OR mu.enddate = '0000-00-00 00:00:00'
        OR mu.enddate >= %s
      )
  ", $cap_key, '%"' . $member_role . '"%', $now_sql));

  /**
   * 2) Members that need to renew (cumulative past to this month) — SCCC role only
   *
   * Interpretation:
   * - includes users whose membership enddate is in the past (overdue)
   * - plus users expiring between month_start and next_month (this month)
   *
   * We count membership rows where:
   * - enddate is a real date (not NULL / not 0000...)
   * - enddate < next_month (everything due up through end of current month)
   * - status is NOT cancelled/admin_cancelled
   */
  $needs_renewal_cumulative = (int) $wpdb->get_var($wpdb->prepare("
    SELECT COUNT(DISTINCT mu.user_id)
    FROM {$mu_table} mu
    INNER JOIN {$wpdb->usermeta} um
      ON um.user_id = mu.user_id
     AND um.meta_key = %s
     AND um.meta_value LIKE %s
    WHERE mu.enddate IS NOT NULL
      AND mu.enddate <> '0000-00-00 00:00:00'
      AND mu.enddate < %s
      AND mu.status NOT IN ('cancelled', 'admin_cancelled')
  ", $cap_key, '%"' . $member_role . '"%', $next_month_sql));

  /**
   * 3) Breakdown of active members per membership level — SCCC role only
   *
   * We start from the levels table (so levels can still show even if 0),
   * then join active memberships, then role-filter via usermeta.
   *
   * COUNT DISTINCT um.user_id ensures only members with the role are counted.
   */
  $rows = $wpdb->get_results($wpdb->prepare("
    SELECT
      l.id   AS level_id,
      l.name AS level_name,
      COUNT(DISTINCT um.user_id) AS member_count
    FROM {$levels_table} l
    LEFT JOIN {$mu_table} mu
      ON mu.membership_id = l.id
     AND mu.status = 'active'
     AND (
       mu.enddate IS NULL
       OR mu.enddate = '0000-00-00 00:00:00'
       OR mu.enddate >= %s
     )
    LEFT JOIN {$wpdb->usermeta} um
      ON um.user_id = mu.user_id
     AND um.meta_key = %s
     AND um.meta_value LIKE %s
    GROUP BY l.id, l.name
    ORDER BY l.id ASC
  ", $now_sql, $cap_key, '%"' . $member_role . '"%'));

  // Render
  echo '<div class="sccc-widget">';

  echo '<p style="margin:0 0 .5rem;"><strong>Total Active Members:</strong> ' . number_format_i18n($total_active) . '</p>';

  echo '<p style="margin:0 0 1rem;">'
    . '<strong>' . esc_html($month_label) . '</strong> '
    . number_format_i18n($needs_renewal_cumulative)
    . ' Members are up for renewal'
    . '</p>';

  echo '<hr style="margin: .75rem 0 1rem;" />';

  echo '<p style="margin:0 0 .5rem;"><strong>Active Members by Level</strong></p>';

  if (empty($rows)) {
    echo '<p style="margin:0;">No membership levels found in PMPro.</p>';
  } else {
    echo '<table class="widefat striped" style="margin:0;">';
    echo '<thead><tr><th>Level</th><th style="width:120px;text-align:right;">Active</th></tr></thead>';
    echo '<tbody>';

    foreach ($rows as $r) {
      $name  = $r->level_name ? $r->level_name : ('Level #' . (int) $r->level_id);
      $count = (int) $r->member_count;

      echo '<tr>';
      echo '<td>' . esc_html($name) . '</td>';
      echo '<td style="text-align:right;">' . number_format_i18n($count) . '</td>';
      echo '</tr>';
    }

    echo '</tbody></table>';
  }

  echo '</div>';
}
