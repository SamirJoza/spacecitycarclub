<?php
/**
 * File: app/Support/Admin/Dashboard/Reports/VeteransReport.php
 *
 * SCCC Admin Reports (HTML Print-to-PDF) — Veterans / First Responders Report
 * ============================================================================
 * PURPOSE
 * - Dashboard widget that lists members marked as Veteran / First Responder.
 * - Internal meeting sheet: browser Print / Save as PDF (no server PDF libs).
 *
 * DATA SOURCES (from MemberProfile ACF user fields)
 * - is_veteran_first_responder (true/false)
 * - veteran_type: veteran | first_responder | both
 * - agency_branch: Branch / Agency (USMC, Army, Houston Fire, EMS…)
 *
 * SORTING (requested)
 * - Military first (veteran + both)
 * - First responders / law at the bottom
 *
 * MEMBER SCOPE
 * - Only WP role: `sccc_member`
 *
 * ADDITIONAL SCOPE (YOUR CURRENT REPORTING RULE)
 * - This is a “community/engagement” report.
 * - Include: Active + Grace (0–29 days lapsed)
 * - Exclude: Past Due (30–59) + Abandoned (60+)
 * - Exclude: Paused
 *
 * IMPORTANT (Theme-scoped)
 * - This file must be INCLUDED by your theme bootstrap or it will never run.
 */

if (!defined('ABSPATH')) {
  exit;
}

/**
 * ---------------------------------------------------------------------------
 * Shared configuration (guarded to prevent constant redefinition warnings)
 * ---------------------------------------------------------------------------
 */
if (!defined('SCCC_REPORTS_MEMBER_ROLE')) {
  define('SCCC_REPORTS_MEMBER_ROLE', 'sccc_member');
}

/**
 * ---------------------------------------------------------------------------
 * Field keys (meta keys)
 * ---------------------------------------------------------------------------
 */
const SCCC_VET_ENABLED_META = 'is_veteran_first_responder';
const SCCC_VET_TYPE_META    = 'veteran_type';
const SCCC_VET_BRANCH_META  = 'agency_branch';

/**
 * ---------------------------------------------------------------------------
 * MEMBERSHIP STATUS FILTER (Active + Grace only)
 * ---------------------------------------------------------------------------
 *
 * Veterans report is used for “who’s active in the club community right now”.
 * So we exclude:
 * - Paused members (manual override)
 * - Past Due (30–59)
 * - Abandoned (60+)
 *
 * We compute from PMPro’s memberships_users table for reliable status.
 */
if (!function_exists('sccc_reports_vets_is_active_or_grace')) {
  function sccc_reports_vets_is_active_or_grace(int $user_id): bool
  {
    // Paused members are excluded from engagement lists.
    if ((int) get_user_meta($user_id, 'sccc_membership_paused', true) === 1) {
      return false;
    }

    // If PMPro table is missing, we can’t reliably decide. Safer to exclude.
    if (!sccc_reports_vets_pmpro_table_exists()) {
      return false;
    }

    $row = sccc_reports_vets_get_latest_pmpro_membership_row($user_id);
    if (!$row || !is_array($row)) {
      return false;
    }

    $now = current_time('timestamp');
    $status = isset($row['status']) ? (string) $row['status'] : '';
    $end_ts = sccc_reports_vets_parse_enddate_to_ts($row['enddate'] ?? null);

    // Active if PMPro says active AND enddate is empty OR in the future.
    $has_end = ($end_ts !== null);
    $active_by_enddate = (!$has_end) || ($end_ts > $now);

    if ($status === 'active' && $active_by_enddate) {
      return true;
    }

    // Grace if membership ended within the last 0–29 days.
    if ($has_end && $end_ts <= $now) {
      $days = (int) floor(max(0, $now - $end_ts) / DAY_IN_SECONDS);
      return ($days >= 0 && $days < 30);
    }

    return false;
  }
}

if (!function_exists('sccc_reports_vets_pmpro_memberships_users_table')) {
  function sccc_reports_vets_pmpro_memberships_users_table(): string
  {
    global $wpdb;

    if (isset($wpdb->pmpro_memberships_users) && !empty($wpdb->pmpro_memberships_users)) {
      return (string) $wpdb->pmpro_memberships_users;
    }

    return (string) ($wpdb->prefix . 'pmpro_memberships_users');
  }
}

if (!function_exists('sccc_reports_vets_pmpro_table_exists')) {
  function sccc_reports_vets_pmpro_table_exists(): bool
  {
    global $wpdb;

    $table = sccc_reports_vets_pmpro_memberships_users_table();
    if (!$table) {
      return false;
    }

    $like = $wpdb->esc_like($table);
    $found = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $like)); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

    return !empty($found);
  }
}

