<?php

namespace App\Support\Admin\Dashboard\Reports;

use App\Support\Admin\UserRoles\OperationalRoles;
/**
 * File: app/Support/Admin/Dashboard/Reports/CarParkReport.php
 *
 * CarParkReport
 * ============================================================================
 * WordPress Dashboard Widget: “SCCC Car Park”
 *
 * IMPORTANT (matches your setup.php pattern)
 * - Your setup.php does: require_once <this file>
 * - Your other widgets show up because their files self-register on load.
 * - This file does the same: it self-registers at the bottom when in wp-admin.
 *
 * What it shows
 * - Top 10 vehicle makes (count + % of total vehicles)
 * - Drill-down: select a make → top models for that make
 * - Print (simple HTML + window.print)
 * - Refresh stats (rebuild cache)
 *
 * MEMBER SCOPE (YOUR RULESET)
 * - Only WP users with role: `sccc_member`
 * - AND only members considered “Active community members”:
 *   - Include: Active + Grace (0–29 days lapsed)
 *   - Exclude: Past Due (30–59), Abandoned (60+)
 *   - Exclude: Paused (manual override)
 *
 * Why we do this for Car Park
 * - Car Park is used to understand the CURRENT active fleet so you can call on
 *   members for events (“all Mustang show”, etc.).
 * - Past-due/abandoned members typically should not influence that planning.
 *
 * Data assumptions
 * - Vehicles are stored as ACF user repeater: vehicles
 *   - make_raw OR make_select OR make_other
 *   - model
 * - Fallback: vehicle_index user meta (less accurate, but better than nothing)
 *
 * Performance notes
 * - We cache stats in a transient (default 6h).
 * - Refresh button rebuilds immediately.
 */

class CarParkReport
{
    /**
     * User role that represents “members” in your system.
     * IMPORTANT: This role is assigned to *past and present* members.
     * We still apply the PMPro-based Active/Grace filter (see below).
     */
    private const MEMBER_ROLE = 'sccc_member';

    /**
     * Manual pause flag (user meta).
     * If set to 1, user is excluded from engagement/community reports.
     */
    private const META_PAUSED = 'sccc_membership_paused';

    /**
     * ACF repeater field name on the user.
     */
    private const ACF_VEHICLES_FIELD = 'vehicles';

    /**
     * Fallback meta (computed elsewhere in your stack).
     */
    private const META_VEHICLE_INDEX = 'vehicle_index';

    /**
     * Cache key + TTL.
     */
    private const CACHE_KEY = 'sccc_car_park_stats_v2';
    private const CACHE_TTL = 6 * HOUR_IN_SECONDS;

    /**
     * Admin-post actions.
     */
    private const ACTION_REFRESH = 'sccc_car_park_refresh';
    private const ACTION_PRINT   = 'sccc_car_park_print';

    /**
     * Register hooks (called automatically on file load — see bottom of file).
     */
    public static function register(): void
    {
        add_action('wp_dashboard_setup', [self::class, 'addWidget']);

        // Refresh stats (rebuild transient)
        add_action('admin_post_' . self::ACTION_REFRESH, [self::class, 'handleRefresh']);

        // Print pages (HTML + window.print)
        add_action('admin_post_' . self::ACTION_PRINT, [self::class, 'handlePrint']);
    }

    /**
     * Add the dashboard widget.
     */
    public static function addWidget(): void
    {
        if (!current_user_can(OperationalRoles::CAP_USE_DASHBOARD_WIDGETS)) {
            return;
        }

        wp_add_dashboard_widget(
            'sccc_car_park_report',
            'SCCC Car Park',
            [self::class, 'renderWidget']
        );
    }

