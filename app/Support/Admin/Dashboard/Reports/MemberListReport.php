<?php

/**
 * File: app/Support/Admin/Dashboard/Reports/MemberListReport.php
 *
 * SCCC Admin Reports (HTML Print-to-PDF) — Member List
 * ============================================================================
 * PURPOSE
 * - Dashboard widget with the full member list in a scrolling table:
 *   Name (First Last), Membership level, Member since.
 * - "Print list" opens a clean HTML sheet for Print / Save as PDF, like the
 *   other SCCC report widgets.
 *
 * MEMBER SCOPE (same rule as the other community reports)
 * - WP role `sccc_member`
 * - Include: Active + Grace (0–29 days lapsed)
 * - Exclude: Past Due (30–59), Abandoned (60+), Paused
 *
 * DATA SOURCES
 * - Name: first_name + last_name user meta, falling back to display_name
 * - Level: membership level of the member's latest PMPro membership row
 * - Member since: `membership_issued_at` user meta (the date the membership
 *   number was issued; also drives the Anniversaries report), falling back to
 *   the earliest PMPro start date, then the account registration date
 *
 * FILTERS
 * - Level: all levels or one level
 * - Sort: last name (default), member since (oldest first), level
 *
 * Loaded from app/setup.php next to the other report widgets.
 */

namespace App\Support\Admin\Dashboard\Reports;

use App\Support\Admin\UserRoles\OperationalRoles;

defined('ABSPATH') || exit;

final class MemberListReport
{
    private const WIDGET_ID    = 'sccc_member_list_report';
    private const PRINT_ACTION = 'sccc_print_member_list';
    private const MEMBER_ROLE  = 'sccc_member';
    private const SORTS        = ['name', 'since', 'level'];

    public static function register(): void
    {
        add_action('wp_dashboard_setup', [self::class, 'addWidget']);
        add_action('admin_post_' . self::PRINT_ACTION, [self::class, 'renderPrint']);
    }

    private static function canView(): bool
    {
        return current_user_can(OperationalRoles::CAP_USE_DASHBOARD_WIDGETS);
    }

    public static function addWidget(): void
    {
        if (! self::canView()) {
            return;
        }

        wp_add_dashboard_widget(self::WIDGET_ID, 'SCCC Member List', [self::class, 'renderWidget']);
    }

    /* ---------------------------------------------------------------------
     * Request filters
     * ------------------------------------------------------------------ */

    /** @return array{level:int, sort:string} */
    private static function filtersFromRequest(): array
    {
        $level = isset($_GET['sccc_ml_level']) ? absint(wp_unslash($_GET['sccc_ml_level'])) : 0;
        $sort  = isset($_GET['sccc_ml_sort']) ? sanitize_key(wp_unslash($_GET['sccc_ml_sort'])) : 'name';

        if (! in_array($sort, self::SORTS, true)) {
            $sort = 'name';
        }

        if ($level > 0 && ! array_key_exists($level, self::levelNames())) {
            $level = 0;
        }

        return ['level' => $level, 'sort' => $sort];
    }

    private static function sortLabel(string $sort): string
    {
        return match ($sort) {
            'since' => 'Member since (oldest first)',
            'level' => 'Membership level',
            default => 'Last name',
        };
    }

    /* ---------------------------------------------------------------------
     * Widget
     * ------------------------------------------------------------------ */

