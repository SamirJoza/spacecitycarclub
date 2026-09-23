<?php

namespace App\Support\Admin\Dashboard\Reports;

use App\Support\Admin\UserRoles\OperationalRoles;
use WP_User_Query;

/**
 * File: app/Support/Admin/Dashboard/Reports/MembershipTrendsReport.php
 *
 * SCCC Admin Dashboard Widget — Membership Trends (PMPro)
 * ============================================================================
 * GOALS (per your latest request)
 * - Lives INSIDE the normal Dashboard widget stack (no footer / overlay behavior)
 * - Light theme styling so it matches the rest of wp-admin
 * - Role-gated: ONLY users with the additional WP role `sccc_member` are counted
 *
 * WHAT IT SHOWS (3 simple charts)
 * 1) Active vs Inactive (line)
 * 2) Signups per interval (bar)
 * 3) Expirations per interval (bar)
 *
 * TERMS
 * - “Interval” = how we group time (daily / weekly / monthly / yearly)
 * - “Bucket”   = one interval slice (example: one week if interval=weekly)
 *
 * DATA SOURCE
 * - PMPro table: {$wpdb->prefix}pmpro_memberships_users
 *   Uses startdate + enddate + status
 *
 * ACTIVE (as-of a bucket end time)
 * - status='active'
 * - startdate is NULL/0000 or startdate <= bucketEnd
 * - enddate   is NULL/0000 or enddate   >= bucketEnd
 *
 * EXPIRATIONS (per bucket)
 * - DISTINCT users whose enddate falls inside the bucket window
 */

if (!defined('ABSPATH')) {
  exit;
}

final class MembershipTrendsReport
{
  /**
   * Only users with this role are counted (filters out shoppers).
   */
  private const MEMBER_ROLE = 'sccc_member';

  /**
   * AJAX action.
   */
  private const AJAX_ACTION = 'sccc_membership_trends_data';

  /**
   * Cache keys.
   */
  private const TRANSIENT_MEMBER_IDS     = 'sccc_trends_member_ids_v2';
  private const TRANSIENT_MEMBER_IDS_TTL = 6 * HOUR_IN_SECONDS;

  private const TRANSIENT_SERIES_PREFIX  = 'sccc_trends_series_v3_';
  private const TRANSIENT_SERIES_TTL     = 30 * MINUTE_IN_SECONDS;

  /**
   * Widget + defaults.
   */
  private const WIDGET_ID    = 'sccc_membership_trends';
  private const WIDGET_TITLE = 'SCCC Membership Trends';

  private const DEFAULT_RANGE    = '12w';
  private const DEFAULT_INTERVAL = 'weekly';

  public static function register(): void
  {
    if (!is_admin()) {
      return;
    }

    add_action('wp_dashboard_setup', [self::class, 'addWidget']);
    add_action('admin_enqueue_scripts', [self::class, 'enqueue']);
    add_action('wp_ajax_' . self::AJAX_ACTION, [self::class, 'ajaxGetData']);
  }

  /**
   * Register the dashboard widget in the normal stack (no overlay/footer tricks).
   */
  public static function addWidget(): void
  {
    if (!current_user_can(OperationalRoles::CAP_USE_DASHBOARD_WIDGETS)) {
      return;
    }

    wp_add_dashboard_widget(
      self::WIDGET_ID,
      self::WIDGET_TITLE,
      [self::class, 'renderWidget']
    );
  }

  /**
   * Load Chart.js + inline CSS/JS only on the Dashboard screen.
   */
  public static function enqueue(string $hookSuffix): void
  {
    if ($hookSuffix !== 'index.php') {
      return;
    }

    if (!current_user_can(OperationalRoles::CAP_USE_DASHBOARD_WIDGETS)) {
      return;
    }

    // Chart.js
    wp_enqueue_script(
      'sccc-chartjs',
      'https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js',
      [],
      '4.4.3',
      true
    );

    // Light-mode CSS
    wp_add_inline_style('wp-admin', self::adminCss());

    // Boot JS (after Chart.js)
    wp_add_inline_script('sccc-chartjs', self::adminJsBoot(), 'after');
  }