if (!function_exists('sccc_reports_vets_get_latest_pmpro_membership_row')) {
  function sccc_reports_vets_get_latest_pmpro_membership_row(int $user_id): ?array
  {
    global $wpdb;

    $table = sccc_reports_vets_pmpro_memberships_users_table();
    if (!$table) {
      return null;
    }

    // “Latest” row by modified timestamp (then id) best reflects current state.
    $sql = "
      SELECT id, status, startdate, enddate, modified, membership_id
      FROM {$table}
      WHERE user_id = %d
      ORDER BY modified DESC, id DESC
      LIMIT 1
    ";

    $row = $wpdb->get_row($wpdb->prepare($sql, $user_id), ARRAY_A); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

    return is_array($row) ? $row : null;
  }
}

if (!function_exists('sccc_reports_vets_parse_enddate_to_ts')) {
  function sccc_reports_vets_parse_enddate_to_ts($enddate): ?int
  {
    if ($enddate === null) {
      return null;
    }

    $enddate = is_string($enddate) ? trim($enddate) : '';
    if ($enddate === '' || $enddate === '0000-00-00 00:00:00') {
      return null;
    }

    $ts = strtotime($enddate);
    return $ts ? (int) $ts : null;
  }
}

/**
 * ---------------------------------------------------------------------------
 * Boot: Dashboard widget registration
 * ---------------------------------------------------------------------------
 */
add_action('wp_dashboard_setup', function () {
  if (!current_user_can(\App\Support\Admin\UserRoles\OperationalRoles::CAP_USE_DASHBOARD_WIDGETS)) {
    return;
  }

  wp_add_dashboard_widget(
    'sccc_veterans_report',
    'SCCC Veterans / First Responders',
    'sccc_veterans_widget_render'
  );
});

/**
 * Render widget:
 * - filter dropdown
 * - preview table
 * - print button
 */
function sccc_veterans_widget_render(): void
{
  if (!current_user_can(\App\Support\Admin\UserRoles\OperationalRoles::CAP_USE_DASHBOARD_WIDGETS)) {
    echo '<p>You do not have permission to view this report.</p>';
    return;
  }

  // Filter: all | veterans | first_responders
  $filter = isset($_GET['sccc_vet_filter'])
    ? sanitize_key((string) $_GET['sccc_vet_filter'])
    : 'all';

  if (!in_array($filter, ['all', 'veterans', 'first_responders'], true)) {
    $filter = 'all';
  }

  echo '<form method="get" action="' . esc_url(admin_url('index.php')) . '" style="margin:0 0 12px;">';

  echo '<p style="margin:0 0 10px; display:flex; gap:10px; flex-wrap:wrap; align-items:flex-end;">';

  echo '<label style="display:flex; flex-direction:column; gap:4px;">';
  echo '<span style="font-size:12px; color:#646970;">Show</span>';
  echo '<select name="sccc_vet_filter" class="regular-text" style="min-width:260px;">';
  echo '<option value="all"' . selected($filter, 'all', false) . '>All (Veterans + First Responders)</option>';
  echo '<option value="veterans"' . selected($filter, 'veterans', false) . '>Veterans only</option>';
  echo '<option value="first_responders"' . selected($filter, 'first_responders', false) . '>First Responders only</option>';
  echo '</select>';
  echo '</label>';

  echo '<button type="submit" class="button button-primary">Load list</button>';
  echo '<a class="button" href="' . esc_url(admin_url('index.php')) . '">Reset</a>';

  echo '</p>';

  echo '<p style="margin:0; color:#646970; font-size:12px;">';
  echo 'Sorting: Veterans first, then First Responders.';
  echo '</p>';

  echo '</form>';

  $rows = sccc_get_veterans_report_rows($filter);

  // Print URL
  $printUrl = add_query_arg(
    [
      'action'           => 'sccc_print_veterans',
      'sccc_vet_filter'  => $filter,
      'autoprint'        => 1,
    ],
    admin_url('admin-post.php')
  );
  $printUrl = wp_nonce_url($printUrl, 'sccc_print_veterans');

  echo '<p style="margin:0 0 12px;">';
  echo '<a class="button button-primary" href="' . esc_url($printUrl) . '">Print list</a>';
  echo '</p>';

  if (empty($rows)) {
    echo '<p style="margin:0;">No members found for this report.</p>';
    return;
  }

  // Preview only (first 12)
  $preview = array_slice($rows, 0, 12);

  echo '<div style="max-height: 300px; overflow:auto; border:1px solid #dcdcde; border-radius:6px;">';
  echo '<table class="widefat striped" style="margin:0;">';
  echo '<thead><tr>';
  echo '<th style="width:240px;">Name</th>';
  echo '<th style="width:160px;">Type</th>';
  echo '<th>Branch / Agency</th>';
  echo '</tr></thead><tbody>';

  foreach ($preview as $r) {
    echo '<tr>';
    echo '<td>' . esc_html($r['name']) . '</td>';
    echo '<td>' . esc_html($r['type_label']) . '</td>';
    echo '<td>' . esc_html($r['branch'] !== '' ? $r['branch'] : '—') . '</td>';
    echo '</tr>';
  }

  echo '</tbody></table></div>';

  $total = count($rows);
  if ($total > count($preview)) {
    echo '<p style="margin:.75rem 0 0; color:#646970;">';
    echo esc_html(sprintf('Showing %d of %d. Use “Print list” for the full sheet.', count($preview), $total));
    echo '</p>';
  }
}