    public static function renderWidget(): void
    {
        if (! self::canView()) {
            echo '<p>You do not have permission to view this report.</p>';
            return;
        }

        $filters = self::filtersFromRequest();
        $levels  = self::levelNames();
        $rows    = self::rows($filters['level'], $filters['sort']);

        echo '<form method="get" action="' . esc_url(admin_url('index.php')) . '" style="margin:0 0 12px;">';
        echo '<p style="margin:0 0 10px; display:flex; gap:10px; flex-wrap:wrap; align-items:flex-end;">';

        echo '<label style="display:flex; flex-direction:column; gap:4px;">';
        echo '<span style="font-size:12px; color:#646970;">Level</span>';
        echo '<select name="sccc_ml_level" style="min-width:180px;">';
        echo '<option value="0"' . selected($filters['level'], 0, false) . '>All levels</option>';
        foreach ($levels as $id => $name) {
            echo '<option value="' . esc_attr((string) $id) . '"' . selected($filters['level'], $id, false) . '>' . esc_html($name) . '</option>';
        }
        echo '</select>';
        echo '</label>';

        echo '<label style="display:flex; flex-direction:column; gap:4px;">';
        echo '<span style="font-size:12px; color:#646970;">Sort by</span>';
        echo '<select name="sccc_ml_sort" style="min-width:180px;">';
        foreach (self::SORTS as $sort) {
            echo '<option value="' . esc_attr($sort) . '"' . selected($filters['sort'], $sort, false) . '>' . esc_html(self::sortLabel($sort)) . '</option>';
        }
        echo '</select>';
        echo '</label>';

        echo '<button type="submit" class="button button-primary">Load list</button>';
        echo '<a class="button" href="' . esc_url(admin_url('index.php')) . '">Reset</a>';
        echo '</p>';

        echo '<p style="margin:0; color:#646970; font-size:12px;">';
        echo 'Current members only (Active and Grace). Past Due, Abandoned and Paused members are left out.';
        echo '</p>';
        echo '</form>';

        $printUrl = wp_nonce_url(
            add_query_arg(
                [
                    'action'        => self::PRINT_ACTION,
                    'sccc_ml_level' => $filters['level'],
                    'sccc_ml_sort'  => $filters['sort'],
                    'autoprint'     => 1,
                ],
                admin_url('admin-post.php')
            ),
            self::PRINT_ACTION
        );

        echo '<p style="margin:0 0 12px; display:flex; gap:12px; align-items:center; flex-wrap:wrap;">';
        echo '<a class="button button-primary" href="' . esc_url($printUrl) . '">Print list</a>';
        echo '<span style="color:#646970;">' . esc_html(self::summary($rows)) . '</span>';
        echo '</p>';

        if (empty($rows)) {
            echo '<p style="margin:0;">No members found for this report.</p>';
            return;
        }

        echo '<div style="max-height:360px; overflow:auto; border:1px solid #dcdcde; border-radius:6px;">';
        echo '<table class="widefat striped" style="margin:0;">';
        echo '<thead><tr>';
        echo '<th style="position:sticky; top:0; background:#fff; z-index:1;">Name</th>';
        echo '<th style="position:sticky; top:0; background:#fff; z-index:1; width:34%;">Level</th>';
        echo '<th style="position:sticky; top:0; background:#fff; z-index:1; width:26%;">Member since</th>';
        echo '</tr></thead><tbody>';

        foreach ($rows as $r) {
            echo '<tr>';
            echo '<td>' . esc_html($r['name']) . '</td>';
            echo '<td>' . esc_html($r['level']) . '</td>';
            echo '<td style="white-space:nowrap;">' . esc_html($r['since_label']) . '</td>';
            echo '</tr>';
        }

        echo '</tbody></table></div>';
    }

    /* ---------------------------------------------------------------------
     * Print sheet
     * ------------------------------------------------------------------ */