  /**
   * Widget markup. Charts are rendered by JS after AJAX returns.
   */
  public static function renderWidget(): void
  {
    if (!current_user_can(OperationalRoles::CAP_USE_DASHBOARD_WIDGETS)) {
      echo '<p>You do not have permission to view this widget.</p>';
      return;
    }

    $nonce = wp_create_nonce(self::AJAX_ACTION);
    $pmproReportsUrl = admin_url('admin.php?page=pmpro-reports');

    echo '<div id="sccc-trends-root" class="sccc-trends"'
      . ' data-ajax="' . esc_attr(admin_url('admin-ajax.php')) . '"'
      . ' data-nonce="' . esc_attr($nonce) . '"'
      . ' data-default-range="' . esc_attr(self::DEFAULT_RANGE) . '"'
      . ' data-default-interval="' . esc_attr(self::DEFAULT_INTERVAL) . '"'
      . '>';

    // Header + controls
    echo '<div class="sccc-trends__header">';
    echo '  <div class="sccc-trends__heading">';
    echo '    <div class="sccc-trends__title">Membership Trends</div>';
    echo '    <div class="sccc-trends__sub">';
    echo '      Scope: role <strong>' . esc_html(self::MEMBER_ROLE) . '</strong> only. ';
    echo '      Need more? <a href="' . esc_url($pmproReportsUrl) . '">PMPro Reports</a>.';
    echo '    </div>';
    echo '  </div>';

    echo '  <div class="sccc-trends__controls">';
    echo '    <label class="sccc-trends__field">';
    echo '      <span>Interval</span>';
    echo '      <select id="sccc-trends-interval">';
    echo '        <option value="daily">Daily</option>';
    echo '        <option value="weekly">Weekly</option>';
    echo '        <option value="monthly">Monthly</option>';
    echo '        <option value="yearly">Yearly</option>';
    echo '      </select>';
    echo '    </label>';

    echo '    <label class="sccc-trends__field">';
    echo '      <span>Range</span>';
    echo '      <select id="sccc-trends-range">';
    echo '        <option value="12w">Last 12 weeks</option>';
    echo '        <option value="90d">Last 90 days</option>';
    echo '        <option value="180d">Last 180 days</option>';
    echo '        <option value="1y">Last 1 year</option>';
    echo '        <option value="ytd">Year-to-date</option>';
    echo '        <option value="all">Since inception</option>';
    echo '      </select>';
    echo '    </label>';

    echo '    <button type="button" class="button button-primary" id="sccc-trends-update">Update</button>';
    echo '    <button type="button" class="button" id="sccc-trends-reset">Reset</button>';
    echo '    <a class="button" href="' . esc_url($pmproReportsUrl) . '">PMPro Reports</a>';
    echo '  </div>';
    echo '</div>';

    // KPIs
    echo '<div class="sccc-trends__kpis">';
    echo '  <div class="kpi"><div class="k">Active (latest)</div><div class="v" id="kpi-active">—</div></div>';
    echo '  <div class="kpi"><div class="k">Inactive (latest)</div><div class="v" id="kpi-inactive">—</div></div>';
    echo '  <div class="kpi"><div class="k">Total members</div><div class="v" id="kpi-total">—</div></div>';
    echo '  <div class="kpi"><div class="k">Signups (range)</div><div class="v" id="kpi-signups">—</div></div>';
    echo '  <div class="kpi"><div class="k">Expirations (range)</div><div class="v" id="kpi-expirations">—</div></div>';
    echo '</div>';

    // Charts (stacked for readability)
    echo '<div class="sccc-trends__panels">';
    echo '  <div class="panel">';
    echo '    <div class="panel__title">Active vs Inactive Members</div>';
    echo '    <div class="panel__canvas"><canvas id="chart-active-inactive" height="150"></canvas></div>';
    echo '  </div>';

    echo '  <div class="panel">';
    echo '    <div class="panel__title">Signups per Interval</div>';
    echo '    <div class="panel__canvas"><canvas id="chart-signups" height="140"></canvas></div>';
    echo '  </div>';

    echo '  <div class="panel">';
    echo '    <div class="panel__title">Expirations per Interval</div>';
    echo '    <div class="panel__canvas"><canvas id="chart-expirations" height="140"></canvas></div>';
    echo '  </div>';
    echo '</div>';

    // Footer note
    echo '<div class="sccc-trends__footer">';
    echo '  <span class="note">Active/Inactive are counts “as of” each interval end. Interval = the grouping size (a “bucket”).</span>';
    echo '  <span class="status" id="sccc-trends-status">Ready.</span>';
    echo '</div>';

    echo '</div>';
  }

