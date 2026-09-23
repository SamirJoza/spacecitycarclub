<?php
/**
 * app/Support/Integrations/ScccMemberLifecycleAdmin.php
 * File: app/Support/Integrations/ScccMemberLifecycleAdmin.php
 *
 * What this file does
 * -----------------------------------------------------------------------------
 * Adds a "Membership Status" column to WP Admin → Users with pill badges
 * for SCCC members ONLY.
 *
 * Membership visibility gate (your request)
 * -----------------------------------------------------------------------------
 * We ONLY show a badge if the user has the WP role: sccc_member
 * - Members (past/present): have sccc_member => badge shows
 * - Shoppers / non-members: do NOT have sccc_member => badge is blank (no badge)
 *
 * Status logic (expiration-date correct)
 * -----------------------------------------------------------------------------
 * For sccc_member users:
 * - Active    = PMPro status=active AND (enddate empty OR enddate > now)
 * - Grace     = enddate is 0–29 days in the past
 * - Past Due  = enddate is 30–59 days in the past
 * - Abandoned = enddate is 60+ days in the past
 * - Paused    = manual override via user meta (admin controlled)
 * - Unknown   = PMPro not available or cannot determine enddate
 *
 * Why enddate is the authority
 * -----------------------------------------------------------------------------
 * PMPro stores expiration in pmpro_memberships_users.enddate. If you manually set
 * the expiration to last October, enddate will be in the past and we will show
 * the user as lapsed (Grace/Past Due/Abandoned) accordingly.
 *
 * Loading (theme-contained)
 * -----------------------------------------------------------------------------
 * require_once get_theme_file_path('app/Support/Integrations/ScccMemberLifecycleAdmin.php');
 */

namespace App\Support\Integrations;

class ScccMemberLifecycleAdmin
{
    // Thresholds in days (aligns with your policy)
    protected int $pastDueStart = 30;
    protected int $abandonedStart = 60;

    // Gate role: only users with this role get a badge
    protected string $memberRole = 'sccc_member';

    // Meta keys (user meta)
    protected string $metaPaused = 'sccc_membership_paused';
    protected string $metaStatus = 'sccc_membership_status';
    protected string $metaDays   = 'sccc_membership_lapsed_days';
    protected string $metaSort   = 'sccc_membership_status_sort';

    // Reset flag (consumed by MemberProfile when a membership is re-assigned)
    protected string $metaResetRequired = 'sccc_reset_identity_required';

    // Users table column key
    protected string $colKey = 'sccc_membership_status_col';