/**
 * ---------------------------------------------------------------------------
 * Print endpoint (standalone HTML)
 * ---------------------------------------------------------------------------
 */
add_action('admin_post_sccc_print_veterans', function () {
  if (!current_user_can(\App\Support\Admin\UserRoles\OperationalRoles::CAP_USE_DASHBOARD_WIDGETS)) {
    wp_die('You do not have permission to print this report.');
  }

  check_admin_referer('sccc_print_veterans');

  $filter = isset($_GET['sccc_vet_filter'])
    ? sanitize_key((string) $_GET['sccc_vet_filter'])
    : 'all';

  if (!in_array($filter, ['all', 'veterans', 'first_responders'], true)) {
    $filter = 'all';
  }

  $autoPrint = !empty($_GET['autoprint']);

  $rows = sccc_get_veterans_report_rows($filter);

  $generated = wp_date('F j, Y g:i A');
  $currentUser = wp_get_current_user();
  $generatedBy = ($currentUser && $currentUser->exists())
    ? $currentUser->display_name
    : 'Unknown';

  $filterLabel = [
    'all'              => 'All (Veterans + First Responders)',
    'veterans'         => 'Veterans only',
    'first_responders' => 'First Responders only',
  ][$filter] ?? 'All';

  header('Content-Type: text/html; charset=' . get_option('blog_charset'));
  ?>
  <!doctype html>
  <html lang="en">
    <head>
      <meta charset="<?php echo esc_attr(get_option('blog_charset')); ?>">
      <meta name="viewport" content="width=device-width, initial-scale=1">
      <title><?php echo esc_html("SCCC Veterans Report — {$filterLabel}"); ?></title>
      <style>
        :root { --text:#111827; --muted:#6b7280; --border:#e5e7eb; }

        html, body { margin:0; padding:0; }
        body {
          font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
          color: var(--text);
          background: #fff;
          padding: 28px;
        }

        .topbar{
          display:flex; align-items:flex-start; justify-content:space-between; gap:16px;
          border-bottom: 2px solid var(--border);
          padding-bottom: 14px;
          margin-bottom: 16px;
        }

        h1 { font-size: 20px; line-height: 1.25; margin: 0 0 4px; }
        .meta { margin:0; color: var(--muted); font-size:12px; line-height:1.4; }
        .actions { display:flex; gap:10px; }

        .btn{
          appearance:none;
          border:1px solid #111827;
          background:#111827;
          color:#fff;
          padding:8px 12px;
          border-radius:6px;
          font-size:13px;
          cursor:pointer;
          text-decoration:none;
          display:inline-flex;
          align-items:center;
          justify-content:center;
        }
        .btn.secondary { background:#fff; color:#111827; }

        table { width:100%; border-collapse: collapse; font-size: 13px; }
        thead th { text-align:left; border-bottom:2px solid var(--border); padding:10px 8px; font-weight:700; }
        tbody td { border-bottom:1px solid var(--border); padding:9px 8px; vertical-align:top; }

        .col-name { width: 280px; }
        .col-type { width: 170px; }

        .group-row td{
          background: #f9fafb;
          font-weight: 700;
          border-top: 2px solid var(--border);
        }

        .empty { padding: 12px 0; color: var(--muted); }

        @media print {
          body { padding: 0; }
          .actions { display:none !important; }
          .topbar { border-bottom: 1px solid #000; }
          a[href]::after { content: ""; }
          .group-row td { background: #fff; }
        }
      </style>
    </head>
    <body>
      <div class="topbar">
        <div>
          <h1><?php echo esc_html("SCCC Veterans / First Responders"); ?></h1>
          <p class="meta">
            Filter: <?php echo esc_html($filterLabel); ?><br>
            Generated: <?php echo esc_html($generated); ?><br>
            Generated by: <?php echo esc_html($generatedBy); ?>
          </p>
        </div>

        <div class="actions">
          <button class="btn" onclick="window.print()">Print / Save as PDF</button>
          <a class="btn secondary" href="<?php echo esc_url(admin_url('index.php')); ?>">Back to Dashboard</a>
        </div>
      </div>

      <?php if (empty($rows)) : ?>
        <p class="empty">No members found for this report.</p>
      <?php else : ?>
        <table>
          <thead>
            <tr>
              <th class="col-name">Name</th>
              <th class="col-type">Type</th>
              <th>Branch / Agency</th>
            </tr>
          </thead>
          <tbody>
            <?php
              $currentGroup = null; // "military" | "first_responders"
              foreach ($rows as $r) :
                if ($currentGroup !== $r['group']) {
                  $currentGroup = $r['group'];
                  $label = ($currentGroup === 'military') ? 'Military / Veterans' : 'First Responders / Law / EMS';
                  ?>
                  <tr class="group-row">
                    <td colspan="3"><?php echo esc_html($label); ?></td>
                  </tr>
                  <?php
                }
                ?>
                <tr>
                  <td class="col-name"><?php echo esc_html($r['name']); ?></td>
                  <td class="col-type"><?php echo esc_html($r['type_label']); ?></td>
                  <td><?php echo esc_html($r['branch'] !== '' ? $r['branch'] : '—'); ?></td>
                </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>

      <?php if ($autoPrint) : ?>
        <script>
          window.addEventListener('load', () => setTimeout(() => window.print(), 250));
        </script>
      <?php endif; ?>
    </body>
  </html>
  <?php
  exit;
});

/**
 * ---------------------------------------------------------------------------
 * Data: build sorted rows
 * ---------------------------------------------------------------------------
 *
 * Output rows (already grouped + sorted):
 * - group: military first, then first_responders
 * - type_label: Veteran | First Responder | Both
 *
 * STATUS FILTER:
 * - Active + Grace only (excludes Past Due / Abandoned / Paused)
 */
function sccc_get_veterans_report_rows(string $filter = 'all'): array
{
  // Query only members who have the toggle enabled.
  // ACF true/false is commonly stored as '1' (string) in user meta.
  $query = new WP_User_Query([
    'role'       => SCCC_REPORTS_MEMBER_ROLE,
    'number'     => 9999,
    'orderby'    => 'display_name',
    'order'      => 'ASC',
    'meta_query' => [
      [
        'key'     => SCCC_VET_ENABLED_META,
        'value'   => '1',
        'compare' => '=',
      ],
    ],
  ]);

  $users = $query->get_results();
  if (empty($users)) {
    return [];
  }

  $rows = [];

  foreach ($users as $u) {
    $userId = (int) $u->ID;

    /**
     * STATUS GATE (Active + Grace only)
     * Keeps this report aligned with your “engagement” reporting rules.
     */
    if (!sccc_reports_vets_is_active_or_grace($userId)) {
      continue;
    }

    $type   = sanitize_key((string) get_user_meta($userId, SCCC_VET_TYPE_META, true));
    $branch = sanitize_text_field((string) get_user_meta($userId, SCCC_VET_BRANCH_META, true));

    // Normalize unknown/missing type
    if (!in_array($type, ['veteran', 'first_responder', 'both'], true)) {
      $type = 'unknown';
    }

    // Apply filter
    if ($filter === 'veterans' && !in_array($type, ['veteran', 'both', 'unknown'], true)) {
      continue;
    }

    /**
     * NOTE:
     * For “First Responders only”, we also include “both” because they *are*
     * first responders too — they just also happen to be veterans.
     */
    if ($filter === 'first_responders' && !in_array($type, ['first_responder', 'both'], true)) {
      continue;
    }

    // Grouping: military first (as requested)
    $group = in_array($type, ['veteran', 'both', 'unknown'], true) ? 'military' : 'first_responders';

    $typeLabel = match ($type) {
      'veteran'         => 'Veteran',
      'first_responder' => 'First Responder',
      'both'            => 'Both',
      default           => 'Unknown',
    };

    $rows[] = [
      'id'         => $userId,
      'name'       => (string) $u->display_name,
      'group'      => $group,
      'type'       => $type,
      'type_label' => $typeLabel,
      'branch'     => $branch,
    ];
  }

  if (empty($rows)) {
    return [];
  }

  // Sort:
  // 1) group (military first)
  // 2) branch/agency
  // 3) name
  usort($rows, function ($a, $b) {
    $ga = ($a['group'] === 'military') ? 0 : 1;
    $gb = ($b['group'] === 'military') ? 0 : 1;
    if ($ga !== $gb) return $ga <=> $gb;

    $ba = strtolower(trim((string) $a['branch']));
    $bb = strtolower(trim((string) $b['branch']));
    if ($ba !== $bb) return $ba <=> $bb;

    return strcasecmp((string) $a['name'], (string) $b['name']);
  });

  return $rows;
}