  /**
   * AJAX endpoint (returns data series for charts).
   */
  public static function ajaxGetData(): void
  {
    if (!current_user_can(OperationalRoles::CAP_USE_DASHBOARD_WIDGETS)) {
      wp_send_json_error(['message' => 'forbidden'], 403);
    }

    check_ajax_referer(self::AJAX_ACTION, 'nonce');

    $range    = isset($_POST['range']) ? sanitize_key((string) $_POST['range']) : self::DEFAULT_RANGE;
    $interval = isset($_POST['interval']) ? sanitize_key((string) $_POST['interval']) : self::DEFAULT_INTERVAL;

    $allowedRanges = ['12w', '90d', '180d', '1y', 'ytd', 'all'];
    $allowedIntervals = ['daily', 'weekly', 'monthly', 'yearly'];

    if (!in_array($range, $allowedRanges, true)) {
      $range = self::DEFAULT_RANGE;
    }
    if (!in_array($interval, $allowedIntervals, true)) {
      $interval = self::DEFAULT_INTERVAL;
    }

    $data = self::getSeriesCached($range, $interval);

    wp_send_json_success($data);
  }

  private static function getSeriesCached(string $range, string $interval): array
  {
    $key = self::TRANSIENT_SERIES_PREFIX . md5($range . '|' . $interval);

    $cached = get_transient($key);
    if (is_array($cached) && isset($cached['labels'])) {
      return $cached;
    }

    $data = self::buildSeries($range, $interval);

    set_transient($key, $data, self::TRANSIENT_SERIES_TTL);

    return $data;
  }

  /**
   * Build chart series.
   */
  private static function buildSeries(string $range, string $interval): array
  {
    $memberIds = self::getMemberIdsCached();
    $totalMembers = count($memberIds);

    if ($totalMembers <= 0) {
      return self::emptyPayload();
    }

    [$startTs, $endTs] = self::resolveRangeWindow($range);

    // If range is “Last 12 weeks”, force weekly buckets (still respects interval UI,
    // but this range is defined in weeks, so weekly makes the most sense).
    if ($range === '12w') {
      $interval = 'weekly';
      $startTs = strtotime('-12 weeks', $endTs);
    }

    $buckets = self::buildBuckets($startTs, $endTs, $interval);
    if (empty($buckets)) {
      return self::emptyPayload($totalMembers);
    }

    $periodsByUser = self::getPmproPeriodsByUser($memberIds);
    $firstStartByUser = self::computeFirstStartByUser($periodsByUser);

    $labels = [];
    $active = [];
    $inactive = [];
    $signups = [];
    $expirations = [];

    $sumSignups = 0;
    $sumExpirations = 0;

    foreach ($buckets as $b) {
      $bucketStart = $b['start'];
      $bucketEnd   = $b['end'];

      $labels[] = $b['label'];

      $activeCount = self::countActiveAt($periodsByUser, $bucketEnd);
      $inactiveCount = max(0, $totalMembers - $activeCount);

      $active[] = $activeCount;
      $inactive[] = $inactiveCount;

      // Signups = users whose earliest membership start falls in this bucket
      $s = 0;
      foreach ($firstStartByUser as $ts) {
        if ($ts !== null && $ts >= $bucketStart && $ts < $bucketEnd) {
          $s++;
        }
      }
      $signups[] = $s;
      $sumSignups += $s;

      // Expirations = DISTINCT users whose enddate falls in this bucket
      $e = self::countExpirationsDistinctUsers($periodsByUser, $bucketStart, $bucketEnd);
      $expirations[] = $e;
      $sumExpirations += $e;
    }

    $latestActive = (int) (end($active) ?: 0);
    $latestInactive = (int) (end($inactive) ?: 0);

    return [
      'labels'      => $labels,
      'active'      => $active,
      'inactive'    => $inactive,
      'signups'     => $signups,
      'expirations' => $expirations,
      'kpis'        => [
        'total'       => $totalMembers,
        'active'      => $latestActive,
        'inactive'    => $latestInactive,
        'signups'     => $sumSignups,
        'expirations' => $sumExpirations,
      ],
    ];
  }

