<?php
/**
 * File: app/Support/Admin/Dashboard/Reports/CarsReport.php
 *
 * SCCC Admin Reports (HTML Print-to-PDF) — Car Search Report
 * ============================================================================
 * PURPOSE
 * - Dashboard widget to search SCCC members by car (Make / Model / Year).
 * - Prints a simple HTML sheet for internal meetings (browser Save as PDF).
 *
 * WHY HTML PRINT (NOT PDF LIBRARY)
 * - Zero server dependencies
 * - Reliable, consistent output for internal use
 *
 * MEMBER SCOPE (UPDATED)
 * - Must have WP role: `sccc_member`
 * - AND must be “Active community member”:
 *   - Include: Active + Grace (0–29 days after expiry)
 *   - Exclude: Past Due (30–59), Abandoned (60+)
 *   - Exclude: Paused (manual override via user meta)
 *
 * DATA MODEL
 * - Fast index meta: `vehicle_index` (lowercased keywords)
 * - ACF user repeater: `vehicles` (actual vehicle rows)
 *
 * IMPORTANT (All fields optional)
 * - You can search with only one field.
 *   Example: Model = "Corvette" returns all members with a Corvette.
 *
 * OUTPUT COLUMNS (requested)
 * - Name, Email, Make, Model, Year
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
 * Manual pause flag (user meta).
 * If set to 1, user is excluded from engagement/community reports.
 */
if (!defined('SCCC_REPORTS_META_PAUSED')) {
  define('SCCC_REPORTS_META_PAUSED', 'sccc_membership_paused');
}

/**
 * ---------------------------------------------------------------------------
 * Report-specific configuration
 * ---------------------------------------------------------------------------
 */
const SCCC_VEHICLE_INDEX_META_KEY = 'vehicle_index';
const SCCC_VEHICLES_FIELD_NAME    = 'vehicles'; // ACF repeater on user

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
    'sccc_cars_search_report',
    'SCCC Car Search (Make / Model / Year)',
    'sccc_cars_widget_render'
  );
});

/**
 * Render the Cars search widget.
 *
 * Fix for “Sorry, you are not allowed to access this page.”
 * - We submit directly back to wp-admin/index.php WITHOUT a `page` parameter.
 */