    /**
     * Render the widget UI.
     */
    public static function renderWidget(): void
    {
        if (!current_user_can(OperationalRoles::CAP_USE_DASHBOARD_WIDGETS)) {
            echo '<p>You do not have permission to view this report.</p>';
            return;
        }

        $stats = self::getStatsCached();

        $totalVehicles = (int) ($stats['totals']['vehicles'] ?? 0);
        $totalMembersWithVehicles = (int) ($stats['totals']['members_with_vehicles'] ?? 0);

        // Total members that qualify for this report scope (Active+Grace, role=sccc_member, not paused)
        $totalEligibleMembers = (int) ($stats['totals']['members_eligible'] ?? 0);

        /**
         * Drill-down selection:
         * - Dashboard is index.php, so we can safely use query args.
         * - Value stored as our normalized key (not the pretty label).
         */
        $selectedMakeKey = isset($_GET['sccc_make']) ? self::normalizeKey((string) $_GET['sccc_make']) : '';

        // URLs (refresh + print)
        $refreshUrl = wp_nonce_url(
            admin_url('admin-post.php?action=' . self::ACTION_REFRESH),
            self::ACTION_REFRESH
        );

        $printMakesUrl = wp_nonce_url(
            admin_url('admin-post.php?action=' . self::ACTION_PRINT . '&type=makes'),
            self::ACTION_PRINT
        );

        // Build make list and top 10 display
        $makes = $stats['makes'] ?? [];
        $makesSorted = self::sortAssocDesc($makes);
        $topMakes = array_slice($makesSorted, 0, 10, true);

        // Dropdown list (all makes, sorted)
        $makeOptions = array_keys($makesSorted);

        // Models for selected make (if any)
        $modelsForSelectedMake = [];
        if ($selectedMakeKey !== '' && !empty($stats['models_by_make'][$selectedMakeKey])) {
            $modelsForSelectedMake = self::sortAssocDesc($stats['models_by_make'][$selectedMakeKey]);
        }

        $printModelsUrl = '';
        if ($selectedMakeKey !== '') {
            $printModelsUrl = wp_nonce_url(
                admin_url('admin-post.php?action=' . self::ACTION_PRINT . '&type=models&make=' . rawurlencode($selectedMakeKey)),
                self::ACTION_PRINT
            );
        }

        echo '<div class="sccc-car-park-widget">';

        // Summary (note: we show eligible member scope, not all role-holders)
        echo '<p style="margin:0 0 .75rem;">';
        echo '<strong>Total vehicles:</strong> ' . esc_html((string) $totalVehicles) . ' &nbsp;|&nbsp; ';
        echo '<strong>Members w/ vehicles:</strong> ' . esc_html((string) $totalMembersWithVehicles) . ' / ' . esc_html((string) $totalEligibleMembers);
        echo '</p>';

        echo '<p style="margin:0 0 1rem; color:#646970; font-size:12px;">';
        echo 'Scope: Active + Grace members only (excludes Paused, Past Due, Abandoned).';
        echo '</p>';

        // Action buttons
        echo '<p style="margin:.25rem 0 1rem; display:flex; gap:.5rem; flex-wrap:wrap;">';
        echo '<a class="button button-secondary" href="' . esc_url($refreshUrl) . '">Refresh stats</a>';
        echo '<a class="button button-secondary" target="_blank" rel="noopener" href="' . esc_url($printMakesUrl) . '">Print makes</a>';
        if ($printModelsUrl) {
            echo '<a class="button button-secondary" target="_blank" rel="noopener" href="' . esc_url($printModelsUrl) . '">Print models (selected make)</a>';
        }
        echo '</p>';

        // Top 10 makes
        echo '<h4 style="margin:0 0 .5rem;">Top 10 Makes</h4>';

        if ($totalVehicles <= 0 || empty($topMakes)) {
            echo '<p style="margin:0;">No vehicle data found.</p>';
        } else {
            echo '<table class="widefat striped" style="margin:0 0 1rem;">';
            echo '<thead><tr><th>Make</th><th style="width:90px;">Count</th><th style="width:90px;">%</th></tr></thead>';
            echo '<tbody>';

            foreach ($topMakes as $makeKey => $count) {
                $pct = $totalVehicles > 0 ? round(((int) $count / $totalVehicles) * 100, 1) : 0;
                $label = $stats['make_labels'][$makeKey] ?? $makeKey;

                echo '<tr>';
                echo '<td><strong>' . esc_html($label) . '</strong></td>';
                echo '<td>' . esc_html((string) (int) $count) . '</td>';
                echo '<td>' . esc_html((string) $pct) . '%</td>';
                echo '</tr>';
            }

            echo '</tbody>';
            echo '</table>';
        }

        // Drill-down
        echo '<h4 style="margin:0 0 .5rem;">Model Drill-Down</h4>';

        echo '<form method="get" action="' . esc_url(admin_url('index.php')) . '" style="display:flex; gap:.5rem; align-items:center; flex-wrap:wrap; margin:0 0 .75rem;">';

        echo '<label for="sccc_make" style="font-weight:600;">Make:</label>';
        echo '<select id="sccc_make" name="sccc_make">';
        echo '<option value="">Select a make…</option>';

        foreach ($makeOptions as $makeKey) {
            $label = $stats['make_labels'][$makeKey] ?? $makeKey;
            $selected = ($selectedMakeKey === $makeKey) ? ' selected' : '';
            echo '<option value="' . esc_attr($makeKey) . '"' . $selected . '>' . esc_html($label) . '</option>';
        }

        echo '</select>';
        echo '<button class="button button-primary" type="submit">View models</button>';

        if ($selectedMakeKey !== '') {
            echo '<a class="button" href="' . esc_url(admin_url('index.php')) . '">Clear</a>';
        }

        echo '</form>';

        if ($selectedMakeKey !== '') {
            $makeLabel = $stats['make_labels'][$selectedMakeKey] ?? $selectedMakeKey;
            $makeTotal = (int) ($makesSorted[$selectedMakeKey] ?? 0);

            echo '<p style="margin:0 0 .5rem;">';
            echo '<strong>' . esc_html($makeLabel) . '</strong> total vehicles: ' . esc_html((string) $makeTotal);
            echo '</p>';

            if (!empty($modelsForSelectedMake)) {
                // Keep the widget tidy: show top 15 models here
                $topModels = array_slice($modelsForSelectedMake, 0, 15, true);

                echo '<table class="widefat striped" style="margin:0;">';
                echo '<thead><tr><th>Model</th><th style="width:90px;">Count</th></tr></thead>';
                echo '<tbody>';

                foreach ($topModels as $modelKey => $count) {
                    $label = $stats['model_labels'][$selectedMakeKey][$modelKey] ?? $modelKey;
                    echo '<tr>';
                    echo '<td>' . esc_html($label) . '</td>';
                    echo '<td>' . esc_html((string) (int) $count) . '</td>';
                    echo '</tr>';
                }

                echo '</tbody>';
                echo '</table>';

                if (count($modelsForSelectedMake) > 15) {
                    echo '<p style="margin:.5rem 0 0; color:#666;">Showing top 15 models. Use Print to see the full list.</p>';
                }
            } else {
                echo '<p style="margin:0;">No models found for this make.</p>';
            }
        }

        echo '</div>';
    }