  private static function emptyPayload(int $totalMembers = 0): array
  {
    return [
      'labels'      => [],
      'active'      => [],
      'inactive'    => [],
      'signups'     => [],
      'expirations' => [],
      'kpis'        => [
        'total'       => $totalMembers,
        'active'      => 0,
        'inactive'    => $totalMembers,
        'signups'     => 0,
        'expirations' => 0,
      ],
    ];
  }

  /**
   * Cache member IDs by role (filters out shoppers).
   */
  private static function getMemberIdsCached(): array
  {
    $cached = get_transient(self::TRANSIENT_MEMBER_IDS);
    if (is_array($cached) && !empty($cached)) {
      return array_values(array_filter(array_map('intval', $cached)));
    }

    $q = new WP_User_Query([
      'role'   => self::MEMBER_ROLE,
      'fields' => 'ID', // IMPORTANT: returns ints (fixes stdClass->int issue)
      'number' => 0,
    ]);

    $ids = $q->get_results();
    $ids = is_array($ids) ? array_values(array_filter(array_map('intval', $ids))) : [];

    set_transient(self::TRANSIENT_MEMBER_IDS, $ids, self::TRANSIENT_MEMBER_IDS_TTL);

    return $ids;
  }

  /**
   * Resolve time window based on selected range.
   */
  private static function resolveRangeWindow(string $range): array
  {
    $end = current_time('timestamp');

    if ($range === '90d')  return [strtotime('-90 days', $end), $end];
    if ($range === '180d') return [strtotime('-180 days', $end), $end];
    if ($range === '1y')   return [strtotime('-1 year', $end), $end];

    if ($range === 'ytd') {
      $year = (int) wp_date('Y', $end);
      return [strtotime($year . '-01-01 00:00:00'), $end];
    }

    if ($range === 'all') {
      $earliest = self::getEarliestPmproStartdateTs();
      if ($earliest && $earliest < $end) {
        return [$earliest, $end];
      }
      // Fallback if table is empty
      return [strtotime('-3 years', $end), $end];
    }

    // Default
    return [strtotime('-12 weeks', $end), $end];
  }