function sccc_cars_widget_render(): void
{
  if (!current_user_can(\App\Support\Admin\UserRoles\OperationalRoles::CAP_USE_DASHBOARD_WIDGETS)) {
    echo '<p>You do not have permission to view this report.</p>';
    return;
  }

  $make  = isset($_GET['sccc_car_make'])  ? sanitize_text_field((string) $_GET['sccc_car_make'])  : '';
  $model = isset($_GET['sccc_car_model']) ? sanitize_text_field((string) $_GET['sccc_car_model']) : '';
  $year  = isset($_GET['sccc_car_year'])  ? (int) $_GET['sccc_car_year'] : 0;

  // Form (GET so the dashboard refresh shows results; no AJAX needed)
  echo '<form method="get" action="' . esc_url(admin_url('index.php')) . '" style="margin:0 0 12px;">';

  echo '<p style="margin:0 0 10px; display:flex; gap:10px; flex-wrap:wrap; align-items:flex-end;">';

  echo '<label style="display:flex; flex-direction:column; gap:4px;">';
  echo '<span style="font-size:12px; color:#646970;">Make (optional)</span>';
  echo '<input type="text" name="sccc_car_make" value="' . esc_attr($make) . '" class="regular-text" placeholder="e.g. Chevrolet" style="min-width:220px;">';
  echo '</label>';

  echo '<label style="display:flex; flex-direction:column; gap:4px;">';
  echo '<span style="font-size:12px; color:#646970;">Model (optional)</span>';
  echo '<input type="text" name="sccc_car_model" value="' . esc_attr($model) . '" class="regular-text" placeholder="e.g. Corvette" style="min-width:220px;">';
  echo '</label>';

  echo '<label style="display:flex; flex-direction:column; gap:4px;">';
  echo '<span style="font-size:12px; color:#646970;">Year (optional)</span>';
  echo '<input type="number" name="sccc_car_year" value="' . esc_attr($year ?: '') . '" class="small-text" placeholder="e.g. 2019" min="1900" max="2100" style="width:110px;">';
  echo '</label>';

  echo '<button type="submit" class="button button-primary">Search</button>';
  echo '<a class="button" href="' . esc_url(admin_url('index.php')) . '">Reset</a>';

  echo '</p>';

  echo '<p style="margin:0; color:#646970; font-size:12px;">';
  echo 'Scope: Active + Grace members only (excludes Paused, Past Due, Abandoned).';
  echo '</p>';

  echo '</form>';

  $hasSearch = (trim($make) !== '' || trim($model) !== '' || $year > 0);
  if (!$hasSearch) {
    echo '<p style="margin:0;">Enter any value above to search.</p>';
    return;
  }

  $results = sccc_get_members_with_car_match($make, $model, $year);

  // Print URL (re-runs the same query on the print endpoint)
  $printUrl = add_query_arg(
    [
      'action'          => 'sccc_print_cars',
      'sccc_car_make'   => $make,
      'sccc_car_model'  => $model,
      'sccc_car_year'   => $year ?: '',
      'autoprint'       => 1,
    ],
    admin_url('admin-post.php')
  );
  $printUrl = wp_nonce_url($printUrl, 'sccc_print_cars');

  // Human-readable query label
  $qParts = array_filter([trim($make), trim($model)]);
  $queryLabel = !empty($qParts) ? implode(' ', $qParts) : '—';
  $yearLabel  = $year > 0 ? (string) $year : 'Any';

  echo '<p style="margin:0 0 10px;">';
  echo 'Query: <strong>' . esc_html($queryLabel) . '</strong> &nbsp;|&nbsp; Year: <strong>' . esc_html($yearLabel) . '</strong>';
  echo '</p>';

  echo '<p style="margin:0 0 12px;">';
  echo '<a class="button button-primary" href="' . esc_url($printUrl) . '">Print list</a>';
  echo '</p>';

  if (empty($results)) {
    echo '<p style="margin:0;">No members matched this car search.</p>';
    return;
  }

  // Preview first 12 rows (rows are per matched VEHICLE, not per member)
  $preview = array_slice($results, 0, 12);

  echo '<div style="max-height: 300px; overflow:auto; border:1px solid #dcdcde; border-radius:6px;">';
  echo '<table class="widefat striped" style="margin:0;">';
  echo '<thead><tr>';
  echo '<th style="width:220px;">Name</th>';
  echo '<th style="width:260px;">Email</th>';
  echo '<th style="width:170px;">Make</th>';
  echo '<th style="width:200px;">Model</th>';
  echo '<th style="width:80px;">Year</th>';
  echo '</tr></thead><tbody>';

  foreach ($preview as $row) {
    echo '<tr>';
    echo '<td>' . esc_html($row['name']) . '</td>';
    echo '<td>' . esc_html($row['email']) . '</td>';
    echo '<td>' . esc_html($row['make'] ?: '—') . '</td>';
    echo '<td>' . esc_html($row['model'] ?: '—') . '</td>';
    echo '<td>' . esc_html($row['year'] ? (string) $row['year'] : '—') . '</td>';
    echo '</tr>';
  }

  echo '</tbody></table></div>';

  $total = count($results);
  if ($total > count($preview)) {
    echo '<p style="margin:.75rem 0 0; color:#646970;">';
    echo esc_html(sprintf('Showing %d of %d rows. Use “Print list” for the full sheet.', count($preview), $total));
    echo '</p>';
  }
}

/**
 * ---------------------------------------------------------------------------
 * Print endpoint (standalone HTML)
 * ---------------------------------------------------------------------------
 */