    public static function renderPrint(): void
    {
        if (! self::canView()) {
            wp_die('You do not have permission to print this report.');
        }

        check_admin_referer(self::PRINT_ACTION);

        $filters    = self::filtersFromRequest();
        $rows       = self::rows($filters['level'], $filters['sort']);
        $levels     = self::levelNames();
        $autoPrint  = ! empty($_GET['autoprint']);
        $levelLabel = $filters['level'] > 0 ? ($levels[$filters['level']] ?? 'All levels') : 'All levels';
        $generated  = wp_date('F j, Y g:i A');
        $user       = wp_get_current_user();
        $by         = ($user && $user->exists()) ? $user->display_name : 'Unknown';

        header('Content-Type: text/html; charset=' . get_option('blog_charset'));
        ?>
        <!doctype html>
        <html lang="en">
          <head>
            <meta charset="<?php echo esc_attr(get_option('blog_charset')); ?>">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title><?php echo esc_html('SCCC Member List — ' . $levelLabel); ?></title>
            <style>
              :root { --text:#111827; --muted:#6b7280; --border:#e5e7eb; }
              html, body { margin:0; padding:0; }
              body {
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
                color: var(--text);
                background: #fff;
                padding: 28px;
              }
              .topbar {
                display:flex; align-items:flex-start; justify-content:space-between; gap:16px;
                border-bottom: 2px solid var(--border);
                padding-bottom: 14px;
                margin-bottom: 16px;
              }
              h1 { font-size: 20px; line-height: 1.25; margin: 0 0 4px; }
              .meta { margin:0; color: var(--muted); font-size:12px; line-height:1.4; }
              .actions { display:flex; gap:10px; }
              .btn {
                appearance:none; border:1px solid #111827; background:#111827; color:#fff;
                padding:8px 12px; border-radius:6px; font-size:13px; cursor:pointer; text-decoration:none;
                display:inline-flex; align-items:center; justify-content:center;
              }
              .btn.secondary { background:#fff; color:#111827; }
              table { width:100%; border-collapse: collapse; font-size: 13px; }
              thead th { text-align:left; border-bottom:2px solid var(--border); padding:10px 8px; font-weight:700; }
              tbody td { border-bottom:1px solid var(--border); padding:9px 8px; vertical-align:top; }
              tbody tr { break-inside: avoid; }
              .col-num { width: 44px; color: var(--muted); font-variant-numeric: tabular-nums; }
              .col-level { width: 220px; }
              .col-since { width: 150px; font-variant-numeric: tabular-nums; }
              .empty { padding: 12px 0; color: var(--muted); }
              @media print {
                body { padding: 0; }
                .actions { display:none !important; }
                .topbar { border-bottom: 1px solid #000; }
                thead { display: table-header-group; }
                a[href]::after { content: ""; }
              }
            </style>
          </head>
          <body>
            <div class="topbar">
              <div>
                <h1>SCCC Member List</h1>
                <p class="meta">
                  Level: <?php echo esc_html($levelLabel); ?> · Sorted by: <?php echo esc_html(self::sortLabel($filters['sort'])); ?><br>
                  <?php echo esc_html(self::summary($rows)); ?> (Active and Grace)<br>
                  Generated: <?php echo esc_html($generated); ?><br>
                  Generated by: <?php echo esc_html($by); ?>
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
                    <th class="col-num">#</th>
                    <th>Name</th>
                    <th class="col-level">Level</th>
                    <th class="col-since">Member since</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($rows as $i => $r) : ?>
                    <tr>
                      <td class="col-num"><?php echo esc_html((string) ($i + 1)); ?></td>
                      <td><?php echo esc_html($r['name']); ?></td>
                      <td class="col-level"><?php echo esc_html($r['level']); ?></td>
                      <td class="col-since"><?php echo esc_html($r['since_label']); ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            <?php endif; ?>

            <?php if ($autoPrint) : ?>
              <script>window.addEventListener('load', () => setTimeout(() => window.print(), 250));</script>
            <?php endif; ?>
          </body>
        </html>
        <?php
        exit;
    }

    /* ---------------------------------------------------------------------
     * Data
     * ------------------------------------------------------------------ */

    /** @return array<int,string> PMPro level id => name */
    private static function levelNames(): array
    {
        static $names = null;

        if ($names !== null) {
            return $names;
        }

        $names = [];

        if (function_exists('pmpro_getAllLevels')) {
            foreach ((array) pmpro_getAllLevels(true, true) as $level) {
                if (is_object($level) && isset($level->id)) {
                    $names[(int) $level->id] = (string) $level->name;
                }
            }
        }

        return $names;
    }

    /**
     * Current members (Active + Grace, not paused), already filtered and sorted.
     *
     * @return list<array{id:int, name:string, last:string, first:string, level:string, level_id:int, since_ts:int, since_label:string}>
     */
    private static function rows(int $levelFilter, string $sort): array
    {
        $users = get_users([
            'role'    => self::MEMBER_ROLE,
            'number'  => -1,
            'fields'  => ['ID', 'display_name', 'user_registered'],
        ]);

        if (empty($users)) {
            return [];
        }

        $ids        = array_map(static fn ($u) => (int) $u->ID, $users);
        $membership = self::membershipRows($ids);
        $levelNames = self::levelNames();
        $now        = (int) current_time('timestamp');
        $rows       = [];

        foreach ($users as $u) {
            $id = (int) $u->ID;

            if ((int) get_user_meta($id, 'sccc_membership_paused', true) === 1) {
                continue;
            }

            $m = $membership[$id] ?? null;
            if (! $m || ! self::isActiveOrGrace($m['latest'], $now)) {
                continue;
            }

            $levelId = (int) $m['latest']['membership_id'];
            if ($levelFilter > 0 && $levelId !== $levelFilter) {
                continue;
            }

            $first = trim((string) get_user_meta($id, 'first_name', true));
            $last  = trim((string) get_user_meta($id, 'last_name', true));
            $name  = trim($first . ' ' . $last);
            if ($name === '') {
                $name = (string) $u->display_name;
            }

            $sinceTs = self::parseDate((string) get_user_meta($id, 'membership_issued_at', true))
                ?? $m['first_start']
                ?? self::parseDate((string) $u->user_registered);

            $rows[] = [
                'id'          => $id,
                'name'        => $name,
                'last'        => $last !== '' ? $last : $name,
                'first'       => $first,
                'level'       => $levelNames[$levelId] ?? '—',
                'level_id'    => $levelId,
                'since_ts'    => (int) ($sinceTs ?? 0),
                'since_label' => $sinceTs ? wp_date('M j, Y', $sinceTs) : '—',
            ];
        }

        usort($rows, static function (array $a, array $b) use ($sort): int {
            $byName = strcasecmp($a['last'], $b['last']) ?: strcasecmp($a['first'], $b['first']);

            return match ($sort) {
                'since' => (($a['since_ts'] ?: PHP_INT_MAX) <=> ($b['since_ts'] ?: PHP_INT_MAX)) ?: $byName,
                'level' => strcasecmp($a['level'], $b['level']) ?: $byName,
                default => $byName,
            };
        });

        return $rows;
    }

    /**
     * Latest PMPro membership row and earliest start date per user, in one query.
     *
     * @param  list<int> $ids
     * @return array<int, array{latest:array<string,mixed>, first_start:?int}>
     */
    private static function membershipRows(array $ids): array
    {
        global $wpdb;

        $table = ! empty($wpdb->pmpro_memberships_users)
            ? (string) $wpdb->pmpro_memberships_users
            : $wpdb->prefix . 'pmpro_memberships_users';

        if ($ids === [] || ! $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table)))) {
            return [];
        }

        $out = [];

        foreach (array_chunk($ids, 500) as $chunk) {
            $placeholders = implode(',', array_fill(0, count($chunk), '%d'));

            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name and placeholders only
            $sql = "SELECT user_id, membership_id, status, startdate, enddate, modified, id
                    FROM {$table}
                    WHERE user_id IN ({$placeholders})
                    ORDER BY user_id ASC, modified DESC, id DESC";

            $results = $wpdb->get_results($wpdb->prepare($sql, $chunk), ARRAY_A); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

            foreach ((array) $results as $row) {
                $uid   = (int) $row['user_id'];
                $start = self::parseDate((string) ($row['startdate'] ?? ''));

                if (! isset($out[$uid])) {
                    $out[$uid] = ['latest' => $row, 'first_start' => $start];
                    continue;
                }

                if ($start !== null && ($out[$uid]['first_start'] === null || $start < $out[$uid]['first_start'])) {
                    $out[$uid]['first_start'] = $start;
                }
            }
        }

        return $out;
    }