  /**
   * Used for “Since inception” range.
   */
  private static function getEarliestPmproStartdateTs(): ?int
  {
    global $wpdb;

    $mu = $wpdb->prefix . 'pmpro_memberships_users';

    $raw = $wpdb->get_var("
      SELECT MIN(startdate)
      FROM {$mu}
      WHERE startdate IS NOT NULL
        AND startdate <> '0000-00-00 00:00:00'
    ");

    $raw = is_string($raw) ? trim($raw) : '';
    if ($raw === '') return null;

    $ts = strtotime($raw);
    return $ts ?: null;
  }

  /**
   * Create buckets between start/end.
   */
  private static function buildBuckets(int $startTs, int $endTs, string $interval): array
  {
    $startTs = max(0, $startTs);
    $endTs   = max($startTs + 1, $endTs);

    // Align to clean boundaries
    if ($interval === 'daily') {
      $cursor = strtotime(wp_date('Y-m-d 00:00:00', $startTs));
    } elseif ($interval === 'weekly') {
      $dow = (int) wp_date('N', $startTs); // 1..7
      $cursor = strtotime('-' . ($dow - 1) . ' days', strtotime(wp_date('Y-m-d 00:00:00', $startTs)));
    } elseif ($interval === 'monthly') {
      $cursor = strtotime(wp_date('Y-m-01 00:00:00', $startTs));
    } else { // yearly
      $y = (int) wp_date('Y', $startTs);
      $cursor = strtotime($y . '-01-01 00:00:00');
    }

    $buckets = [];
    $guard = 0;

    while ($cursor < $endTs && $guard < 5000) {
      $guard++;

      if ($interval === 'daily') {
        $next = strtotime('+1 day', $cursor);
        $label = wp_date('M j', $cursor);
      } elseif ($interval === 'weekly') {
        $next = strtotime('+7 days', $cursor);
        // Readable weekly label: "Jan 8"
        $label = wp_date('M j', $cursor);
      } elseif ($interval === 'monthly') {
        $next = strtotime('+1 month', $cursor);
        $label = wp_date('M Y', $cursor);
      } else {
        $next = strtotime('+1 year', $cursor);
        $label = wp_date('Y', $cursor);
      }

      $bucketStart = $cursor;
      $bucketEnd   = min($next, $endTs);

      if ($bucketEnd > $bucketStart) {
        $buckets[] = [
          'start' => $bucketStart,
          'end'   => $bucketEnd,
          'label' => $label,
        ];
      }

      $cursor = $next;
    }

    return $buckets;
  }

  /**
   * Fetch PMPro membership rows for these users and group by user_id.
   */
  private static function getPmproPeriodsByUser(array $userIds): array
  {
    global $wpdb;

    $userIds = array_values(array_filter(array_map('intval', $userIds)));
    if (empty($userIds)) {
      return [];
    }

    $mu = $wpdb->prefix . 'pmpro_memberships_users';

    $out = [];

    foreach (array_chunk($userIds, 500) as $chunk) {
      $placeholders = implode(',', array_fill(0, count($chunk), '%d'));
      $sql = $wpdb->prepare("
        SELECT user_id, status, startdate, enddate
        FROM {$mu}
        WHERE user_id IN ({$placeholders})
      ", ...$chunk);

      $rows = $wpdb->get_results($sql);
      if (empty($rows)) {
        continue;
      }

      foreach ($rows as $r) {
        $uid = (int) ($r->user_id ?? 0);
        if ($uid <= 0) continue;

        $status = is_string($r->status) ? strtolower(trim($r->status)) : '';

        $startRaw = is_string($r->startdate) ? trim($r->startdate) : '';
        $endRaw   = is_string($r->enddate) ? trim($r->enddate) : '';

        $startTs = ($startRaw !== '' && $startRaw !== '0000-00-00 00:00:00') ? strtotime($startRaw) : null;
        $endTs   = ($endRaw !== '' && $endRaw !== '0000-00-00 00:00:00') ? strtotime($endRaw) : null;

        $out[$uid][] = [
          'status' => $status,
          'start'  => $startTs ?: null,
          'end'    => $endTs ?: null,
        ];
      }
    }

    // Ensure all members exist in map
    foreach ($userIds as $uid) {
      if (!isset($out[$uid])) $out[$uid] = [];
    }

    return $out;
  }

  /**
   * Earliest start per user (for signups).
   */
  private static function computeFirstStartByUser(array $periodsByUser): array
  {
    $out = [];

    foreach ($periodsByUser as $uid => $periods) {
      $min = null;

      if (is_array($periods)) {
        foreach ($periods as $p) {
          $s = $p['start'] ?? null;
          if ($s && (!$min || $s < $min)) {
            $min = $s;
          }
        }
      }

      $out[(int) $uid] = $min;
    }

    return $out;
  }

  /**
   * Count how many role-members are active at time T.
   */
  private static function countActiveAt(array $periodsByUser, int $t): int
  {
    $count = 0;

    foreach ($periodsByUser as $periods) {
      if (!is_array($periods) || empty($periods)) {
        continue;
      }

      $isActive = false;

      foreach ($periods as $p) {
        if (($p['status'] ?? '') !== 'active') {
          continue;
        }

        $start = $p['start'] ?? null;
        $end   = $p['end'] ?? null;

        // Startdate in future => not active yet
        if ($start !== null && $start > $t) {
          continue;
        }

        // No enddate => active
        if ($end === null) {
          $isActive = true;
          break;
        }

        // Enddate after time => active
        if ($end >= $t) {
          $isActive = true;
          break;
        }
      }

      if ($isActive) {
        $count++;
      }
    }

    return $count;
  }

  /**
   * Count DISTINCT users who expire in a bucket.
   */
  private static function countExpirationsDistinctUsers(array $periodsByUser, int $start, int $end): int
  {
    $expiringUsers = [];

    foreach ($periodsByUser as $uid => $periods) {
      if (!is_array($periods) || empty($periods)) {
        continue;
      }

      foreach ($periods as $p) {
        $e = $p['end'] ?? null;
        if ($e === null) continue;

        if ($e >= $start && $e < $end) {
          $expiringUsers[(int) $uid] = true;
          break;
        }
      }
    }

    return count($expiringUsers);
  }

  /**
   * Light widget styling (matches wp-admin).
   */
  private static function adminCss(): string
  {
    return <<<CSS
/* Membership Trends widget (light) */
#sccc-trends-root.sccc-trends{
  display:block;
}

#sccc-trends-root .sccc-trends__header{
  display:flex;
  justify-content:space-between;
  align-items:flex-start;
  gap:12px;
  flex-wrap:wrap;
  margin-bottom: 12px;
}

#sccc-trends-root .sccc-trends__title{
  font-size: 16px;
  font-weight: 700;
  color:#111827;
  margin: 0 0 2px;
}

#sccc-trends-root .sccc-trends__sub{
  font-size: 12px;
  color:#50575e;
}