    public function register(): void
    {
        // Admin Users table: column + sortable + CSS
        add_filter('manage_users_columns', [$this, 'addUsersColumn']);
        add_filter('manage_users_custom_column', [$this, 'renderUsersColumn'], 10, 3);
        add_filter('manage_users_sortable_columns', [$this, 'sortableUsersColumn']);
        add_action('pre_get_users', [$this, 'usersColumnSortQuery']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminCss']);

        // Bulk actions (Users screen) — should apply to members only, but safe either way
        add_filter('bulk_actions-users', [$this, 'registerBulkActions']);
        add_filter('handle_bulk_actions-users', [$this, 'handleBulkActions'], 10, 3);
        add_action('admin_notices', [$this, 'bulkActionNotices']);

        // PMPro: hide “Abandoned” from the PMPro Members List / CSV export
        add_filter('pmpro_members_list_sql', [$this, 'excludeAbandonedFromPmproMembersListSql']);
    }

    /* =======================================================================
     * USERS TABLE COLUMN
     * ======================================================================= */

    public function addUsersColumn(array $columns): array
    {
        $columns[$this->colKey] = 'Membership Status';
        return $columns;
    }

    public function renderUsersColumn(string $output, string $column_name, int $user_id): string
    {
        if ($column_name !== $this->colKey) {
            return $output;
        }

        /**
         * HARD GATE (your request)
         * ------------------------------------------------------------------
         * Only show a badge if the user has the sccc_member role.
         * Everyone else => blank column (no badge).
         */
        if (!$this->userHasRole($user_id, $this->memberRole)) {
            // Keep meta clean-ish: don’t stamp statuses for non-members.
            // (Optional) You can delete status meta here, but we’ll avoid side-effects.
            return '';
        }

        /**
         * Compute fresh every render
         * ------------------------------------------------------------------
         * You’re changing expiration dates manually during development.
         * Computing on the fly ensures the column reflects reality immediately.
         */
        $computed = $this->computeStatusForMemberUser($user_id);

        // Persist meta for sorting and for PMPro member list exclusions, etc.
        $this->persistStatusMeta($user_id, $computed['status'], $computed['days']);

        return $this->renderPill($computed['status'], $computed['days']);
    }

    public function sortableUsersColumn(array $columns): array
    {
        $columns[$this->colKey] = $this->colKey;
        return $columns;
    }

    public function usersColumnSortQuery(\WP_User_Query $query): void
    {
        if (!is_admin() || !$query->is_main_query()) {
            return;
        }

        if ($query->get('orderby') !== $this->colKey) {
            return;
        }

        /**
         * Note on sorting when blanks exist:
         * ------------------------------------------------------------------
         * Users without sccc_member role will not have our meta_sort value,
         * so WP will push them to the bottom/top depending on DB behavior.
         * This is fine; if you want “members first always”, we can add an
         * additional orderby clause, but keeping this minimal for now.
         */
        $query->set('meta_key', $this->metaSort);
        $query->set('orderby', 'meta_value_num');
    }

    public function enqueueAdminCss(string $hook): void
    {
        if ($hook !== 'users.php') {
            return;
        }

        $css = '
        .sccc-pill{display:inline-flex;align-items:center;gap:.35rem;padding:.18rem .55rem;border-radius:999px;
          font-size:12px;font-weight:700;line-height:1.6;border:1px solid transparent;white-space:nowrap;}
        .sccc-dot{width:.45rem;height:.45rem;border-radius:999px;display:inline-block;}
        .sccc-active{background:#e8f7ee;border-color:#2fb565;color:#155b2d;}
        .sccc-active .sccc-dot{background:#2fb565;}
        .sccc-grace{background:#fff8e6;border-color:#f1c40f;color:#5a4500;}
        .sccc-grace .sccc-dot{background:#f1c40f;}
        .sccc-pastdue{background:#fff3e6;border-color:#f39c12;color:#6a3b00;}
        .sccc-pastdue .sccc-dot{background:#f39c12;}
        .sccc-abandoned{background:#ffe8e8;border-color:#e74c3c;color:#6b0f0a;}
        .sccc-abandoned .sccc-dot{background:#e74c3c;}
        .sccc-paused{background:#eef2ff;border-color:#6366f1;color:#1e1b4b;}
        .sccc-paused .sccc-dot{background:#6366f1;}
        .sccc-unknown{background:#f8fafc;border-color:#cbd5e1;color:#475569;}
        .sccc-unknown .sccc-dot{background:#cbd5e1;}
        ';

        wp_register_style('sccc-member-lifecycle-admin', false);
        wp_enqueue_style('sccc-member-lifecycle-admin');
        wp_add_inline_style('sccc-member-lifecycle-admin', $css);
    }

    protected function renderPill(string $status, int $days): string
    {
        $map = [
            'active'    => ['Active', 'sccc-active'],
            'grace'     => ['Grace', 'sccc-grace'],
            'past_due'  => ['Past Due', 'sccc-pastdue'],
            'abandoned' => ['Abandoned', 'sccc-abandoned'],
            'paused'    => ['Paused', 'sccc-paused'],
            'unknown'   => ['Unknown', 'sccc-unknown'],
        ];

        $label = $map[$status][0] ?? 'Unknown';
        $class = $map[$status][1] ?? 'sccc-unknown';

        $suffix = '';
        if (in_array($status, ['grace', 'past_due', 'abandoned'], true) && $days > 0) {
            $suffix = " ({$days}d)";
        }

        return sprintf(
            '<span class="sccc-pill %s"><span class="sccc-dot" aria-hidden="true"></span>%s%s</span>',
            esc_attr($class),
            esc_html($label),
            esc_html($suffix)
        );
    }

    /* =======================================================================
     * STATUS COMPUTATION FOR MEMBERS (sccc_member role only)
     * ======================================================================= */

    protected function computeStatusForMemberUser(int $user_id): array
    {
        // Manual pause always wins
        if ((int) get_user_meta($user_id, $this->metaPaused, true) === 1) {
            return ['status' => 'paused', 'days' => 0];
        }

        // If PMPro isn’t installed/active, we can’t compute correctly.
        if (!$this->pmproTableExists()) {
            return ['status' => 'unknown', 'days' => 0];
        }

        /**
         * We use PMPro enddate as the authority.
         * We pick the “most recent” membership row for this user across all levels.
         *
         * NOTE:
         * Since we’re gating by WP role, we don’t need to know specific level IDs.
         * The role tells us they are/were a club member at some point.
         */
        $row = $this->getLatestPmproMembershipRow($user_id);

        if (!is_array($row)) {
            return ['status' => 'unknown', 'days' => 0];
        }

        $now = current_time('timestamp');
        $pmpro_status = (string) ($row['status'] ?? '');
        $end_ts = $this->parsePmproEnddateToTimestamp($row['enddate'] ?? null);

        // Active if PMPro says active AND enddate is empty OR in future
        $has_end = ($end_ts !== null);
        $active_by_enddate = (!$has_end) || ($end_ts > $now);

        if ($pmpro_status === 'active' && $active_by_enddate) {
            return ['status' => 'active', 'days' => 0];
        }

        // If we have an enddate in the past, bucket by how many days since it ended.
        if ($has_end && $end_ts <= $now) {
            $days = (int) floor(max(0, $now - $end_ts) / DAY_IN_SECONDS);
            return $this->lapseBucket($days);
        }

        /**
         * If enddate is missing (NULL), but status is not active,
         * we can’t reliably compute a lapse age.
         * (This can happen in odd transition states.)
         */
        return ['status' => 'unknown', 'days' => 0];
    }

    protected function lapseBucket(int $days): array
    {
        if ($days >= $this->abandonedStart) {
            return ['status' => 'abandoned', 'days' => $days];
        }
        if ($days >= $this->pastDueStart) {
            return ['status' => 'past_due', 'days' => $days];
        }
        return ['status' => 'grace', 'days' => $days];
    }

    protected function persistStatusMeta(int $user_id, string $status, int $days): void
    {
        update_user_meta($user_id, $this->metaStatus, $status);
        update_user_meta($user_id, $this->metaDays, $days);

        // Sort order: active(0), grace(1), past_due(2), abandoned(3), paused(4), unknown(9)
        $sort = 9;
        if ($status === 'active') {
            $sort = 0;
        } elseif ($status === 'grace') {
            $sort = 1;
        } elseif ($status === 'past_due') {
            $sort = 2;
        } elseif ($status === 'abandoned') {
            $sort = 3;
        } elseif ($status === 'paused') {
            $sort = 4;
        }

        update_user_meta($user_id, $this->metaSort, $sort);
    }

    /* =======================================================================
     * PMPro TABLE HELPERS
     * ======================================================================= */

    protected function getLatestPmproMembershipRow(int $user_id): ?array
    {
        global $wpdb;

        $table = $this->pmproMembershipsUsersTableName();
        if (!$table) {
            return null;
        }

        /**
         * We want the most recently modified membership row.
         * This aligns well with “current state” in PMPro’s memberships_users table.
         */
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

    protected function parsePmproEnddateToTimestamp($enddate): ?int
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

    protected function pmproTableExists(): bool
    {
        global $wpdb;

        $table = $this->pmproMembershipsUsersTableName();
        if (!$table) {
            return false;
        }

        $like = $wpdb->esc_like($table);
        $found = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $like)); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        return !empty($found);
    }

    protected function pmproMembershipsUsersTableName(): string
    {
        global $wpdb;

        if (isset($wpdb->pmpro_memberships_users) && !empty($wpdb->pmpro_memberships_users)) {
            return (string) $wpdb->pmpro_memberships_users;
        }

        return (string) ($wpdb->prefix . 'pmpro_memberships_users');
    }

    /* =======================================================================
     * ROLE HELPERS
     * ======================================================================= */

    protected function userHasRole(int $user_id, string $role): bool
    {
        $user = get_userdata($user_id);
        if (!$user || empty($user->roles) || !is_array($user->roles)) {
            return false;
        }

        return in_array($role, $user->roles, true);
    }

    /* =======================================================================
     * BULK ACTIONS
     * ======================================================================= */

    public function registerBulkActions(array $actions): array
    {
        $actions['sccc_mark_paused']    = 'SCCC: Mark Paused';
        $actions['sccc_unpause']        = 'SCCC: Unpause';
        $actions['sccc_mark_abandoned'] = 'SCCC: Mark Abandoned (sets reset flag)';
        $actions['sccc_wipe_profile']   = 'SCCC: Wipe Club Profile (ACF)';
        $actions['sccc_reset_identity'] = 'SCCC: Reset Membership Identity (new number on rejoin)';
        return $actions;
    }

    public function handleBulkActions(string $redirect_to, string $action, array $user_ids): string
    {
        if (empty($user_ids)) {
            return $redirect_to;
        }

        $count = 0;

        foreach ($user_ids as $uid) {
            $user_id = (int) $uid;
            if ($user_id <= 0) {
                continue;
            }

            // We only apply actions to true members (role gated).
            if (!$this->userHasRole($user_id, $this->memberRole)) {
                continue;
            }

            switch ($action) {
                case 'sccc_mark_paused':
                    update_user_meta($user_id, $this->metaPaused, 1);
                    $count++;
                    break;

                case 'sccc_unpause':
                    delete_user_meta($user_id, $this->metaPaused);
                    $count++;
                    break;

                case 'sccc_mark_abandoned':
                    update_user_meta($user_id, $this->metaResetRequired, 1);
                    $count++;
                    break;

                case 'sccc_wipe_profile':
                    $this->wipeClubProfileAcf($user_id);
                    $count++;
                    break;

                case 'sccc_reset_identity':
                    delete_user_meta($user_id, 'membership_number');
                    delete_user_meta($user_id, 'membership_issued_at');
                    update_user_meta($user_id, $this->metaResetRequired, 1);
                    $count++;
                    break;
            }

            // Refresh cached status immediately
            $computed = $this->computeStatusForMemberUser($user_id);
            $this->persistStatusMeta($user_id, $computed['status'], $computed['days']);
        }

        if ($count > 0) {
            $redirect_to = add_query_arg([
                'sccc_bulk' => $action,
                'sccc_cnt'  => $count,
            ], $redirect_to);
        }

        return $redirect_to;
    }

    public function bulkActionNotices(): void
    {
        if (!is_admin()) {
            return;
        }

        $action = isset($_GET['sccc_bulk']) ? sanitize_text_field((string) $_GET['sccc_bulk']) : '';
        $cnt    = isset($_GET['sccc_cnt']) ? (int) $_GET['sccc_cnt'] : 0;

        if ($action === '' || $cnt <= 0) {
            return;
        }

        $labels = [
            'sccc_mark_paused'    => 'Paused',
            'sccc_unpause'        => 'Unpaused',
            'sccc_mark_abandoned' => 'Marked Abandoned',
            'sccc_wipe_profile'   => 'Wiped Club Profile (ACF)',
            'sccc_reset_identity' => 'Reset Membership Identity',
        ];

        $msg = $labels[$action] ?? 'Updated';

        echo '<div class="notice notice-success is-dismissible"><p>'
            . esc_html("SCCC: {$msg} for {$cnt} member(s).")
            . '</p></div>';
    }

    protected function wipeClubProfileAcf(int $user_id): void
    {
        if (!function_exists('delete_field')) {
            return;
        }

        $acf_id = 'user_' . $user_id;

        delete_field('birth_date', $acf_id);
        delete_field('is_veteran_first_responder', $acf_id);
        delete_field('veteran_type', $acf_id);
        delete_field('agency_branch', $acf_id);
        delete_field('vehicles', $acf_id);
    }

    /* =======================================================================
     * PMPro MEMBERS LIST: EXCLUDE ABANDONED
     * ======================================================================= */

    public function excludeAbandonedFromPmproMembersListSql(string $sql): string
    {
        global $wpdb;

        $clause = $wpdb->prepare("
            AND u.ID NOT IN (
                SELECT user_id
                FROM {$wpdb->usermeta}
                WHERE meta_key = %s
                  AND meta_value = 'abandoned'
            )
        ", $this->metaStatus);

        if (strpos($sql, "meta_value = 'abandoned'") !== false) {
            return $sql;
        }

        return $sql . ' ' . $clause;
    }
}

// Auto-boot when included (matches your require_once pattern).
(new \App\Support\Integrations\ScccMemberLifecycleAdmin())->register();