    /** Same rule as the other community reports: active, or ended 0–29 days ago. */
    private static function isActiveOrGrace(array $row, int $now): bool
    {
        $end = self::parseDate((string) ($row['enddate'] ?? ''));

        if (($row['status'] ?? '') === 'active' && ($end === null || $end > $now)) {
            return true;
        }

        if ($end !== null && $end <= $now) {
            return (int) floor(($now - $end) / DAY_IN_SECONDS) < 30;
        }

        return false;
    }

    private static function parseDate(string $raw): ?int
    {
        $raw = trim($raw);

        if ($raw === '' || str_starts_with($raw, '0000-00-00')) {
            return null;
        }

        if (preg_match('/^\d{8}$/', $raw)) {
            $d = \DateTime::createFromFormat('Ymd', $raw, wp_timezone());

            return $d ? $d->setTime(12, 0)->getTimestamp() : null;
        }

        $ts = strtotime($raw);

        return $ts ? (int) $ts : null;
    }

    /** e.g. "42 members: 20 Member, 12 Founding Member, 10 Veteran / First Responder" */
    private static function summary(array $rows): string
    {
        $total = count($rows);

        if ($total === 0) {
            return '0 members';
        }

        $counts = [];
        foreach ($rows as $r) {
            $counts[$r['level']] = ($counts[$r['level']] ?? 0) + 1;
        }
        arsort($counts);

        $parts = [];
        foreach ($counts as $level => $n) {
            $parts[] = $n . ' ' . $level;
        }

        return sprintf(_n('%d member', '%d members', $total, 'sage'), $total) . ': ' . implode(', ', $parts);
    }
}

MemberListReport::register();