add_action('admin_post_sccc_print_cars', function () {
  if (!current_user_can(\App\Support\Admin\UserRoles\OperationalRoles::CAP_USE_DASHBOARD_WIDGETS)) {
    wp_die('You do not have permission to print this report.');
  }

  check_admin_referer('sccc_print_cars');

  $make  = isset($_GET['sccc_car_make'])  ? sanitize_text_field((string) $_GET['sccc_car_make'])  : '';
  $model = isset($_GET['sccc_car_model']) ? sanitize_text_field((string) $_GET['sccc_car_model']) : '';
  $year  = isset($_GET['sccc_car_year'])  ? (int) $_GET['sccc_car_year'] : 0;

  $autoPrint = !empty($_GET['autoprint']);

  $results = sccc_get_members_with_car_match($make, $model, $year);

  $generated = wp_date('F j, Y g:i A');
  $currentUser = wp_get_current_user();
  $generatedBy = ($currentUser && $currentUser->exists())
    ? $currentUser->display_name
    : 'Unknown';

  $qParts = array_filter([trim($make), trim($model)]);
  $queryLabel = !empty($qParts) ? implode(' ', $qParts) : '—';
  $yearLabel  = $year > 0 ? (string) $year : 'Any';

  header('Content-Type: text/html; charset=' . get_option('blog_charset'));

  ?>
  <!doctype html>
  <html lang="en">
    <head>
      <meta charset="<?php echo esc_attr(get_option('blog_charset')); ?>">
      <meta name="viewport" content="width=device-width, initial-scale=1">
      <title><?php echo esc_html("SCCC Car Search — {$queryLabel} ({$yearLabel})"); ?></title>
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

        .col-name  { width: 240px; }
        .col-email { width: 34%; }
        .col-year  { width: 80px; }

        .empty { padding: 12px 0; color: var(--muted); }

        @media print {
          body { padding: 0; }
          .actions { display:none !important; }
          .topbar { border-bottom: 1px solid #000; }
          a[href]::after { content: ""; }
        }
      </style>
    </head>
    <body>
      <div class="topbar">
        <div>
          <h1><?php echo esc_html("SCCC Car Search — {$queryLabel}"); ?></h1>
          <p class="meta">
            Year: <?php echo esc_html($yearLabel); ?><br>
            Scope: Active + Grace members only (excludes Paused, Past Due, Abandoned).<br>
            Generated: <?php echo esc_html($generated); ?><br>
            Generated by: <?php echo esc_html($generatedBy); ?>
          </p>
        </div>

        <div class="actions">
          <button class="btn" onclick="window.print()">Print / Save as PDF</button>
          <a class="btn secondary" href="<?php echo esc_url(admin_url('index.php')); ?>">Back to Dashboard</a>
        </div>
      </div>

      <?php if (empty($results)) : ?>
        <p class="empty">No members matched this car search.</p>
      <?php else : ?>
        <table>
          <thead>
            <tr>
              <th class="col-name">Name</th>
              <th class="col-email">Email</th>
              <th>Make</th>
              <th>Model</th>
              <th class="col-year">Year</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($results as $row) : ?>
              <tr>
                <td class="col-name"><?php echo esc_html($row['name']); ?></td>
                <td class="col-email"><?php echo esc_html($row['email']); ?></td>
                <td><?php echo esc_html($row['make'] ?: '—'); ?></td>
                <td><?php echo esc_html($row['model'] ?: '—'); ?></td>
                <td class="col-year"><?php echo esc_html($row['year'] ? (string) $row['year'] : '—'); ?></td>
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
 * Core logic: find members that match a car search
 * ---------------------------------------------------------------------------
 *
 * Returns ROWS PER MATCHING VEHICLE (meeting-friendly export):
 * [
 *   [ 'id'=>123, 'name'=>'Jane', 'email'=>'jane@x.com', 'make'=>'Chevrolet', 'model'=>'Corvette', 'year'=>2019 ],
 *   ...
 * ]
 */
function sccc_get_members_with_car_match(string $make, string $model, int $year): array
{
  $makeNorm  = sccc_reports_norm($make);
  $modelNorm = sccc_reports_norm($model);
  $year      = $year > 0 ? $year : 0;

  // Build an index term to narrow candidates when we can.
  // If both make + model are empty (year-only), we cannot use the index reliably.
  $indexTerm = trim($makeNorm . ' ' . $modelNorm);

  $args = [
    'role'    => SCCC_REPORTS_MEMBER_ROLE,
    'number'  => 9999,
    'orderby' => 'display_name',
    'order'   => 'ASC',
  ];

  // If we have a make/model keyword, use vehicle_index to narrow candidates.
  if ($indexTerm !== '') {
    $args['meta_query'] = [
      [
        'key'     => SCCC_VEHICLE_INDEX_META_KEY,
        'compare' => 'EXISTS',
      ],
      [
        'key'     => SCCC_VEHICLE_INDEX_META_KEY,
        'value'   => '',
        'compare' => '!=',
      ],
      [
        'key'     => SCCC_VEHICLE_INDEX_META_KEY,
        'value'   => $indexTerm,
        'compare' => 'LIKE',
      ],
    ];
  }

  $query = new WP_User_Query($args);
  $users = $query->get_results();

  if (empty($users)) {
    return [];
  }

  $rows = [];

  foreach ($users as $user) {
    $userId = (int) $user->ID;

    // NEW: enforce “Active + Grace” scope (and exclude paused)
    if (!sccc_reports_is_active_or_grace_member($userId)) {
      continue;
    }

    $matches = sccc_reports_get_vehicle_matches_for_user(
      $userId,
      $makeNorm,
      $modelNorm,
      $year
    );

    foreach ($matches as $m) {
      $rows[] = [
        'id'    => $userId,
        'name'  => (string) $user->display_name,
        'email' => (string) $user->user_email,
        'make'  => $m['make'],
        'model' => $m['model'],
        'year'  => $m['year'],
      ];
    }
  }

  // Sort rows by Make, Model, Year desc, then Name (nice for meetings)
  usort($rows, function ($a, $b) {
    $am = strtolower((string) $a['make']);
    $bm = strtolower((string) $b['make']);
    if ($am !== $bm) return $am <=> $bm;

    $amo = strtolower((string) $a['model']);
    $bmo = strtolower((string) $b['model']);
    if ($amo !== $bmo) return $amo <=> $bmo;

    $ay = (int) ($a['year'] ?: 0);
    $by = (int) ($b['year'] ?: 0);
    if ($ay !== $by) return $by <=> $ay;

    return strcasecmp((string) $a['name'], (string) $b['name']);
  });

  return $rows;
}

/**
 * Read the user’s ACF `vehicles` repeater and return only rows that match.
 *
 * Matching rules (all optional):
 * - If make keyword provided: match if vehicle make contains it
 * - If model keyword provided: match if vehicle model contains it
 * - If year provided: match if year equals
 *
 * If all three are empty, returns [].
 */
function sccc_reports_get_vehicle_matches_for_user(int $userId, string $makeNorm, string $modelNorm, int $year): array
{
  // No criteria → no results (prevents accidental “everything”)
  if ($makeNorm === '' && $modelNorm === '' && $year <= 0) {
    return [];
  }

  $vehicles = [];

  // Preferred: ACF API
  if (function_exists('get_field')) {
    $acfVehicles = get_field(SCCC_VEHICLES_FIELD_NAME, 'user_' . $userId);
    if (is_array($acfVehicles)) {
      $vehicles = $acfVehicles;
    }
  }

  if (empty($vehicles)) {
    return [];
  }

  $matches = [];

  foreach ($vehicles as $v) {
    if (!is_array($v)) {
      continue;
    }

    $vYear = isset($v['year']) ? (int) $v['year'] : 0;

    // Make (try several common keys)
    $vMake = '';
    if (!empty($v['make_raw'])) {
      $vMake = (string) $v['make_raw'];
    } elseif (!empty($v['make_select'])) {
      $vMake = (string) $v['make_select'];
    } elseif (!empty($v['make_other'])) {
      $vMake = (string) $v['make_other'];
    } elseif (!empty($v['make'])) {
      $vMake = (string) $v['make'];
    }

    $vModel = !empty($v['model']) ? (string) $v['model'] : '';

    $vMakeNorm  = sccc_reports_norm($vMake);
    $vModelNorm = sccc_reports_norm($vModel);

    if ($makeNorm !== '' && strpos($vMakeNorm, $makeNorm) === false) {
      continue;
    }
    if ($modelNorm !== '' && strpos($vModelNorm, $modelNorm) === false) {
      continue;
    }
    if ($year > 0 && $vYear !== $year) {
      continue;
    }

    $matches[] = [
      'year'  => $vYear ?: '',
      'make'  => $vMake,
      'model' => $vModel,
    ];
  }

  return $matches;
}

/**
 * Normalize strings for matching:
 * - lower-case
 * - trim
 * - collapse whitespace
 */
function sccc_reports_norm(string $s): string
{
  $s = strtolower(trim($s));
  $s = preg_replace('/\s+/', ' ', $s);
  return $s ?: '';
}

/**
 * ---------------------------------------------------------------------------
 * MEMBER ELIGIBILITY (Active + Grace) — shared logic
 * ---------------------------------------------------------------------------
 *
 * We keep this inside the report file so it works “drop-in”, but we guard all
 * function names to prevent redeclare errors if multiple report files define
 * similar helpers.
 */

if (!function_exists('sccc_reports_pmpro_memberships_users_table')) {
  function sccc_reports_pmpro_memberships_users_table(): string
  {
    global $wpdb;

    // PMPro often defines $wpdb->pmpro_memberships_users; use it if present.
    if (isset($wpdb->pmpro_memberships_users) && !empty($wpdb->pmpro_memberships_users)) {
      return (string) $wpdb->pmpro_memberships_users;
    }

    return (string) ($wpdb->prefix . 'pmpro_memberships_users');
  }
}

if (!function_exists('sccc_reports_pmpro_memberships_users_table_exists')) {
  function sccc_reports_pmpro_memberships_users_table_exists(): bool
  {
    global $wpdb;

    $table = sccc_reports_pmpro_memberships_users_table();
    if ($table === '') {
      return false;
    }

    $like  = $wpdb->esc_like($table);
    $found = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $like)); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

    return !empty($found);
  }
}

if (!function_exists('sccc_reports_get_latest_pmpro_row')) {
  function sccc_reports_get_latest_pmpro_row(int $userId): ?array
  {
    global $wpdb;

    $table = sccc_reports_pmpro_memberships_users_table();
    if ($table === '' || !sccc_reports_pmpro_memberships_users_table_exists()) {
      return null;
    }

    $sql = "
      SELECT id, status, startdate, enddate, modified, membership_id
      FROM {$table}
      WHERE user_id = %d
      ORDER BY modified DESC, id DESC
      LIMIT 1
    ";

    $row = $wpdb->get_row($wpdb->prepare($sql, $userId), ARRAY_A); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

    return is_array($row) ? $row : null;
  }
}

if (!function_exists('sccc_reports_parse_enddate_to_ts')) {
  function sccc_reports_parse_enddate_to_ts($enddate): ?int
  {
    if ($enddate === null) {
      return null;
    }

    $enddate = is_string($enddate) ? trim($enddate) : '';
    if ($enddate === '' || $enddate === '0000-00-00 00:00:00') {
      return null; // “no expiration”
    }

    $ts = strtotime($enddate);
    return $ts ? (int) $ts : null;
  }
}

if (!function_exists('sccc_reports_is_active_or_grace_member')) {
  function sccc_reports_is_active_or_grace_member(int $userId): bool
  {
    // Manual pause wins.
    if ((int) get_user_meta($userId, SCCC_REPORTS_META_PAUSED, true) === 1) {
      return false;
    }

    // If PMPro table is missing, we can’t reliably decide. Safer to exclude.
    if (!sccc_reports_pmpro_memberships_users_table_exists()) {
      return false;
    }

    $row = sccc_reports_get_latest_pmpro_row($userId);
    if (!$row) {
      return false;
    }

    $now = current_time('timestamp');

    $status = isset($row['status']) ? (string) $row['status'] : '';
    $end_ts = sccc_reports_parse_enddate_to_ts($row['enddate'] ?? null);

    $has_end = ($end_ts !== null);
    $active_by_enddate = (!$has_end) || ($end_ts > $now);

    // Active: PMPro says active AND enddate still valid (or no enddate).
    if ($status === 'active' && $active_by_enddate) {
      return true;
    }

    // Grace: ended within the last 0–29 days.
    if ($has_end && $end_ts <= $now) {
      $days = (int) floor(max(0, $now - $end_ts) / DAY_IN_SECONDS);
      return ($days >= 0 && $days < 30);
    }

    return false;
  }
}
