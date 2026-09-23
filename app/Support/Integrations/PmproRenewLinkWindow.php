<?php
/**
 * app/Support/Integrations/PmproRenewLinkWindow.php
 * File: app/Support/Integrations/PmproRenewLinkWindow.php
 *
 * Purpose
 * -----------------------------------------------------------------------------
 * Show the PMPro “Renew” link starting 90 days before expiration.
 *
 * Member-only scope
 * -----------------------------------------------------------------------------
 * Only apply this behavior for “true members” (role + active PMPro).
 */

namespace App\Support\Integrations;

use App\Support\Members\MemberContext;

class PmproRenewLinkWindow
{
    public function register(): void
    {
        add_filter('pmpro_is_level_expiring_soon', [$this, 'setExpiringSoonWindow'], 10, 2);
    }

    public function setExpiringSoonWindow(bool $is_expiring_soon, $level): bool
    {
        if (! MemberContext::isTrueMember()) {
            return $is_expiring_soon;
        }

        if (empty($level) || empty($level->enddate)) {
            return false;
        }

        $days = 90;
        $now  = (int) current_time('timestamp');

        return ($now + ($days * DAY_IN_SECONDS) >= (int) $level->enddate);
    }
}

(new \App\Support\Integrations\PmproRenewLinkWindow())->register();