#sccc-trends-root .sccc-trends__sub a{
  color:#2271b1;
  text-decoration:none;
}
#sccc-trends-root .sccc-trends__sub a:hover{
  text-decoration:underline;
}

#sccc-trends-root .sccc-trends__controls{
  display:flex;
  align-items:flex-end;
  gap:10px;
  flex-wrap:wrap;
}

#sccc-trends-root .sccc-trends__field{
  display:flex;
  flex-direction:column;
  gap:4px;
}

#sccc-trends-root .sccc-trends__field span{
  font-size:12px;
  color:#50575e;
}

#sccc-trends-root select{
  min-width: 180px;
  border-radius: 6px;
}

#sccc-trends-root .sccc-trends__kpis{
  display:grid;
  grid-template-columns: repeat(5, minmax(0,1fr));
  gap:10px;
  margin: 8px 0 12px;
}

#sccc-trends-root .kpi{
  border: 1px solid #dcdcde;
  border-radius: 8px;
  background:#fff;
  padding: 10px 12px;
  min-height: 58px;
}

#sccc-trends-root .kpi .k{
  font-size: 12px;
  color:#50575e;
  margin: 0 0 4px;
}

#sccc-trends-root .kpi .v{
  font-size: 18px;
  font-weight: 800;
  color:#111827;
  margin: 0;
}

#sccc-trends-root .sccc-trends__panels{
  display:flex;
  flex-direction:column;
  gap:12px;
}

#sccc-trends-root .panel{
  border: 1px solid #dcdcde;
  border-radius: 10px;
  background:#fff;
  padding: 12px;
}

#sccc-trends-root .panel__title{
  font-size: 13px;
  font-weight: 700;
  color:#111827;
  margin: 0 0 8px;
}

#sccc-trends-root .panel__canvas{
  position: relative;
  height: 260px;
}

#sccc-trends-root .sccc-trends__footer{
  display:flex;
  justify-content:space-between;
  align-items:center;
  gap:10px;
  margin-top: 10px;
  padding-top: 10px;
  border-top: 1px solid #e5e7eb;
}

#sccc-trends-root .note{
  font-size: 12px;
  color:#50575e;
}

#sccc-trends-root .status{
  font-size: 12px;
  color:#50575e;
}