    /**
     * Refresh handler: rebuild cache immediately.
     */
    public static function handleRefresh(): void
    {
        if (!current_user_can(OperationalRoles::CAP_USE_DASHBOARD_WIDGETS)) {
            wp_die('Sorry, you are not allowed to do that.');
        }

        check_admin_referer(self::ACTION_REFRESH);

        $stats = self::buildStats();
        set_transient(self::CACHE_KEY, $stats, self::CACHE_TTL);

        wp_safe_redirect(admin_url('index.php'));
        exit;
    }

    /**
     * Print handler: minimal HTML + window.print().
     *
     * Supported:
     * - type=makes
     * - type=models&make=<makeKey>
     */
    public static function handlePrint(): void
    {
        if (!current_user_can(OperationalRoles::CAP_USE_DASHBOARD_WIDGETS)) {
            wp_die('Sorry, you are not allowed to access this page.');
        }

        check_admin_referer(self::ACTION_PRINT);

        $type = isset($_GET['type']) ? sanitize_text_field((string) $_GET['type']) : 'makes';
        $make = isset($_GET['make']) ? sanitize_text_field((string) $_GET['make']) : '';

        $stats = self::getStatsCached();

        $title = 'SCCC Car Park';
        $rows = [];

        if ($type === 'models' && $make !== '') {
            $makeKey = self::normalizeKey($make);
            $makeLabel = $stats['make_labels'][$makeKey] ?? $makeKey;

            $title = 'SCCC Car Park — Models for ' . $makeLabel;

            $models = $stats['models_by_make'][$makeKey] ?? [];
            $models = self::sortAssocDesc($models);

            foreach ($models as $modelKey => $count) {
                $modelLabel = $stats['model_labels'][$makeKey][$modelKey] ?? $modelKey;
                $rows[] = [$modelLabel, (int) $count];
            }
        } else {
            $title = 'SCCC Car Park — Makes';

            $makes = $stats['makes'] ?? [];
            $makes = self::sortAssocDesc($makes);

            foreach ($makes as $makeKey => $count) {
                $makeLabel = $stats['make_labels'][$makeKey] ?? $makeKey;
                $rows[] = [$makeLabel, (int) $count];
            }
        }

        $generatedBy = wp_get_current_user();
        $generatedName = $generatedBy && !empty($generatedBy->display_name) ? $generatedBy->display_name : 'Unknown';

        header('Content-Type: text/html; charset=utf-8');

        echo '<!doctype html><html><head>';
        echo '<meta charset="utf-8" />';
        echo '<meta name="viewport" content="width=device-width, initial-scale=1" />';
        echo '<title>' . esc_html($title) . '</title>';

        echo '<style>
          body{ font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif; margin: 24px; color:#111; }
          h1{ font-size: 18px; margin: 0 0 6px; }
          .meta{ font-size: 12px; color:#444; margin: 0 0 16px; }
          table{ width: 100%; border-collapse: collapse; }
          th, td{ border:1px solid #ccc; padding:8px; font-size: 12px; text-align:left; }
          th{ background:#f3f3f3; }
          .count{ width: 100px; text-align:right; }
          @media print { body{ margin: 10mm; } }
        </style>';

        echo '</head><body>';

        echo '<h1>' . esc_html($title) . '</h1>';
        echo '<p class="meta">';
        echo 'Generated by: ' . esc_html($generatedName) . ' &nbsp;|&nbsp; ';
        echo 'Generated on: ' . esc_html(wp_date('M j, Y g:i A')) . '<br>';
        echo 'Scope: Active + Grace members only (excludes Paused, Past Due, Abandoned).';
        echo '</p>';

        echo '<table><thead><tr>';
        echo '<th>' . esc_html(($type === 'models') ? 'Model' : 'Make') . '</th>';
        echo '<th class="count">Count</th>';
        echo '</tr></thead><tbody>';

        if (empty($rows)) {
            echo '<tr><td colspan="2">No data found.</td></tr>';
        } else {
            foreach ($rows as $r) {
                echo '<tr><td>' . esc_html((string) $r[0]) . '</td><td class="count">' . esc_html((string) (int) $r[1]) . '</td></tr>';
            }
        }

        echo '</tbody></table>';

        echo '<script>
          window.addEventListener("load", function() {
            window.print();
          });
        </script>';

        echo '</body></html>';
        exit;
    }

    /**
     * Cached stats getter.
     */
    private static function getStatsCached(): array
    {
        $cached = get_transient(self::CACHE_KEY);

        if (is_array($cached) && !empty($cached['totals'])) {
            return $cached;
        }

        $stats = self::buildStats();
        set_transient(self::CACHE_KEY, $stats, self::CACHE_TTL);

        return $stats;
    }

    /**
     * Build stats by scanning member users and their vehicles.
     *
     * IMPORTANT:
     * - We start from role=sccc_member (past+present)
     * - Then we apply “Active + Grace only” membership filter via PMPro
     */
    private static function buildStats(): array
    {
        $stats = [
            'totals' => [
                'members_role_total'     => 0, // all users with sccc_member role
                'members_eligible'       => 0, // active+grace, not paused
                'members_with_vehicles'  => 0, // eligible members who have vehicles
                'vehicles'               => 0, // total vehicle rows counted
            ],
            'makes' => [],                 // [makeKey => count]
            'models_by_make' => [],        // [makeKey => [modelKey => count]]
            'make_labels' => [],           // [makeKey => original label]
            'model_labels' => [],          // [makeKey => [modelKey => original label]]
        ];

        $users = get_users([
            'role'   => self::MEMBER_ROLE,
            'fields' => ['ID'],
            'number' => 0,
        ]);

        $stats['totals']['members_role_total'] = is_array($users) ? count($users) : 0;

        if (empty($users)) {
            return $stats;
        }

        foreach ($users as $u) {
            $userId = (int) ($u->ID ?? 0);
            if ($userId <= 0) {
                continue;
            }

            // Eligibility gate: Active + Grace only (and not paused)
            if (!self::isActiveOrGraceMember($userId)) {
                continue;
            }

            $stats['totals']['members_eligible']++;

            $vehicles = self::getUserVehicles($userId);

            if (empty($vehicles)) {
                continue;
            }

            $stats['totals']['members_with_vehicles']++;

            foreach ($vehicles as $v) {
                $makeLabel  = self::extractMakeLabel($v);
                $modelLabel = self::extractModelLabel($v);

                if ($makeLabel === '') {
                    continue;
                }

                $makeKey  = self::normalizeKey($makeLabel);
                $modelKey = self::normalizeKey($modelLabel);

                $stats['totals']['vehicles']++;

                // Makes
                if (!isset($stats['makes'][$makeKey])) {
                    $stats['makes'][$makeKey] = 0;
                    $stats['make_labels'][$makeKey] = $makeLabel;
                }
                $stats['makes'][$makeKey]++;

                // Models per make
                if (!isset($stats['models_by_make'][$makeKey])) {
                    $stats['models_by_make'][$makeKey] = [];
                    $stats['model_labels'][$makeKey] = [];
                }

                if ($modelKey !== '') {
                    if (!isset($stats['models_by_make'][$makeKey][$modelKey])) {
                        $stats['models_by_make'][$makeKey][$modelKey] = 0;
                        $stats['model_labels'][$makeKey][$modelKey] = $modelLabel;
                    }
                    $stats['models_by_make'][$makeKey][$modelKey]++;
                }
            }
        }

        return $stats;
    }

    /**
     * Eligibility check:
     * - Exclude Paused
     * - Include Active (PMPro active + enddate not passed)
     * - Include Grace (ended within last 0–29 days)
     * - Exclude everything else (past due / abandoned / no PMPro row)
     */
    private static function isActiveOrGraceMember(int $userId): bool
    {
        // Manual pause wins.
        if ((int) get_user_meta($userId, self::META_PAUSED, true) === 1) {
            return false;
        }

        // If PMPro table is missing, we can’t reliably decide. Safer to exclude.
        if (!self::pmproMembershipsUsersTableExists()) {
            return false;
        }

        $row = self::getLatestPmproMembershipRow($userId);
        if (!$row) {
            return false;
        }

        $now = current_time('timestamp');

        $status = isset($row['status']) ? (string) $row['status'] : '';
        $end_ts = self::parseEnddateToTs($row['enddate'] ?? null);

        $has_end = ($end_ts !== null);
        $active_by_enddate = (!$has_end) || ($end_ts > $now);

        // Active: PMPro says active and enddate still valid (or no enddate).
        if ($status === 'active' && $active_by_enddate) {
            return true;
        }

        // Grace: membership ended within the last 0–29 days.
        if ($has_end && $end_ts <= $now) {
            $days = (int) floor(max(0, $now - $end_ts) / DAY_IN_SECONDS);
            return ($days >= 0 && $days < 30);
        }

        return false;
    }

    /**
     * PMPro memberships_users table name.
     * PMPro often sets $wpdb->pmpro_memberships_users; fallback to prefix table.
     */
    private static function pmproMembershipsUsersTable(): string
    {
        global $wpdb;

        if (isset($wpdb->pmpro_memberships_users) && !empty($wpdb->pmpro_memberships_users)) {
            return (string) $wpdb->pmpro_memberships_users;
        }

        return (string) ($wpdb->prefix . 'pmpro_memberships_users');
    }

    /**
     * Verify PMPro memberships_users table exists (avoids fatal SQL errors).
     */
    private static function pmproMembershipsUsersTableExists(): bool
    {
        global $wpdb;

        $table = self::pmproMembershipsUsersTable();
        if (!$table) {
            return false;
        }

        $like = $wpdb->esc_like($table);
        $found = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $like)); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

        return !empty($found);
    }

    /**
     * Get the latest PMPro memberships_users row for a user.
     * We sort by modified desc, then id desc.
     */
    private static function getLatestPmproMembershipRow(int $userId): ?array
    {
        global $wpdb;

        $table = self::pmproMembershipsUsersTable();
        if (!$table) {
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

    /**
     * Parse PMPro enddate into a unix timestamp.
     * Returns null when:
     * - enddate is null/empty
     * - enddate is the PMPro “no expiration” value
     */
    private static function parseEnddateToTs($enddate): ?int
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

    /**
     * Read vehicles for a user.
     *
     * Primary: ACF repeater via get_field('vehicles', 'user_{id}')
     * Fallback: vehicle_index meta (less precise).
     */
    private static function getUserVehicles(int $userId): array
    {
        if (function_exists('get_field')) {
            $rows = get_field(self::ACF_VEHICLES_FIELD, 'user_' . $userId);
            if (is_array($rows) && !empty($rows)) {
                return $rows;
            }
        }

        $idx = (string) get_user_meta($userId, self::META_VEHICLE_INDEX, true);
        $idx = trim($idx);

        if ($idx === '') {
            return [];
        }

        $parts = array_filter(array_map('trim', explode('|', $idx)));

        $vehicles = [];
        foreach ($parts as $p) {
            $tokens = preg_split('/\s+/', $p);
            if (!$tokens || count($tokens) < 1) {
                continue;
            }

            $make = (string) array_shift($tokens);
            $model = trim(implode(' ', $tokens));

            $vehicles[] = [
                'make_raw' => $make,
                'model' => $model,
            ];
        }

        return $vehicles;
    }

    /**
     * Extract make label from a vehicle row.
     */
    private static function extractMakeLabel(array $vehicle): string
    {
        $makeRaw = isset($vehicle['make_raw']) ? trim((string) $vehicle['make_raw']) : '';
        if ($makeRaw !== '') return $makeRaw;

        $makeSelect = isset($vehicle['make_select']) ? trim((string) $vehicle['make_select']) : '';
        if ($makeSelect !== '') return $makeSelect;

        $makeOther = isset($vehicle['make_other']) ? trim((string) $vehicle['make_other']) : '';
        return $makeOther;
    }

    /**
     * Extract model label from a vehicle row.
     */
    private static function extractModelLabel(array $vehicle): string
    {
        return isset($vehicle['model']) ? trim((string) $vehicle['model']) : '';
    }

    /**
     * Normalize for grouping (keys).
     */
    private static function normalizeKey(string $value): string
    {
        $v = strtolower(trim($value));
        $v = preg_replace('/\s+/', ' ', $v);
        $v = preg_replace('/[^a-z0-9 \-]/', '', $v);
        return trim($v);
    }

    /**
     * Sort associative array by value desc.
     */
    private static function sortAssocDesc(array $arr): array
    {
        arsort($arr, SORT_NUMERIC);
        return $arr;
    }
}

/**
 * SELF-REGISTRATION (matches your existing widget files)
 * ============================================================================
 * Because you include this file via setup.php (require_once),
 * we register immediately in wp-admin.
 */
if (is_admin()) {
    CarParkReport::register();
}
