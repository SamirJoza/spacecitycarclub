<?php
/**
 * app/Support/Members/MemberContext.php
 * File: app/Support/Members/MemberContext.php
 *
 * Why this exists
 * -----------------------------------------------------------------------------
 * You asked to keep logic and presentation separate.
 *
 * Many features need the exact same check:
 * - Is this user a “true member” for the SCCC member dashboard experience?
 *
 * Definition (your requirement)
 * -----------------------------------------------------------------------------
 * TRUE when:
 * - Logged-in user has WP role: sccc_member
 * - AND has an active PMPro membership
 *
 * This class is the single source of truth for that check.
 */

namespace App\Support\Members;

class MemberContext
{
    /**
     * True if the current user meets “true member” criteria.
     */
    public static function isTrueMember(?int $user_id = null): bool
    {
        $user_id = $user_id ?? get_current_user_id();

        if (! $user_id || $user_id <= 0) {
            return false;
        }

        $user = get_user_by('id', $user_id);
        if (! $user) {
            return false;
        }

        $roles = (array) ($user->roles ?? []);
        $has_role = in_array('sccc_member', $roles, true);

        // PMPro active membership check (enddate 0/empty = active, else future enddate).
        $has_active_pmpro = false;

        if (function_exists('pmpro_getMembershipLevelForUser')) {
            $level = pmpro_getMembershipLevelForUser($user_id);

            if (is_object($level)) {
                $end = isset($level->enddate) ? (int) $level->enddate : 0;

                $has_active_pmpro = ($end <= 0)
                    ? true
                    : ($end > (int) current_time('timestamp'));
            }
        } elseif (function_exists('pmpro_hasMembershipLevel')) {
            // Fallback: any level (less strict than enddate, but safer than guessing).
            $has_active_pmpro = (bool) pmpro_hasMembershipLevel(null, $user_id);
        }

        $is_member = ($has_role && $has_active_pmpro);

        /**
         * Escape hatch for admins/testing.
         * Return true/false, don’t return user objects here.
         */
        return (bool) apply_filters('sccc_is_true_member', $is_member, $user_id);
    }
}