/* Responsive */
@media (max-width: 1400px){
  #sccc-trends-root .sccc-trends__kpis{
    grid-template-columns: repeat(3, minmax(0,1fr));
  }
}
@media (max-width: 900px){
  #sccc-trends-root .sccc-trends__kpis{
    grid-template-columns: repeat(2, minmax(0,1fr));
  }
  #sccc-trends-root select{
    min-width: 160px;
  }
}
CSS;
  }

  /**
   * Chart rendering + AJAX.
   */
  private static function adminJsBoot(): string
  {
    $action = self::AJAX_ACTION;

    return <<<JS
(function(){
  var root = document.getElementById('sccc-trends-root');
  if (!root) return;

  function el(id){ return document.getElementById(id); }
  function setStatus(msg){
    var s = el('sccc-trends-status');
    if (s) s.textContent = msg;
  }

  var ajaxUrl = root.getAttribute('data-ajax');
  var nonce   = root.getAttribute('data-nonce');

  var rangeSel = el('sccc-trends-range');
  var intSel   = el('sccc-trends-interval');
  var btnUp    = el('sccc-trends-update');
  var btnReset = el('sccc-trends-reset');

  var defRange = root.getAttribute('data-default-range') || '12w';
  var defInt   = root.getAttribute('data-default-interval') || 'weekly';

  if (rangeSel) rangeSel.value = defRange;
  if (intSel)   intSel.value   = defInt;

  var charts = { a:null, s:null, e:null };

  function destroy(c){ if (c && typeof c.destroy === 'function') c.destroy(); }

  function postData(range, interval){
    var fd = new FormData();
    fd.append('action', '{$action}');
    fd.append('nonce', nonce);
    fd.append('range', range);
    fd.append('interval', interval);

    return fetch(ajaxUrl, {
      method: 'POST',
      credentials: 'same-origin',
      body: fd
    }).then(function(r){ return r.json(); });
  }

  function setKpis(k){
    if (!k) return;
    el('kpi-active').textContent = (k.active ?? '—');
    el('kpi-inactive').textContent = (k.inactive ?? '—');
    el('kpi-total').textContent = (k.total ?? '—');
    el('kpi-signups').textContent = (k.signups ?? '—');
    el('kpi-expirations').textContent = (k.expirations ?? '—');
  }

  function commonOptions(){
    return {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { labels: { color: '#111827' } },
        tooltip: { enabled: true }
      },
      scales: {
        x: {
          ticks: { color: '#50575e', maxRotation: 45, minRotation: 0 },
          grid: { color: '#e5e7eb' }
        },
        y: {
          beginAtZero: true,
          ticks: { color: '#50575e' },
          grid: { color: '#e5e7eb' }
        }
      }
    };
  }

  function render(payload){
    if (!payload) return;

    var labels = payload.labels || [];
    var active = payload.active || [];
    var inactive = payload.inactive || [];
    var signups = payload.signups || [];
    var expirations = payload.expirations || [];

    setKpis(payload.kpis || {});

    destroy(charts.a);
    charts.a = new Chart(el('chart-active-inactive'), {
      type: 'line',
      data: {
        labels: labels,
        datasets: [
          { label:'Active', data: active, tension: 0.25, pointRadius: 2, borderWidth: 2 },
          { label:'Inactive', data: inactive, tension: 0.25, pointRadius: 2, borderWidth: 2 }
        ]
      },
      options: commonOptions()
    });

    destroy(charts.s);
    charts.s = new Chart(el('chart-signups'), {
      type: 'bar',
      data: { labels: labels, datasets: [ { label:'Signups', data: signups, borderWidth: 1 } ] },
      options: commonOptions()
    });

    destroy(charts.e);
    charts.e = new Chart(el('chart-expirations'), {
      type: 'bar',
      data: { labels: labels, datasets: [ { label:'Expirations', data: expirations, borderWidth: 1 } ] },
      options: commonOptions()
    });
  }

  function load(){
    var range = rangeSel ? rangeSel.value : defRange;
    var interval = intSel ? intSel.value : defInt;

    setStatus('Loading…');

    postData(range, interval)
      .then(function(json){
        if (!json || !json.success) {
          setStatus('Failed to load data.');
          return;
        }
        render(json.data);
        setStatus('Updated.');
      })
      .catch(function(){
        setStatus('Failed to load data.');
      });
  }

  if (btnUp) btnUp.addEventListener('click', function(){ load(); });

  if (btnReset) btnReset.addEventListener('click', function(){
    if (rangeSel) rangeSel.value = defRange;
    if (intSel) intSel.value = defInt;
    load();
  });

  // Load once on widget render
  load();
})();
JS;
  }
}

MembershipTrendsReport::register();
